<?php

namespace App\Support;

use Carbon\Carbon;

class AmortizacionFrancesa
{
    /**
     * Cuota fija mensual en centavos.
     *
     * @return list<array{numero:int,fecha:string,capital:int,interes:int,cuota:int,saldo:int}>
     */
    public static function calendario(int $principalCentavos, float $tasaPeriodo, int $plazos, Carbon $primeraFecha, string $periodicidad = 'mensual'): array
    {
        if ($principalCentavos <= 0 || $plazos < 1) {
            throw new \InvalidArgumentException('Principal y plazo deben ser positivos.');
        }

        $cuota = self::cuota($principalCentavos, $tasaPeriodo, $plazos);
        $saldo = $principalCentavos;
        $filas = [];

        for ($i = 1; $i <= $plazos; $i++) {
            $interes = (int) round($saldo * $tasaPeriodo);
            $capital = $cuota - $interes;
            if ($i === $plazos || $capital > $saldo) {
                $capital = $saldo;
                $cuotaFila = $capital + $interes;
            } else {
                $cuotaFila = $cuota;
            }
            $saldo -= $capital;
            $filas[] = [
                'numero' => $i,
                'fecha' => self::fechaCuota($primeraFecha, $i - 1, $periodicidad),
                'capital' => $capital,
                'interes' => $interes,
                'cuota' => $cuotaFila,
                'saldo' => max(0, $saldo),
            ];
        }

        return $filas;
    }

    public static function calendarioMetodo(
        int $principalCentavos, float $tasaPeriodo, int $plazos, Carbon $primeraFecha,
        string $metodo, int $seguroCentavos = 0, int $otrosCargosCentavos = 0, string $periodicidad = 'mensual'
    ): array {
        if ($metodo === 'frances') {
            $filas = self::calendario($principalCentavos, $tasaPeriodo, $plazos, $primeraFecha, $periodicidad);
        } elseif ($metodo === 'lineal') {
            $filas = [];
            $saldo = $principalCentavos;
            $capitalBase = intdiv($principalCentavos, $plazos);
            for ($i = 1; $i <= $plazos; $i++) {
                $capital = $i === $plazos ? $saldo : $capitalBase;
                $interes = (int) round($saldo * $tasaPeriodo);
                $saldo -= $capital;
                $filas[] = ['numero' => $i, 'fecha' => self::fechaCuota($primeraFecha, $i - 1, $periodicidad), 'capital' => $capital, 'interes' => $interes, 'cuota' => $capital + $interes, 'saldo' => max(0, $saldo)];
            }
        } elseif ($metodo === 'solo_interes') {
            $filas = [];
            for ($i = 1; $i <= $plazos; $i++) {
                $interes = (int) round($principalCentavos * $tasaPeriodo);
                $capital = $i === $plazos ? $principalCentavos : 0;
                $filas[] = ['numero' => $i, 'fecha' => self::fechaCuota($primeraFecha, $i - 1, $periodicidad), 'capital' => $capital, 'interes' => $interes, 'cuota' => $capital + $interes, 'saldo' => $i === $plazos ? 0 : $principalCentavos];
            }
        } else {
            throw new \InvalidArgumentException('Método de amortización no soportado.');
        }

        return array_map(function (array $fila) use ($seguroCentavos, $otrosCargosCentavos): array {
            $fila['seguro'] = $seguroCentavos;
            $fila['otros'] = $otrosCargosCentavos;
            $fila['total'] = $fila['cuota'] + $seguroCentavos + $otrosCargosCentavos;
            return $fila;
        }, $filas);
    }

    public static function cuota(int $principalCentavos, float $tasaPeriodo, int $plazos): int
    {
        if ($tasaPeriodo == 0.0) {
            return (int) ceil($principalCentavos / $plazos);
        }

        $factor = ($tasaPeriodo * ((1 + $tasaPeriodo) ** $plazos)) / (((1 + $tasaPeriodo) ** $plazos) - 1);

        return (int) round($principalCentavos * $factor);
    }

    /**
     * Número de cuotas restantes manteniendo la cuota (abono extra reduce plazo).
     */
    public static function plazosConCuotaFija(int $principalRestante, float $tasaPeriodo, int $cuotaCentavos): int
    {
        if ($principalRestante <= 0) {
            return 0;
        }
        if ($tasaPeriodo == 0.0) {
            return (int) ceil($principalRestante / max(1, $cuotaCentavos));
        }
        $capacidad = $cuotaCentavos - ($principalRestante * $tasaPeriodo);
        if ($capacidad <= 0) {
            throw new \InvalidArgumentException('La cuota no cubre los intereses del saldo restante.');
        }
        $n = log($cuotaCentavos / ($cuotaCentavos - $principalRestante * $tasaPeriodo)) / log(1 + $tasaPeriodo);

        return max(1, (int) ceil($n - 1e-9));
    }

    public static function fechaCuota(Carbon $primeraFecha, int $indiceCero, string $periodicidad = 'mensual'): string
    {
        $fecha = $primeraFecha->copy();

        return match ($periodicidad) {
            'semanal' => $fecha->addWeeks($indiceCero)->toDateString(),
            'quincenal' => $fecha->addDays(15 * $indiceCero)->toDateString(),
            'anual' => $fecha->addYears($indiceCero)->toDateString(),
            default => $fecha->addMonths($indiceCero)->toDateString(),
        };
    }
}
