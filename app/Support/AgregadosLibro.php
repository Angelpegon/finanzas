<?php

namespace App\Support;

use App\Enums\TipoHechoTesoreria;
use App\Models\CompraTarjeta;
use App\Models\HechoTesoreria;
use App\Models\Pago;
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
        $hechos = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', TipoHechoTesoreria::Gasto->value)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        if ($categoriaId !== null) {
            $hechos->where('categoria_id', $categoriaId);
        }
        $total = (int) self::excluirOrigenesRevertidos($hechos, HechoTesoreria::class, $usuarioId)->sum('monto_centavos');

        $pagos = Pago::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereIn('tipo', ['gasto', 'servicio', 'deuda_personal', 'otra_obligacion'])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        if ($categoriaId !== null) {
            $pagos->where('categoria_id', $categoriaId);
        }
        $total += (int) self::excluirOrigenesRevertidos($pagos, Pago::class, $usuarioId)->sum('monto_centavos');

        $compras = CompraTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        if ($categoriaId !== null) {
            $compras->where('categoria_id', $categoriaId);
        } else {
            $compras->whereNotNull('categoria_id');
        }
        $total += (int) self::excluirOrigenesRevertidos($compras, CompraTarjeta::class, $usuarioId)->sum('monto_centavos');

        return $total;
    }
}
