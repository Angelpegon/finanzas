<?php

namespace App\Support;

class Dinero
{
    public static function pesosACentavos(string|int|float $pesos): int
    {
        $bruto = trim((string) $pesos);
        $bruto = str_replace(['$', ' '], '', $bruto);
        if ($bruto === '') {
            throw new \InvalidArgumentException('Monto inválido.');
        }

        $negativo = str_starts_with($bruto, '-');
        $bruto = ltrim($bruto, '+-');

        if (str_contains($bruto, ',') && str_contains($bruto, '.')) {
            $ultimoComa = strrpos($bruto, ',');
            $ultimoPunto = strrpos($bruto, '.');
            if ($ultimoComa > $ultimoPunto) {
                $normalizado = str_replace('.', '', $bruto);
                $normalizado = str_replace(',', '.', $normalizado);
            } else {
                $normalizado = str_replace(',', '', $bruto);
            }
        } elseif (str_contains($bruto, ',')) {
            $normalizado = str_replace('.', '', $bruto);
            $normalizado = str_replace(',', '.', $normalizado);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $bruto)) {
            $normalizado = str_replace('.', '', $bruto);
        } else {
            $normalizado = $bruto;
        }

        if ($normalizado === '' || ! is_numeric($normalizado)) {
            throw new \InvalidArgumentException('Monto inválido.');
        }

        $centavos = (int) round(((float) $normalizado) * 100);

        return $negativo ? -$centavos : $centavos;
    }

    public static function centavosAPesos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    public static function formatear(int $centavos): string
    {
        $negativo = $centavos < 0;
        $abs = abs($centavos);
        $pesos = intdiv($abs, 100);
        $frac = $abs % 100;
        $cuerpo = number_format($pesos, 0, ',', '.');
        $texto = $frac === 0
            ? '$ '.$cuerpo
            : '$ '.$cuerpo.','.str_pad((string) $frac, 2, '0', STR_PAD_LEFT);

        return $negativo ? '-'.$texto : $texto;
    }
}
