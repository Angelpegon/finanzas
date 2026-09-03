<?php

namespace App\Support;

class Tasa
{
    /** Convierte EA % a tasa efectiva mensual. */
    public static function mensualDesdeEA(float $eaPorcentaje): float
    {
        if ($eaPorcentaje < 0) {
            throw new \InvalidArgumentException('La EA no puede ser negativa.');
        }

        return ((1 + ($eaPorcentaje / 100)) ** (1 / 12)) - 1;
    }
}
