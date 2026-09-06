<?php

namespace App\Services;

use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Recurrencia;
use App\Models\TarjetaCredito;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioFinancieroService
{
    public function mensual(int $usuarioId, int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();
        $eventos = collect();

        HechoTesoreria::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->whereBetween('fecha', [$inicio, $fin])->get()->each(function (HechoTesoreria $hecho) use ($eventos): void {
                $eventos->push($this->evento($hecho->fecha, $hecho->tipo->value, 'real', $hecho->monto_centavos, $hecho->descripcion ?: ucfirst($hecho->tipo->value)));
            });
        Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->whereNotIn('tipo', ['prestamo', 'credito', 'tarjeta'])
            ->whereBetween('fecha', [$inicio, $fin])->get()->each(function (Pago $pago) use ($eventos): void {
                $eventos->push($this->evento($pago->fecha, 'pago', 'real', $pago->monto_centavos, $pago->destino ?: $pago->descripcion ?: 'Pago'));
            });

        CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->with('prestamo')->get()->each(function (CuotaPrestamo $cuota) use ($eventos): void {
                $estado = $cuota->pagada ? 'real' : ($cuota->fecha_vencimiento->lt(now()->startOfDay()) ? 'vencido' : 'proyectado');
                $eventos->push($this->evento($cuota->fecha_vencimiento, 'cuota', $estado, $cuota->total_centavos, 'Cuota '.($cuota->prestamo?->nombre ?: 'préstamo').' #'.$cuota->numero));
            });
        CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->with('compra.tarjetaCredito')->get()->each(function (CuotaTarjeta $cuota) use ($eventos): void {
                $estado = $cuota->pagada ? 'real' : ($cuota->fecha_vencimiento->lt(now()->startOfDay()) ? 'vencido' : 'proyectado');
                $eventos->push($this->evento($cuota->fecha_vencimiento, 'cuota', $estado, $cuota->total_centavos, 'Cuota '.($cuota->compra?->tarjetaCredito?->nombre ?: 'tarjeta').' #'.$cuota->numero));
            });

        Recurrencia::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->get()
            ->each(fn (Recurrencia $recurrencia) => $this->agregarRecurrencias($eventos, $recurrencia, $inicio, $fin));

        TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->get()
            ->each(function (TarjetaCredito $tarjeta) use ($eventos, $inicio): void {
                $corte = $this->fechaDia($inicio, $tarjeta->dia_corte);
                $pago = $this->fechaDia($inicio, $tarjeta->dia_pago);
                $eventos->push($this->evento($corte, 'corte', 'proyectado', 0, 'Corte · '.$tarjeta->nombre));
                $eventos->push($this->evento($pago, 'limite_tarjeta', 'proyectado', $tarjeta->pago_minimo_centavos, 'Límite de pago · '.$tarjeta->nombre));
            });

        return $eventos->sortBy(fn (array $evento) => $evento['fecha'].'-'.$evento['tipo'])->values()->all();
    }

    private function agregarRecurrencias(Collection $eventos, Recurrencia $recurrencia, Carbon $inicio, Carbon $fin): void
    {
        if (in_array($recurrencia->periodicidad, ['mensual', 'anual', 'unico'], true)
            && $this->recurrenciaYaCubrida($recurrencia, $inicio, $fin)) {
            return;
        }
        if ($recurrencia->periodicidad === 'unico') {
            $fecha = $this->fechaDia($inicio, $recurrencia->dia_del_mes);
            if ($fecha->isSameMonth($inicio)) {
                $eventos->push($this->evento($fecha, $recurrencia->tipo, 'proyectado', $recurrencia->monto_centavos, $recurrencia->nombre));
            }
            return;
        }
        $fecha = $this->fechaDia($inicio, $recurrencia->dia_del_mes);
        if ($recurrencia->periodicidad === 'anual' && $recurrencia->created_at?->month !== $inicio->month) {
            return;
        }
        $paso = match ($recurrencia->periodicidad) {
            'diario' => 1, 'semanal' => 7, 'quincenal' => 15, default => 0,
        };
        if ($paso === 0) {
            $eventos->push($this->evento($fecha, $recurrencia->tipo, 'proyectado', $recurrencia->monto_centavos, $recurrencia->nombre));
            return;
        }
        while ($fecha->lte($fin)) {
            if ($fecha->gte($inicio)) {
                $eventos->push($this->evento($fecha, $recurrencia->tipo, 'proyectado', $recurrencia->monto_centavos, $recurrencia->nombre));
            }
            $fecha->addDays($paso);
        }
    }

    private function recurrenciaYaCubrida(Recurrencia $recurrencia, Carbon $inicio, Carbon $fin): bool
    {
        if (! $recurrencia->categoria_id) {
            return false;
        }
        $hechos = (int) HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $recurrencia->usuario_id)
            ->where('tipo', $recurrencia->tipo)
            ->where('categoria_id', $recurrencia->categoria_id)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->count();
        $pagos = $recurrencia->tipo === 'gasto'
            ? (int) Pago::withoutGlobalScopes()
                ->where('usuario_id', $recurrencia->usuario_id)
                ->where('categoria_id', $recurrencia->categoria_id)
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->count()
            : 0;

        return ($hechos + $pagos) > 0;
    }

    private function fechaDia(Carbon $mes, int $dia): Carbon
    {
        return $mes->copy()->day(min($dia, $mes->daysInMonth));
    }

    private function evento(Carbon|string $fecha, string $tipo, string $estado, int $monto, string $descripcion): array
    {
        return ['fecha' => ($fecha instanceof Carbon ? $fecha : Carbon::parse($fecha))->toDateString(), 'tipo' => $tipo, 'estado' => $estado, 'monto_centavos' => $monto, 'descripcion' => $descripcion];
    }
}
