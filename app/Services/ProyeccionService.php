<?php

namespace App\Services;

use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\MetaAhorro;
use App\Models\Recurrencia;
use Illuminate\Support\Carbon;

class ProyeccionService
{
    public function horizonteMensual(int $usuarioId, ?Carbon $fecha = null): array
    {
        $fecha ??= now();
        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();
        $ingresos = \App\Support\AgregadosLibro::ingresosReales($usuarioId, $inicio, $fin);
        $gastos = \App\Support\AgregadosLibro::gastosReales($usuarioId, $inicio, $fin);
        $cuotasPrestamo = (int) CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('pagada', false)->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->get()->sum(fn (CuotaPrestamo $cuota): int => (int) $cuota->total_centavos);
        $cuotasTarjeta = (int) CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('pagada', false)->whereBetween('fecha_vencimiento', [$inicio, $fin])
            ->get()->sum(fn (CuotaTarjeta $cuota): int => (int) ($cuota->capital_centavos + $cuota->interes_centavos));
        $recurrenteIngreso = $this->montoMensualRecurrente($usuarioId, 'ingreso');
        $recurrenteGasto = $this->montoMensualRecurrente($usuarioId, 'gasto');
        $metas = (int) MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'activa')
            ->sum('aporte_mensual_centavos');

        return [
            'ingresos' => max($ingresos, $recurrenteIngreso),
            'gastos' => max($gastos, $recurrenteGasto),
            'cuotas' => $cuotasPrestamo + $cuotasTarjeta,
            'metas' => $metas,
            'capacidad_ahorro' => $ingresos + $recurrenteIngreso - $gastos - $recurrenteGasto - $cuotasPrestamo - $cuotasTarjeta - $metas,
        ];
    }

    /**
     * Devuelve únicamente salidas e ingresos esperados, sin mezclar hechos
     * ya registrados. Las cuotas pendientes representan la deuda programada;
     * los pagos futuros de obligaciones se excluyen para no duplicarla.
     */
    public function meses(int $usuarioId, ?Carbon $desde = null, int $cantidad = 6): array
    {
        $desde ??= now();
        $cantidad = max(1, min(12, $cantidad));
        $resultado = [];

        for ($indice = 0; $indice < $cantidad; $indice++) {
            $mes = $desde->copy()->startOfMonth()->addMonths($indice);
            $inicio = $mes->copy()->startOfMonth();
            $fin = $mes->copy()->endOfMonth();
            $ingresos = $this->montoMensualRecurrente($usuarioId, 'ingreso');
            $gastos = $this->montoMensualRecurrente($usuarioId, 'gasto');
            $deudas = (int) CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)
                ->where('pagada', false)->whereBetween('fecha_vencimiento', [$inicio, $fin])
                ->get()->sum(fn (CuotaPrestamo $cuota): int => $cuota->total_centavos)
                + (int) CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)
                    ->where('pagada', false)->whereBetween('fecha_vencimiento', [$inicio, $fin])
                    ->get()->sum(fn (CuotaTarjeta $cuota): int => $cuota->total_centavos);
            $pagos = 0;

            $resultado[] = [
                'periodo' => $mes->format('Y-m'),
                'etiqueta' => ucfirst($mes->locale('es')->monthName).' '.$mes->year,
                'ingresos_centavos' => $ingresos,
                'gastos_centavos' => $gastos,
                'deudas_centavos' => $deudas,
                'pagos_centavos' => $pagos,
                'disponible_centavos' => $ingresos - $gastos - $deudas,
                'estado' => 'proyectado',
            ];
        }

        return $resultado;
    }

    private function montoMensualRecurrente(int $usuarioId, string $tipo): int
    {
        return (int) Recurrencia::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('tipo', $tipo)->where('activa', true)->get()
            ->sum(function (Recurrencia $recurrencia): int {
                return match ($recurrencia->periodicidad) {
                    'diario' => $recurrencia->monto_centavos * 30,
                    'semanal' => $recurrencia->monto_centavos * 4,
                    'quincenal' => $recurrencia->monto_centavos * 2,
                    'anual' => intdiv($recurrencia->monto_centavos, 12),
                    'unico' => 0,
                    default => $recurrencia->monto_centavos,
                };
            });
    }
}
