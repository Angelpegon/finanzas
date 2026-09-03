<?php

namespace App\Services;

use App\Models\HechoTesoreria;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use Illuminate\Support\Carbon;
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
            return $presupuesto->load('lineas.categoria');
        });
    }

    public function consumo(int $usuarioId, int $categoriaId, int $anio, int $mes): int
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        return (int) HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('categoria_id', $categoriaId)
            ->where('tipo', 'gasto')->whereBetween('fecha', [$inicio, $inicio->copy()->endOfMonth()])
            ->sum('monto_centavos');
    }

    public function proyeccion(int $usuarioId, int $categoriaId, int $anio, int $mes): int
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();

        return (int) \App\Models\Recurrencia::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('tipo', 'gasto')->where('categoria_id', $categoriaId)
            ->where('activa', true)->get()
            ->sum(function ($recurrencia) use ($inicio): int {
                return match ($recurrencia->periodicidad) {
                    'diario' => $recurrencia->monto_centavos * $inicio->daysInMonth,
                    'semanal' => $recurrencia->monto_centavos * 4,
                    'quincenal' => $recurrencia->monto_centavos * 2,
                    'mensual' => $recurrencia->monto_centavos,
                    'anual' => $inicio->month === Carbon::parse($recurrencia->created_at)->month ? $recurrencia->monto_centavos : 0,
                    default => 0,
                };
            });
    }
}
