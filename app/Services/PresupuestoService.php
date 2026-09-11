<?php

namespace App\Services;

use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Recurrencia;
use App\Support\AgregadosLibro;
use App\Support\RecurrenciaMensual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PresupuestoService
{
    public function guardar(int $usuarioId, int $anio, int $mes, array $lineas, array $umbrales = [70, 80, 90, 100]): Presupuesto
    {
        $umbrales = collect($umbrales)->map(fn ($valor) => (int) $valor)->filter(fn (int $valor) => $valor > 0 && $valor <= 100)->unique()->sort()->values()->all();
        if ($mes < 1 || $mes > 12 || $lineas === [] || $umbrales === []) {
            throw new \InvalidArgumentException('El período o presupuesto no es válido.');
        }

        return DB::transaction(function () use ($usuarioId, $anio, $mes, $lineas, $umbrales): Presupuesto {
            $presupuesto = Presupuesto::withoutGlobalScopes()->updateOrCreate(
                ['usuario_id' => $usuarioId, 'anio' => $anio, 'mes' => $mes],
                ['umbrales_alerta' => $umbrales]
            );
            foreach ($lineas as $categoriaId => $tope) {
                if ((int) $tope < 0) {
                    throw new \InvalidArgumentException('El tope no puede ser negativo.');
                }
                PresupuestoLinea::withoutGlobalScopes()->updateOrCreate([
                    'usuario_id' => $usuarioId, 'presupuesto_id' => $presupuesto->id,
                    'categoria_id' => (int) $categoriaId,
                ], ['tope_centavos' => (int) $tope]);
            }
            PresupuestoLinea::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('presupuesto_id', $presupuesto->id)
                ->whereNotIn('categoria_id', array_map('intval', array_keys($lineas)))
                ->delete();

            return $presupuesto->load('lineas.categoria');
        });
    }

    public function consumo(int $usuarioId, int $categoriaId, int $anio, int $mes): int
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();

        return AgregadosLibro::gastosReales($usuarioId, $inicio, $inicio->copy()->endOfMonth(), $categoriaId);
    }

    public function proyeccion(int $usuarioId, int $categoriaId, int $anio, int $mes): int
    {
        $mapa = $this->proyeccionesPorCategoria($usuarioId, $anio, $mes, [$categoriaId]);

        return (int) ($mapa[$categoriaId] ?? 0);
    }

    /**
     * Adjunta consumo/proyección/%/alertas a las líneas en lote (evita N+1).
     */
    public function enriquecer(?Presupuesto $presupuesto): ?Presupuesto
    {
        if ($presupuesto === null) {
            return null;
        }

        $lineas = $presupuesto->relationLoaded('lineas')
            ? $presupuesto->lineas
            : $presupuesto->lineas()->with('categoria')->get();

        $ids = $lineas->pluck('categoria_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $inicio = Carbon::create((int) $presupuesto->anio, (int) $presupuesto->mes, 1)->startOfDay();
        $consumos = AgregadosLibro::gastosRealesPorCategoria(
            (int) $presupuesto->usuario_id,
            $inicio,
            $inicio->copy()->endOfMonth(),
            $ids
        );
        $proyecciones = $this->proyeccionesPorCategoria(
            (int) $presupuesto->usuario_id,
            (int) $presupuesto->anio,
            (int) $presupuesto->mes,
            $ids
        );
        $umbrales = collect($presupuesto->umbrales_alerta ?? [70, 80, 90, 100])
            ->map(fn ($u) => (int) $u)
            ->filter(fn (int $u) => $u > 0)
            ->values()
            ->all();

        foreach ($lineas as $linea) {
            $catId = (int) $linea->categoria_id;
            $real = (int) ($consumos[$catId] ?? 0);
            $proy = (int) ($proyecciones[$catId] ?? 0);
            $tope = (int) $linea->tope_centavos;
            $pct = $tope > 0 ? round(($real / $tope) * 100, 1) : 0.0;
            $alertas = collect($umbrales)->filter(fn (int $u) => $pct >= $u)->values()->all();

            $linea->setAttribute('gasto_real_centavos', $real);
            $linea->setAttribute('proyeccion_centavos', $proy);
            $linea->setAttribute('porcentaje_consumido', $pct);
            $linea->setAttribute('alertas', $alertas);
            $linea->setAttribute('recurrente_pendiente_centavos', max(0, $proy - $real));
            $linea->syncOriginal();
        }

        $presupuesto->setRelation('lineas', $lineas);

        return $presupuesto;
    }

    /**
     * @param  list<int>  $categoriaIds
     * @return array<int, int>
     */
    public function proyeccionesPorCategoria(int $usuarioId, int $anio, int $mes, array $categoriaIds): array
    {
        $mapa = [];
        foreach ($categoriaIds as $id) {
            $mapa[(int) $id] = 0;
        }
        if ($categoriaIds === []) {
            return $mapa;
        }

        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $recurrencias = Recurrencia::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', 'gasto')
            ->where('activa', true)
            ->whereIn('categoria_id', $categoriaIds)
            ->get();

        foreach ($recurrencias as $recurrencia) {
            $catId = (int) $recurrencia->categoria_id;
            $mapa[$catId] = ($mapa[$catId] ?? 0) + RecurrenciaMensual::montoBrutoEnMes($recurrencia, $inicio);
        }

        return $mapa;
    }
}
