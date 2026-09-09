<?php

namespace App\Support;

use App\Models\MetaAhorro;
use Illuminate\Validation\Rules\Exists;

/**
 * Cuentas líquidas que no son bolsillo de meta activa
 * (operativas para gasto/ingreso/transferencia).
 */
final class CuentasOperativas
{
    /** @return list<int> */
    public static function idsBolsillosActivos(int $usuarioId): array
    {
        return MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'activa')
            ->whereNotNull('cuenta_liquida_id')
            ->pluck('cuenta_liquida_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function reglaExistsActiva(int $usuarioId): Exists
    {
        $bolsillos = self::idsBolsillosActivos($usuarioId);

        return \Illuminate\Validation\Rule::exists('cuentas_liquidas', 'id')
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->where(function ($query) use ($bolsillos): void {
                if ($bolsillos !== []) {
                    $query->whereNotIn('id', $bolsillos);
                }
            });
    }
}
