<?php

namespace App\Support;

use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Recurrencia;
use App\Services\PagoService;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Agregado mensual de recurrencias alineado a reglas 38 / proyección.
 * El calendario proyecta por día; aquí se resume el mes para Situación y Proyecciones.
 */
class RecurrenciaMensual
{
    public static function montoBrutoEnMes(Recurrencia $recurrencia, CarbonInterface $inicioMes): int
    {
        $monto = (int) $recurrencia->monto_centavos;
        if ($monto <= 0) {
            return 0;
        }

        $inicioMes = Carbon::parse($inicioMes)->startOfMonth();

        return match ($recurrencia->periodicidad) {
            'diario' => $monto * $inicioMes->daysInMonth,
            'semanal' => $monto * self::ocurrenciasConPaso($recurrencia, $inicioMes, 7),
            'quincenal' => $monto * self::ocurrenciasConPaso($recurrencia, $inicioMes, 15),
            'mensual' => $monto,
            'anual' => self::esMesAniversario($recurrencia, $inicioMes) ? $monto : 0,
            'unico' => self::fechaUnicoEnMes($recurrencia, $inicioMes) ? $monto : 0,
            default => $monto,
        };
    }

    /**
     * Bruto del mes menos reales de la misma categoría (reversos excluidos).
     */
    public static function montoPendienteEnMes(Recurrencia $recurrencia, CarbonInterface $inicioMes): int
    {
        $inicioMes = Carbon::parse($inicioMes)->startOfMonth();
        $bruto = self::montoBrutoEnMes($recurrencia, $inicioMes);
        if ($bruto <= 0) {
            return 0;
        }

        $cubierto = self::montoCubiertoEnPeriodo(
            $recurrencia,
            $inicioMes->copy()->startOfMonth(),
            $inicioMes->copy()->endOfMonth()
        );

        return max(0, $bruto - $cubierto);
    }

    public static function montoCubiertoEnPeriodo(Recurrencia $recurrencia, CarbonInterface $inicio, CarbonInterface $fin): int
    {
        $inicio = Carbon::parse($inicio);
        $fin = Carbon::parse($fin);
        if (! $recurrencia->categoria_id) {
            return 0;
        }

        $hechos = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $recurrencia->usuario_id)
            ->where('tipo', $recurrencia->tipo)
            ->where('categoria_id', $recurrencia->categoria_id)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        $total = (int) AgregadosLibro::excluirOrigenesRevertidos(
            $hechos,
            HechoTesoreria::class,
            (int) $recurrencia->usuario_id
        )->sum('monto_centavos');

        if ($recurrencia->tipo === 'gasto') {
            $pagos = Pago::withoutGlobalScopes()
                ->where('usuario_id', $recurrencia->usuario_id)
                ->whereIn('tipo', PagoService::TIPOS_GENERICOS)
                ->where('categoria_id', $recurrencia->categoria_id)
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
            $total += (int) AgregadosLibro::excluirOrigenesRevertidos(
                $pagos,
                Pago::class,
                (int) $recurrencia->usuario_id
            )->sum('monto_centavos');
        }

        return $total;
    }

    public static function sumaPendiente(int $usuarioId, string $tipo, CarbonInterface $inicioMes): int
    {
        $inicioMes = Carbon::parse($inicioMes);

        return (int) Recurrencia::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', $tipo)
            ->where('activa', true)
            ->get()
            ->sum(fn (Recurrencia $r): int => self::montoPendienteEnMes($r, $inicioMes));
    }

    public static function sumaBruta(int $usuarioId, string $tipo, CarbonInterface $inicioMes): int
    {
        $inicioMes = Carbon::parse($inicioMes);

        return (int) Recurrencia::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', $tipo)
            ->where('activa', true)
            ->get()
            ->sum(fn (Recurrencia $r): int => self::montoBrutoEnMes($r, $inicioMes));
    }

    private static function esMesAniversario(Recurrencia $recurrencia, CarbonInterface $inicioMes): bool
    {
        $ancla = Carbon::parse($recurrencia->created_at ?? $inicioMes);

        return (int) $ancla->month === (int) Carbon::parse($inicioMes)->month;
    }

    private static function fechaUnicoEnMes(Recurrencia $recurrencia, CarbonInterface $inicioMes): bool
    {
        $inicioMes = Carbon::parse($inicioMes);
        $ancla = Carbon::parse($recurrencia->created_at ?? $inicioMes)->startOfDay();
        $fecha = $ancla->copy()->day(min((int) $recurrencia->dia_del_mes, $ancla->daysInMonth));

        return $fecha->isSameMonth($inicioMes);
    }

    /** Misma ancla que el calendario: día del mes, luego +paso. */
    private static function ocurrenciasConPaso(Recurrencia $recurrencia, CarbonInterface $inicioMes, int $paso): int
    {
        $inicioMes = Carbon::parse($inicioMes)->startOfMonth();
        $fin = $inicioMes->copy()->endOfMonth();
        $fecha = $inicioMes->copy()->day(min(max(1, (int) $recurrencia->dia_del_mes), $inicioMes->daysInMonth));
        $n = 0;
        while ($fecha->lte($fin)) {
            if ($fecha->gte($inicioMes)) {
                $n++;
            }
            $fecha->addDays($paso);
        }

        return max(0, $n);
    }
}
