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

        $vencidos = CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereDate('fecha_vencimiento', '<', $fecha->toDateString())->count()
            + CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
                ->whereDate('fecha_vencimiento', '<', $fecha->toDateString())->count();
        if ($vencidos > 0) {
            $alertas[] = $this->crear('danger', 'Pago vencido', "Tienes {$vencidos} cuota(s) vencida(s).");
        }

        $proximos = CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(7)->endOfDay()])->count()
            + CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
                ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(7)->endOfDay()])->count();
        if ($proximos > 0) {
            $alertas[] = $this->crear('warning', 'Pago próximo', "Tienes {$proximos} cuota(s) por pagar en los próximos 7 días.");
        }

        $presupuesto = Presupuesto::with('lineas.categoria')->withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('anio', $fecha->year)->where('mes', $fecha->month)->first();
        foreach ($presupuesto?->lineas ?? [] as $linea) {
            $cruzado = collect($linea->alertas)->max() ?: 0;
            if ($cruzado >= 100) {
                $alertas[] = $this->crear('danger', 'Presupuesto excedido', "{$linea->categoria->nombre} superó el presupuesto.");
            } elseif ($cruzado > 0) {
                $alertas[] = $this->crear('warning', 'Presupuesto en umbral', "{$linea->categoria->nombre} alcanzó el {$cruzado}% del presupuesto.");
            }
        }

        foreach (TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->get() as $tarjeta) {
            $utilizacion = $tarjeta->cupo_centavos > 0
                ? ($tarjeta->saldo_actual_centavos / $tarjeta->cupo_centavos) * 100
                : 0;
            if ($utilizacion >= 80) {
                $alertas[] = $this->crear('warning', 'Cupo de tarjeta elevado', "{$tarjeta->nombre} tiene {$this->formatearPorcentaje($utilizacion)}% del cupo utilizado.");
            }
        }

        $actual = $this->totalesMes($usuarioId, $fecha);
        $anterior = $this->totalesMes($usuarioId, $fecha->copy()->subMonth());
        if ($anterior['gastos'] > 0 && $actual['gastos'] > $anterior['gastos'] * 1.2) {
            $alertas[] = $this->crear('warning', 'Aumento de gastos', 'Tus gastos reales aumentaron más de 20% frente al mes anterior.');
        }
        if ($anterior['ingresos'] > 0 && $actual['ingresos'] < $anterior['ingresos'] * 0.8) {
            $alertas[] = $this->crear('warning', 'Disminución de ingresos', 'Tus ingresos reales disminuyeron más de 20% frente al mes anterior.');
        }
        if ($situacion['flujo_caja_centavos'] < 0) {
            $alertas[] = $this->crear('danger', 'Flujo de caja negativo', 'Este mes tus salidas superan tus ingresos.');
        }
        if ($situacion['nivel_endeudamiento_porcentaje'] >= 50) {
            $alertas[] = $this->crear('warning', 'Nivel de endeudamiento elevado', 'La deuda supera el 50% del saldo de tus cuentas.');
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

    private function crear(string $nivel, string $titulo, string $mensaje): array
    {
        return compact('nivel', 'titulo', 'mensaje');
    }

    private function formatearPorcentaje(float $valor): string
    {
        return number_format($valor, 1, ',', '.');
    }
}
