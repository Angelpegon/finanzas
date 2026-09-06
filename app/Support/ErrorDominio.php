<?php

namespace App\Support;

/**
 * Mapea excepciones de dominio a campos de formulario.
 * Evita colgar en "monto" errores que hablan de cuenta, referencia, tipo, etc.
 */
final class ErrorDominio
{
    /**
     * @return array<string, string>
     */
    public static function aCampo(\Throwable $e, string $fallback = 'form'): array
    {
        $msg = $e->getMessage();
        $lower = mb_strtolower($msg);

        return match (true) {
            str_contains($lower, 'cuenta cancelada') || str_contains($lower, 'ya está cancelada') => ['form' => $msg],
            str_contains($lower, 'bolsillo de una meta') || str_contains($lower, 'préstamo con cuotas') => ['form' => $msg],
            str_contains($lower, 'tiene saldo') || str_contains($lower, 'dar de baja') || str_contains($lower, 'transfiere') => ['disposicion' => $msg],
            str_contains($lower, 'cuenta destino') => ['cuenta_destino_id' => $msg],
            str_contains($lower, 'cuenta de referencia') || str_contains($lower, 'cuenta operativa') => ['cuenta_liquida_id' => $msg],
            str_contains($lower, 'referencia') => ['referencia' => $msg],
            str_contains($lower, 'categoría') || str_contains($lower, 'categoria') => ['categoria_id' => $msg],
            str_contains($lower, 'saldo insuficiente') => ['cuenta_liquida_id' => $msg],
            str_contains($lower, 'distinta al bolsillo') => ['cuenta_liquida_id' => $msg],
            str_contains($lower, 'origen y destino') || str_contains($lower, 'deben ser diferentes') => ['cuenta_destino_id' => $msg],
            str_contains($lower, 'bolsillo') || str_contains($lower, 'cuenta destino') || str_contains($lower, 'cuenta de origen') || str_contains($lower, 'cuenta de pago') => ['cuenta_liquida_id' => $msg],
            str_contains($lower, 'módulo especializado') || str_contains($lower, 'préstamos y tarjetas') => ['tipo' => $msg],
            str_contains($lower, 'meta cancelada') => ['meta_ahorro_id' => $msg],
            str_contains($lower, 'cupo') => ['monto' => $msg],
            str_contains($lower, 'cuota') && str_contains($lower, 'completo') => ['monto' => $msg],
            str_contains($lower, 'abono extra') => ['monto' => $msg],
            str_contains($lower, 'cuotas pendientes') => ['prestamo_id' => $msg],
            str_contains($lower, 'monto') || str_contains($lower, 'aporte debe ser') || str_contains($lower, 'pago debe ser') => ['monto' => $msg],
            default => [$fallback => $msg],
        };
    }
}
