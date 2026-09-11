<?php

namespace App\Services;

use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\Presupuesto;
use App\Models\TarjetaCredito;
use Illuminate\Support\Carbon;

class AlertaService
{
    public function evaluar(int $usuarioId, array $situacion, ?Carbon $fecha = null): array
    {
        $fecha ??= now();
        $alertas = [];
        $enlaceCalendario = route('app.calendario', [
            'anio' => $fecha->year,
            'mes' => $fecha->month,
        ]);

        $vencidos = CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereDate('fecha_vencimiento', '<', $fecha->toDateString())->count()
            + CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
                ->whereDate('fecha_vencimiento', '<', $fecha->toDateString())->count();
        if ($vencidos > 0) {
            $alertas[] = $this->crear(
                'danger',
                'Pago vencido',
                "Tienes {$vencidos} cuota(s) vencida(s).",
                $enlaceCalendario
            );
        }

        $proximos = CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(7)->endOfDay()])->count()
            + CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
                ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(7)->endOfDay()])->count();
        if ($proximos > 0) {
            $alertas[] = $this->crear(
                'warning',
                'Pago próximo',
                "Tienes {$proximos} cuota(s) por pagar en los próximos 7 días.",
                $enlaceCalendario
            );
        }

        $presupuesto = Presupuesto::with('lineas.categoria')->withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('anio', $fecha->year)->where('mes', $fecha->month)->first();
        app(PresupuestoService::class)->enriquecer($presupuesto);
        foreach ($presupuesto?->lineas ?? [] as $linea) {
            $cruzado = collect($linea->alertas ?? [])->max() ?: 0;
            if ($cruzado >= 100) {
                $alertas[] = $this->crear(
                    'danger',
                    'Presupuesto excedido',
                    "{$linea->categoria->nombre} superó el presupuesto.",
                    route('app.presupuestos.index')
                );
            } elseif ($cruzado > 0) {
                $alertas[] = $this->crear(
                    'warning',
                    'Presupuesto en umbral',
                    "{$linea->categoria->nombre} alcanzó el {$cruzado}% del presupuesto.",
                    route('app.presupuestos.index')
                );
            }
        }

        foreach (TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->get() as $tarjeta) {
            $utilizacion = $tarjeta->cupo_centavos > 0
                ? ($tarjeta->saldo_actual_centavos / $tarjeta->cupo_centavos) * 100
                : 0;
            if ($utilizacion >= 80) {
                $alertas[] = $this->crear(
                    'warning',
                    'Cupo de tarjeta elevado',
                    "{$tarjeta->nombre} tiene {$this->formatearPorcentaje($utilizacion)}% del cupo utilizado.",
                    route('app.tarjetas.index')
                );
            }
        }

        foreach (\App\Models\MetaAhorro::withoutGlobalScopes()->where('usuario_id', $usuarioId)->get() as $meta) {
            $objetivo = (int) $meta->objetivo_centavos;
            $avance = (int) $meta->monto_actual_centavos;
            if ($objetivo > 0 && $avance >= (int) round($objetivo * 0.9) && $avance < $objetivo) {
                $alertas[] = $this->crear(
                    'info',
                    'Meta casi cumplida',
                    "{$meta->nombre} lleva ".$this->formatearPorcentaje(($avance / $objetivo) * 100).'% del objetivo.',
                    route('app.metas.index')
                );
            }
            if ($meta->estado === 'activa' && $meta->fecha_objetivo && $meta->fecha_objetivo->isPast() && $avance < $objetivo) {
                $alertas[] = $this->crear(
                    'warning',
                    'Meta vencida',
                    "{$meta->nombre} pasó su fecha objetivo sin completarse.",
                    route('app.metas.index')
                );
            } elseif (
                $meta->estado === 'activa'
                && (int) $meta->aporte_mensual_centavos > 0
                && $meta->fecha_objetivo
                && $meta->fecha_objetivo->greaterThan($fecha)
                && (int) $meta->ahorro_mensual_necesario_centavos > (int) $meta->aporte_mensual_centavos * 1.2
            ) {
                $alertas[] = $this->crear(
                    'warning',
                    'Meta fuera de ritmo',
                    "{$meta->nombre}: con el plan actual no alcanzas la fecha objetivo.",
                    route('app.metas.index')
                );
            }
        }

        $actual = $this->totalesMes($usuarioId, $fecha);
        $anterior = $this->totalesMes($usuarioId, $fecha->copy()->subMonth());
        if ($anterior['gastos'] > 0 && $actual['gastos'] > $anterior['gastos'] * 1.2) {
            $alertas[] = $this->crear(
                'warning',
                'Aumento de gastos',
                'Tus gastos reales aumentaron más de 20% frente al mes anterior.',
                route('app.gastos.index')
            );
        }
        if ($anterior['ingresos'] > 0 && $actual['ingresos'] < $anterior['ingresos'] * 0.8) {
            $alertas[] = $this->crear(
                'warning',
                'Disminución de ingresos',
                'Tus ingresos reales disminuyeron más de 20% frente al mes anterior.',
                route('app.ingresos.index')
            );
        }
        if ($situacion['flujo_caja_centavos'] < 0) {
            $alertas[] = $this->crear(
                'danger',
                'Flujo de caja negativo',
                'Este mes tus salidas superan tus ingresos.',
                route('app.situacion')
            );
        }
        if ($situacion['nivel_endeudamiento_porcentaje'] >= 50) {
            $alertas[] = $this->crear(
                'warning',
                'Nivel de endeudamiento elevado',
                'La deuda supera el 50% del saldo de tus cuentas.',
                route('app.deudas.index')
            );
        }

        return $alertas;
    }

    private function totalesMes(int $usuarioId, Carbon $fecha): array
    {
        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();

        return [
            'ingresos' => \App\Support\AgregadosLibro::ingresosReales($usuarioId, $inicio, $fin),
            'gastos' => \App\Support\AgregadosLibro::gastosReales($usuarioId, $inicio, $fin),
        ];
    }

    /**
     * @return array{nivel: string, titulo: string, mensaje: string, enlace: ?string}
     */
    private function crear(string $nivel, string $titulo, string $mensaje, ?string $enlace = null): array
    {
        return compact('nivel', 'titulo', 'mensaje', 'enlace');
    }

    private function formatearPorcentaje(float $valor): string
    {
        return number_format($valor, 1, ',', '.');
    }
}
