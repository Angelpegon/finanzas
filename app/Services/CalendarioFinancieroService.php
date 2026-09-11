<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Recurrencia;
use App\Models\TarjetaCredito;
use App\Support\AgregadosLibro;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Proyección de lectura para la grilla/agenda. No escribe libro.
 * Refleja estado efectivo: excluye orígenes revertidos; cuotas solo pendientes;
 * pagos de préstamo/tarjeta aparecen como reales al liquidarse.
 */
class CalendarioFinancieroService
{
    /** Hechos de caja / metas visibles en el radar (no apertura, cierre ni transferencia). */
    private const TIPOS_HECHO_RADAR = [
        TipoHechoTesoreria::Ingreso->value,
        TipoHechoTesoreria::Gasto->value,
        TipoHechoTesoreria::AporteMeta->value,
        TipoHechoTesoreria::RetiroMeta->value,
    ];

    public function mensual(int $usuarioId, int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();
        $eventos = collect();
        $hoy = now()->startOfDay();

        $hechos = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereIn('tipo', self::TIPOS_HECHO_RADAR)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        AgregadosLibro::excluirOrigenesRevertidos($hechos, HechoTesoreria::class, $usuarioId)
            ->get()
            ->each(function (HechoTesoreria $hecho) use ($eventos): void {
                $tipo = $hecho->tipo->value;
                $eventos->push($this->evento(
                    $hecho->fecha,
                    $tipo,
                    'real',
                    (int) $hecho->monto_centavos,
                    $hecho->descripcion ?: ucfirst(str_replace('_', ' ', $tipo)),
                    $this->enlaceHecho($tipo)
                ));
            });

        $pagos = Pago::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereIn('tipo', array_merge(PagoService::TIPOS_GENERICOS, ['prestamo', 'tarjeta']))
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->with(['prestamo', 'tarjetaCredito']);
        AgregadosLibro::excluirOrigenesRevertidos($pagos, Pago::class, $usuarioId)
            ->get()
            ->each(function (Pago $pago) use ($eventos): void {
                $etiqueta = match ($pago->tipo) {
                    'prestamo' => 'Pago · '.($pago->prestamo?->nombre ?: 'préstamo'),
                    'tarjeta' => 'Pago · '.($pago->tarjetaCredito?->nombre ?: 'tarjeta'),
                    default => $pago->destino ?: $pago->descripcion ?: 'Pago',
                };
                $eventos->push($this->evento(
                    $pago->fecha,
                    'pago',
                    'real',
                    (int) $pago->monto_centavos,
                    $etiqueta,
                    route('app.pagos.index', ['pago' => 1])
                ));
            });

        CuotaPrestamo::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$inicio->toDateString(), $fin->toDateString()])
            ->with('prestamo')
            ->get()
            ->each(function (CuotaPrestamo $cuota) use ($eventos, $hoy): void {
                $estado = $cuota->fecha_vencimiento->lt($hoy) ? 'vencido' : 'proyectado';
                $eventos->push($this->evento(
                    $cuota->fecha_vencimiento,
                    'cuota',
                    $estado,
                    (int) $cuota->total_centavos,
                    'Cuota '.($cuota->prestamo?->nombre ?: 'préstamo').' #'.$cuota->numero,
                    route('app.deudas.index')
                ));
            });

        CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$inicio->toDateString(), $fin->toDateString()])
            ->with('compra.tarjetaCredito')
            ->get()
            ->each(function (CuotaTarjeta $cuota) use ($eventos, $hoy): void {
                $estado = $cuota->fecha_vencimiento->lt($hoy) ? 'vencido' : 'proyectado';
                $eventos->push($this->evento(
                    $cuota->fecha_vencimiento,
                    'cuota',
                    $estado,
                    (int) $cuota->total_centavos,
                    'Cuota '.($cuota->compra?->tarjetaCredito?->nombre ?: 'tarjeta').' #'.$cuota->numero,
                    route('app.tarjetas.index')
                ));
            });

        Recurrencia::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->get()
            ->each(fn (Recurrencia $recurrencia) => $this->agregarRecurrencias($eventos, $recurrencia, $inicio, $fin, $hoy));

        // Corte: marca informativa del ciclo (sin monto). El compromiso de caja son las cuotas.
        TarjetaCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->get()
            ->each(function (TarjetaCredito $tarjeta) use ($eventos, $inicio): void {
                $corte = $this->fechaDia($inicio, (int) $tarjeta->dia_corte);
                $eventos->push($this->evento(
                    $corte,
                    'corte',
                    'proyectado',
                    0,
                    'Corte · '.$tarjeta->nombre,
                    route('app.tarjetas.index')
                ));
            });

        return $eventos->sortBy(fn (array $evento) => $evento['fecha'].'-'.$evento['tipo'])->values()->all();
    }

    /**
     * @param  array<int, array{fecha: string, tipo: string, estado: string, monto_centavos: int, descripcion: string, enlace?: ?string}>  $eventos
     * @return array{anio: int, mes: int, etiqueta: string, celdas: list<null|array<string, mixed>>}
     */
    public function grillaMensual(Carbon $fecha, array $eventos): array
    {
        $porDia = collect($eventos)->groupBy('fecha');
        $inicioMes = $fecha->copy()->startOfMonth();
        $diasEnMes = $inicioMes->daysInMonth;
        $offset = ($inicioMes->dayOfWeek + 6) % 7;
        $celdas = [];
        for ($i = 0; $i < $offset; $i++) {
            $celdas[] = null;
        }
        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fechaDia = $inicioMes->copy()->day($dia)->toDateString();
            $eventosDia = ($porDia[$fechaDia] ?? collect())->values()->all();
            $celdas[] = [
                'dia' => $dia,
                'fecha' => $fechaDia,
                'hoy' => $fechaDia === now()->toDateString(),
                'eventos' => $eventosDia,
                'monto_centavos' => (int) collect($eventosDia)
                    ->reject(fn ($e) => ($e['tipo'] ?? '') === 'corte')
                    ->sum('monto_centavos'),
                'tiene_pago' => collect($eventosDia)->contains(
                    fn ($e) => in_array($e['tipo'], ['cuota', 'pago', 'gasto'], true)
                ),
                'tiene_ingreso' => collect($eventosDia)->contains(fn ($e) => $e['tipo'] === 'ingreso'),
                'tiene_vencido' => collect($eventosDia)->contains(fn ($e) => ($e['estado'] ?? '') === 'vencido'),
            ];
        }
        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        return [
            'anio' => $fecha->year,
            'mes' => $fecha->month,
            'etiqueta' => ucfirst($fecha->copy()->locale('es')->monthName).' '.$fecha->year,
            'celdas' => $celdas,
        ];
    }

    /**
     * @param  array<int, array{fecha: string, tipo: string, estado: string, monto_centavos: int, descripcion: string}>  $eventos
     * @return array{
     *     reales: int,
     *     proyectados: int,
     *     vencidos: int,
     *     ingresos_centavos: int,
     *     salidas_centavos: int,
     *     salidas_proyectadas_centavos: int
     * }
     */
    public function resumenMensual(array $eventos): array
    {
        $col = collect($eventos);
        $esSalidaReal = fn (array $e): bool => ($e['estado'] ?? '') === 'real'
            && in_array($e['tipo'], ['gasto', 'pago'], true);
        $esSalidaProyectada = fn (array $e): bool => in_array($e['estado'] ?? '', ['proyectado', 'vencido'], true)
            && in_array($e['tipo'], ['gasto', 'cuota'], true);

        return [
            'reales' => $col->where('estado', 'real')->count(),
            'proyectados' => $col->where('estado', 'proyectado')->count(),
            'vencidos' => $col->where('estado', 'vencido')->count(),
            'ingresos_centavos' => (int) $col
                ->where('estado', 'real')
                ->where('tipo', 'ingreso')
                ->sum('monto_centavos'),
            'salidas_centavos' => (int) $col->filter($esSalidaReal)->sum('monto_centavos'),
            'salidas_proyectadas_centavos' => (int) $col->filter($esSalidaProyectada)->sum('monto_centavos'),
        ];
    }

    private function agregarRecurrencias(
        Collection $eventos,
        Recurrencia $recurrencia,
        Carbon $inicio,
        Carbon $fin,
        Carbon $hoy
    ): void {
        $enlace = $recurrencia->tipo === 'ingreso'
            ? route('app.ingresos.index')
            : route('app.gastos.index');

        if ($recurrencia->periodicidad === 'unico') {
            $ancla = Carbon::parse($recurrencia->created_at ?? $inicio)->startOfDay();
            $fecha = $ancla->copy()->day(min((int) $recurrencia->dia_del_mes, $ancla->daysInMonth));
            if (! $fecha->betweenIncluded($inicio, $fin)) {
                return;
            }
            if ($this->montoCubiertoPeriodo($recurrencia, $fecha->copy()->startOfDay(), $fecha->copy()->endOfDay())
                >= (int) $recurrencia->monto_centavos) {
                return;
            }
            $estado = $fecha->lt($hoy) ? 'vencido' : 'proyectado';
            $eventos->push($this->evento(
                $fecha,
                $recurrencia->tipo,
                $estado,
                (int) $recurrencia->monto_centavos,
                $recurrencia->nombre,
                $enlace
            ));

            return;
        }

        if (in_array($recurrencia->periodicidad, ['mensual', 'anual'], true)
            && $this->montoCubiertoPeriodo($recurrencia, $inicio, $fin) >= (int) $recurrencia->monto_centavos) {
            return;
        }

        // Anual: aniversario = mes de creación (sin columna dedicada en fase 1).
        if ($recurrencia->periodicidad === 'anual') {
            $mesAniversario = (int) Carbon::parse($recurrencia->created_at ?? $inicio)->month;
            if ($mesAniversario !== (int) $inicio->month) {
                return;
            }
        }

        $fecha = $this->fechaDia($inicio, (int) $recurrencia->dia_del_mes);
        $paso = match ($recurrencia->periodicidad) {
            'diario' => 1,
            'semanal' => 7,
            'quincenal' => 15,
            default => 0,
        };

        if ($paso === 0) {
            $estado = $fecha->lt($hoy) ? 'vencido' : 'proyectado';
            $eventos->push($this->evento(
                $fecha,
                $recurrencia->tipo,
                $estado,
                (int) $recurrencia->monto_centavos,
                $recurrencia->nombre,
                $enlace
            ));

            return;
        }

        // Diario / semanal / quincenal: una ocurrencia por fecha; se omite si ya hay hecho/pago ese día.
        while ($fecha->lte($fin)) {
            if ($fecha->gte($inicio)
                && $this->montoCubiertoPeriodo($recurrencia, $fecha->copy()->startOfDay(), $fecha->copy()->endOfDay())
                    < (int) $recurrencia->monto_centavos) {
                $estado = $fecha->lt($hoy) ? 'vencido' : 'proyectado';
                $eventos->push($this->evento(
                    $fecha->copy(),
                    $recurrencia->tipo,
                    $estado,
                    (int) $recurrencia->monto_centavos,
                    $recurrencia->nombre,
                    $enlace
                ));
            }
            $fecha->addDays($paso);
        }
    }

    private function montoCubiertoPeriodo(Recurrencia $recurrencia, Carbon $inicio, Carbon $fin): int
    {
        return \App\Support\RecurrenciaMensual::montoCubiertoEnPeriodo($recurrencia, $inicio, $fin);
    }

    private function fechaDia(Carbon $mes, int $dia): Carbon
    {
        return $mes->copy()->day(min(max(1, $dia), $mes->daysInMonth))->startOfDay();
    }

    private function enlaceHecho(string $tipo): string
    {
        return match ($tipo) {
            TipoHechoTesoreria::Ingreso->value => route('app.ingresos.index'),
            TipoHechoTesoreria::Gasto->value => route('app.gastos.index'),
            TipoHechoTesoreria::AporteMeta->value,
            TipoHechoTesoreria::RetiroMeta->value => route('app.metas.index'),
            default => route('app.calendario'),
        };
    }

    /**
     * @return array{fecha: string, tipo: string, estado: string, monto_centavos: int, descripcion: string, enlace: ?string}
     */
    private function evento(
        Carbon|string $fecha,
        string $tipo,
        string $estado,
        int $monto,
        string $descripcion,
        ?string $enlace = null
    ): array {
        return [
            'fecha' => ($fecha instanceof Carbon ? $fecha : Carbon::parse($fecha))->toDateString(),
            'tipo' => $tipo,
            'estado' => $estado,
            'monto_centavos' => $monto,
            'descripcion' => $descripcion,
            'enlace' => $enlace,
        ];
    }
}
