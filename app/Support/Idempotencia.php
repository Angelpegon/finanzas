<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class Idempotencia
{
    /**
     * Registra una clave de un solo uso. Si ya existía, lanza validación.
     */
    public static function consumir(string $ambito, int $usuarioId, ?string $clave, int $ttlSegundos = 300): void
    {
        $clave = trim((string) $clave);
        if ($clave === '' || strlen($clave) > 64 || ! preg_match('/^[A-Za-z0-9_-]+$/', $clave)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'No se pudo validar el envío. Recarga e intenta de nuevo.',
            ]);
        }

        $ok = Cache::add("idem:{$ambito}:{$usuarioId}:{$clave}", 1, $ttlSegundos);
        if (! $ok) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Este envío ya se estaba procesando. Revisa el listado antes de volver a guardar.',
            ]);
        }
    }

    /** Libera la clave si el procesamiento falló después de consumirla. */
    public static function liberar(string $ambito, int $usuarioId, ?string $clave): void
    {
        $clave = trim((string) $clave);
        if ($clave === '' || strlen($clave) > 64) {
            return;
        }

        Cache::forget("idem:{$ambito}:{$usuarioId}:{$clave}");
    }
}
