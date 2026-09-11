<?php

namespace App\Support;

use App\Models\CuentaLiquida;
use App\Models\MetaAhorro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Exists;

/**
 * Cuentas líquidas operativas: nunca incluyen bolsillos de meta
 * (cualquier estado — el bolsillo no se “libera” a operativa).
 */
final class CuentasOperativas
{
    /** @return array<string, string> */
    public static function etiquetasTipo(): array
    {
        return [
            'bancaria' => 'Cuenta bancaria',
            'ahorros' => 'Cuenta de ahorros',
            'corriente' => 'Cuenta corriente',
            'efectivo' => 'Efectivo',
            'billetera' => 'Billetera digital',
            'otra' => 'Otra cuenta',
        ];
    }

    public static function etiquetaTipo(?string $tipo): string
    {
        if ($tipo === null || $tipo === '') {
            return 'Cuenta';
        }

        return self::etiquetasTipo()[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
    }

    /**
     * Todos los bolsillos ligados a una meta (activa, cumplida, etc.).
     *
     * @return list<int>
     */
    public static function idsBolsillosMetas(int $usuarioId): array
    {
        return MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereNotNull('cuenta_liquida_id')
            ->pluck('cuenta_liquida_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @deprecated Usa idsBolsillosMetas — el nombre histórico; ahora incluye todo bolsillo de meta.
     *
     * @return list<int>
     */
    public static function idsBolsillosActivos(int $usuarioId): array
    {
        return self::idsBolsillosMetas($usuarioId);
    }

    /**
     * Cuentas operativas visibles en UI (activas, no canceladas, no bolsillo).
     *
     * @return Builder<CuentaLiquida>
     */
    public static function queryActivas(int $usuarioId): Builder
    {
        $bolsillos = self::idsBolsillosMetas($usuarioId);

        return CuentaLiquida::query()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->where('estado', 'activa')
            ->when($bolsillos !== [], fn ($q) => $q->whereNotIn('id', $bolsillos));
    }

    /**
     * @return Builder<CuentaLiquida>
     */
    public static function queryArchivadas(int $usuarioId): Builder
    {
        $bolsillos = self::idsBolsillosMetas($usuarioId);

        return CuentaLiquida::query()
            ->where('usuario_id', $usuarioId)
            ->where('activa', false)
            ->where('estado', 'inactiva')
            ->when($bolsillos !== [], fn ($q) => $q->whereNotIn('id', $bolsillos));
    }

    public static function reglaExistsActiva(int $usuarioId): Exists
    {
        $bolsillos = self::idsBolsillosMetas($usuarioId);

        return \Illuminate\Validation\Rule::exists('cuentas_liquidas', 'id')
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->where('estado', 'activa')
            ->where(function ($query) use ($bolsillos): void {
                if ($bolsillos !== []) {
                    $query->whereNotIn('id', $bolsillos);
                }
            });
    }
}
