<?php

namespace App\Support;

class Tasa
{
    /** Convierte EA % a tasa efectiva mensual. */
    public static function mensualDesdeEA(float $eaPorcentaje): float
    {
        return self::tasaPeriodo($eaPorcentaje, 'ea', 'mensual');
    }

    public static function eaDesdeMensual(float $mensualPorcentaje): float
    {
        if ($mensualPorcentaje < 0) {
            throw new \InvalidArgumentException('La tasa no puede ser negativa.');
        }

        return (((1 + ($mensualPorcentaje / 100)) ** 12) - 1) * 100;
    }

    public static function periodosPorAnio(string $periodicidad): int
    {
        return match ($periodicidad) {
            'semanal' => 52,
            'quincenal' => 24,
            'anual' => 1,
            default => 12,
        };
    }

    public static function tasaPeriodo(float $porcentaje, string $tipoTasa = 'ea', string $periodicidad = 'mensual'): float
    {
        if ($porcentaje < 0) {
            throw new \InvalidArgumentException('La tasa no puede ser negativa.');
        }

        $n = self::periodosPorAnio($periodicidad);
        $p = $porcentaje / 100;

        return match ($tipoTasa) {
            'mensual' => $periodicidad === 'mensual'
                ? $p
                : ((1 + $p) ** (12 / $n)) - 1,
            'nominal' => $p / $n,
            default => ((1 + $p) ** (1 / $n)) - 1,
        };
    }

    public static function eaEquivalente(float $porcentaje, string $tipoTasa): float
    {
        return match ($tipoTasa) {
            'mensual' => self::eaDesdeMensual($porcentaje),
            'nominal' => ((((1 + ($porcentaje / 100) / 12) ** 12) - 1) * 100),
            default => $porcentaje,
        };
    }
}
