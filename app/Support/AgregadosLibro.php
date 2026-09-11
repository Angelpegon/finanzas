<?php

namespace App\Support;

use App\Enums\TipoHechoTesoreria;
use App\Models\CompraTarjeta;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Services\PagoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AgregadosLibro
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function excluirOrigenesRevertidos(Builder $query, string $origenTipo, int $usuarioId): Builder
    {
        $clave = $query->getModel()->getQualifiedKeyName();

        return $query->whereNotIn($clave, function ($sub) use ($usuarioId, $origenTipo) {
            $sub->from('asientos as a')
                ->join('asientos as r', 'r.asiento_reversado_id', '=', 'a.id')
                ->where('a.usuario_id', $usuarioId)
                ->where('a.origen_tipo', $origenTipo)
                ->where('a.es_reverso', false)
                ->select('a.origen_id');
        });
    }

    public static function ingresosReales(int $usuarioId, Carbon $inicio, Carbon $fin): int
    {
        $q = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', TipoHechoTesoreria::Ingreso->value)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);

        return (int) self::excluirOrigenesRevertidos($q, HechoTesoreria::class, $usuarioId)->sum('monto_centavos');
    }

    public static function gastosReales(int $usuarioId, Carbon $inicio, Carbon $fin, ?int $categoriaId = null): int
    {
        $mapa = self::gastosRealesPorCategoria(
            $usuarioId,
            $inicio,
            $fin,
            $categoriaId !== null ? [$categoriaId] : null
        );

        if ($categoriaId !== null) {
            return (int) ($mapa[$categoriaId] ?? 0);
        }

        return (int) array_sum($mapa);
    }

    /**
     * Gastos reales agrupados por categoría (hechos + pagos genéricos + compras con categoría).
     * Una compra con tarjeta compromete el monto total en el mes de la compra.
     *
     * @param  list<int>|null  $categoriaIds  null = todas las categorías con movimiento
     * @return array<int, int> categoria_id => centavos
     */
    public static function gastosRealesPorCategoria(
        int $usuarioId,
        Carbon $inicio,
        Carbon $fin,
        ?array $categoriaIds = null
    ): array {
        $mapa = [];
        $sumar = function (int $categoriaId, int $monto) use (&$mapa): void {
            if ($categoriaId <= 0 || $monto === 0) {
                return;
            }
            $mapa[$categoriaId] = ($mapa[$categoriaId] ?? 0) + $monto;
        };

        $hechos = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', TipoHechoTesoreria::Gasto->value)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotNull('categoria_id');
        if ($categoriaIds !== null) {
            $hechos->whereIn('categoria_id', $categoriaIds === [] ? [0] : $categoriaIds);
        }
        foreach (self::excluirOrigenesRevertidos($hechos, HechoTesoreria::class, $usuarioId)
            ->selectRaw('categoria_id, SUM(monto_centavos) as total')
            ->groupBy('categoria_id')
            ->get() as $row) {
            $sumar((int) $row->categoria_id, (int) $row->total);
        }

        $pagos = Pago::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereIn('tipo', PagoService::TIPOS_GENERICOS)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotNull('categoria_id');
        if ($categoriaIds !== null) {
            $pagos->whereIn('categoria_id', $categoriaIds === [] ? [0] : $categoriaIds);
        }
        foreach (self::excluirOrigenesRevertidos($pagos, Pago::class, $usuarioId)
            ->selectRaw('categoria_id, SUM(monto_centavos) as total')
            ->groupBy('categoria_id')
            ->get() as $row) {
            $sumar((int) $row->categoria_id, (int) $row->total);
        }

        $compras = CompraTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotNull('categoria_id')
            ->where('anulada', false);
        if ($categoriaIds !== null) {
            $compras->whereIn('categoria_id', $categoriaIds === [] ? [0] : $categoriaIds);
        }
        foreach (self::excluirOrigenesRevertidos($compras, CompraTarjeta::class, $usuarioId)
            ->selectRaw('categoria_id, SUM(monto_centavos) as total')
            ->groupBy('categoria_id')
            ->get() as $row) {
            $sumar((int) $row->categoria_id, (int) $row->total);
        }

        if ($categoriaIds !== null) {
            foreach ($categoriaIds as $id) {
                $mapa[(int) $id] = (int) ($mapa[(int) $id] ?? 0);
            }
        }

        return $mapa;
    }
}
