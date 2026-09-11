<?php

namespace App\Services;

use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\MetaAhorro;
use App\Support\AgregadosLibro;
use App\Support\RecurrenciaMensual;
use Illuminate\Support\Carbon;

/**
 * Forecast de lectura (no escribe libro). Alineado a calendario en recurrencias
 * (unico/anual/cobertura) y carga cuotas vencidas arrastradas en el mes corriente.
 */
class ProyeccionService
{
    public function horizonteMensual(int $usuarioId, ?Carbon $fecha = null): array
    {
        $fecha ??= now();
        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();

        $ingresos = AgregadosLibro::ingresosReales($usuarioId, $inicio, $fin);
        $gastos = AgregadosLibro::gastosReales($usuarioId, $inicio, $fin);
        $ingresosPendientes = RecurrenciaMensual::sumaPendiente($usuarioId, 'ingreso', $inicio);
        $gastosPendientes = RecurrenciaMensual::sumaPendiente($usuarioId, 'gasto', $inicio);
        $cuotas = $this->cuotasComprometidasHasta($usuarioId, $fin);
        $metas = app(MetaAhorroService::class)->comprometidoMensualNeto($usuarioId, $fecha, true);

        $ingresosEfectivos = $ingresos + $ingresosPendientes;
        $gastosEfectivos = $gastos + $gastosPendientes;

        return [
            'ingresos' => $ingresosEfectivos,
            'gastos' => $gastosEfectivos,
            'cuotas' => $cuotas,
            'metas' => $metas,
            'capacidad_ahorro' => $ingresosEfectivos - $gastosEfectivos - $cuotas - $metas,
        ];
    }

    /**
     * Meses futuros/corriente: ingresos y gastos esperados (reales del mes +
     * recurrencia pendiente). Deudas = cuotas del mes; en el mes 0 también
     * arrastra vencidas. Residual = flujo del mes sin saldo inicial de caja.
     */
    public function meses(int $usuarioId, ?Carbon $desde = null, int $cantidad = 6): array
    {
        $desde ??= now();
        $cantidad = max(1, min(12, $cantidad));
        $resultado = [];
        $faltanteMetas = $this->faltanteMetasActivas($usuarioId);

        for ($indice = 0; $indice < $cantidad; $indice++) {
            $mes = $desde->copy()->startOfMonth()->addMonths($indice);
            $inicio = $mes->copy()->startOfMonth();
            $fin = $mes->copy()->endOfMonth();

            if ($indice === 0) {
                $ingresosReales = AgregadosLibro::ingresosReales($usuarioId, $inicio, $fin);
                $gastosReales = AgregadosLibro::gastosReales($usuarioId, $inicio, $fin);
                $ingresos = $ingresosReales + RecurrenciaMensual::sumaPendiente($usuarioId, 'ingreso', $inicio);
                $gastos = $gastosReales + RecurrenciaMensual::sumaPendiente($usuarioId, 'gasto', $inicio);
                $deudas = $this->cuotasComprometidasHasta($usuarioId, $fin);
            } else {
                $ingresos = RecurrenciaMensual::sumaBruta($usuarioId, 'ingreso', $inicio);
                $gastos = RecurrenciaMensual::sumaBruta($usuarioId, 'gasto', $inicio);
                $deudas = $this->cuotasEnMes($usuarioId, $inicio, $fin);
            }

            $planMetas = app(MetaAhorroService::class)->comprometidoMensualNeto($usuarioId, $mes, false);
            $metas = min($planMetas, $faltanteMetas);
            $faltanteMetas = max(0, $faltanteMetas - $metas);

            $resultado[] = [
                'periodo' => $mes->format('Y-m'),
                'etiqueta' => ucfirst($mes->locale('es')->monthName).' '.$mes->year,
                'ingresos_centavos' => $ingresos,
                'gastos_centavos' => $gastos,
                'deudas_centavos' => $deudas,
                'metas_centavos' => $metas,
                'residual_centavos' => $ingresos - $gastos - $deudas - $metas,
                'estado' => 'proyectado',
            ];
        }

        return $resultado;
    }

    /** Cuotas del mes + vencidas impagas con fecha ≤ fin (compromiso de caja actual). */
    private function cuotasComprometidasHasta(int $usuarioId, Carbon $fin): int
    {
        $limite = $fin->toDateString();

        $prestamo = (int) CuotaPrestamo::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('pagada', false)
            ->whereDate('fecha_vencimiento', '<=', $limite)
            ->get()
            ->sum(fn (CuotaPrestamo $c): int => (int) $c->total_centavos);

        $tarjeta = (int) CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('pagada', false)
            ->whereDate('fecha_vencimiento', '<=', $limite)
            ->get()
            ->sum(fn (CuotaTarjeta $c): int => (int) $c->total_centavos);

        return $prestamo + $tarjeta;
    }

    private function cuotasEnMes(int $usuarioId, Carbon $inicio, Carbon $fin): int
    {
        $prestamo = (int) CuotaPrestamo::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$inicio->toDateString(), $fin->toDateString()])
            ->get()
            ->sum(fn (CuotaPrestamo $c): int => (int) $c->total_centavos);

        $tarjeta = (int) CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$inicio->toDateString(), $fin->toDateString()])
            ->get()
            ->sum(fn (CuotaTarjeta $c): int => (int) $c->total_centavos);

        return $prestamo + $tarjeta;
    }

    private function faltanteMetasActivas(int $usuarioId): int
    {
        return (int) MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'activa')
            ->get()
            ->sum(fn (MetaAhorro $m): int => max(0, (int) $m->objetivo_centavos - (int) $m->monto_actual_centavos));
    }
}
