<?php

namespace App\Services;

use App\Enums\NaturalezaCuenta;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Presupuesto;
use App\Models\Recurrencia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SituacionFinancieraService
{
    public function __construct(private readonly ProyeccionService $proyeccion) {}

    /**
     * El análisis distingue el saldo contable disponible en cuentas de las
     * salidas futuras. Los compromisos son cuotas pendientes y aportes de
     * metas; los gastos proyectados se muestran aparte para no presentarlos
     * como deuda ya contraída.
     */
    public function responder(int $usuarioId, ?Carbon $fecha = null): array
    {
        $fecha ??= now();
        $liquidez = (int) CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->get()->sum(fn (CuentaLiquida $cuenta) => $cuenta->saldoCentavos());
        $deuda = (int) CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('naturaleza', NaturalezaCuenta::Pasivo)->get()->sum(fn (CuentaContable $cuenta) => $cuenta->saldoCentavos());
        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();
        $proyeccion = $this->proyeccion->horizonteMensual($usuarioId, $fecha);
        $ingresosMes = (int) HechoTesoreria::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('tipo', 'ingreso')->whereBetween('fecha', [$inicio, $fin])->sum('monto_centavos');
        $gastosMes = (int) HechoTesoreria::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('tipo', 'gasto')->whereBetween('fecha', [$inicio, $fin])->sum('monto_centavos');
        $pagosDeudaMes = (int) Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->whereIn('tipo', ['prestamo', 'credito', 'tarjeta'])->whereBetween('fecha', [$inicio, $fin])->sum('monto_centavos');
        $cuotasProximas = CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(30)->endOfDay()])
            ->with('prestamo')->orderBy('fecha_vencimiento')->limit(10)->get();
        $cuotasTarjeta = CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(30)->endOfDay()])
            ->with(['compra.tarjetaCredito'])->orderBy('fecha_vencimiento')->limit(10)->get();
        $presupuesto = Presupuesto::with('lineas.categoria')->withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('anio', $fecha->year)->where('mes', $fecha->month)->first();
        $presupuestosAlertas = $presupuesto?->lineas->filter(fn ($linea) => $linea->porcentaje_consumido >= min($presupuesto->umbrales_alerta ?? [70]))->values() ?? collect();
        $ingresosEsperados = Recurrencia::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('tipo', 'ingreso')->where('activa', true)->orderBy('dia_del_mes')->limit(10)->get();
        $pagosProximos = $cuotasProximas->concat($cuotasTarjeta)->sortBy('fecha_vencimiento')->values();
        $flujo = $ingresosMes - $gastosMes - $pagosDeudaMes;
        $ingresosBase = max($ingresosMes, $proyeccion['ingresos']);
        $compromisos = $proyeccion['cuotas'] + $proyeccion['gastos'];
        $gastosProyectadosPendientes = max(0, $proyeccion['gastos'] - $gastosMes);
        $dineroComprometido = $proyeccion['cuotas'] + $proyeccion['metas'];
        $salidasPendientes = $dineroComprometido + $gastosProyectadosPendientes;
        $tasaAhorro = $ingresosMes > 0 ? round(($flujo / $ingresosMes) * 100, 1) : 0;
        $nivelEndeudamiento = $liquidez > 0
            ? round(($deuda / $liquidez) * 100, 1)
            : ($deuda > 0 ? 100 : 0);
        return [
            'tengo_centavos' => $liquidez,
            'saldo_cuentas_centavos' => $liquidez,
            'dinero_comprometido_centavos' => $dineroComprometido,
            'gastos_proyectados_pendientes_centavos' => $gastosProyectadosPendientes,
            'dinero_disponible_real_centavos' => $liquidez - $salidasPendientes,
            'debo_centavos' => $deuda,
            'ingresos_mes_centavos' => $ingresosMes,
            'gastos_mes_centavos' => $gastosMes,
            'pagos_deuda_mes_centavos' => $pagosDeudaMes,
            'ahorro_mes_centavos' => $flujo,
            'flujo_caja_centavos' => $flujo,
            'tasa_ahorro_porcentaje' => $tasaAhorro,
            'nivel_endeudamiento_porcentaje' => $nivelEndeudamiento,
            'nivel_endeudamiento_definicion' => 'Deuda total / saldo actual de cuentas × 100',
            'ingresos_comprometidos_porcentaje' => $ingresosBase > 0 ? round(($compromisos / $ingresosBase) * 100, 1) : 0,
            'ingresos_comprometidos_centavos' => $compromisos,
            'ingresos_comprometidos_definicion' => 'Cuotas pendientes y gastos proyectados / ingresos proyectados × 100',
            'recibire_centavos' => $proyeccion['ingresos'],
            'comprometido_centavos' => $dineroComprometido,
            'gastare_este_mes_centavos' => $proyeccion['gastos'] + $proyeccion['cuotas'],
            'proximos_pagos' => $pagosProximos,
            'proximos_vencimientos' => $pagosProximos,
            'ingresos_esperados' => $ingresosEsperados,
            'presupuestos_alertas' => $presupuestosAlertas,
            'evolucion_deuda' => $this->evolucionPasivo($usuarioId, $fecha),
            'evolucion_patrimonial' => $this->evolucionPatrimonial($usuarioId, $fecha),
            'intereses_mes_centavos' => (int) CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->whereBetween('fecha_vencimiento', [$inicio, $fin])->sum('interes_centavos') + (int) CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->whereBetween('fecha_vencimiento', [$inicio, $fin])->sum('interes_centavos'),
            'puede_asumir_deuda' => $proyeccion['capacidad_ahorro'] > 0,
            'capacidad_ahorro_centavos' => $proyeccion['capacidad_ahorro'],
            'proyeccion' => $proyeccion,
        ];
    }

    private function evolucionPasivo(int $usuarioId, Carbon $fecha): array
    {
        return $this->evolucionSaldos($usuarioId, $fecha, NaturalezaCuenta::Pasivo);
    }

    private function evolucionPatrimonial(int $usuarioId, Carbon $fecha): array
    {
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $fin = $fecha->copy()->subMonths($i)->endOfMonth();
            $activos = $this->saldoNaturalezaHasta($usuarioId, NaturalezaCuenta::Activo, $fin);
            $pasivos = $this->saldoNaturalezaHasta($usuarioId, NaturalezaCuenta::Pasivo, $fin);
            $meses[] = ['periodo' => $fin->format('m/Y'), 'centavos' => $activos - $pasivos];
        }
        return $meses;
    }

    private function evolucionSaldos(int $usuarioId, Carbon $fecha, NaturalezaCuenta $naturaleza): array
    {
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $fin = $fecha->copy()->subMonths($i)->endOfMonth();
            $meses[] = ['periodo' => $fin->format('m/Y'), 'centavos' => $this->saldoNaturalezaHasta($usuarioId, $naturaleza, $fin)];
        }
        return $meses;
    }

    private function saldoNaturalezaHasta(int $usuarioId, NaturalezaCuenta $naturaleza, Carbon $fecha): int
    {
        $cuentas = CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('naturaleza', $naturaleza)->get();
        return (int) $cuentas->sum(function (CuentaContable $cuenta) use ($fecha, $naturaleza): int {
            $movimientos = DB::table('movimientos')->join('asientos', 'asientos.id', '=', 'movimientos.asiento_id')
                ->where('movimientos.usuario_id', $cuenta->usuario_id)->where('movimientos.cuenta_contable_id', $cuenta->id)
                ->whereDate('asientos.fecha', '<=', $fecha->toDateString());
            $debe = (int) (clone $movimientos)->sum('movimientos.debe_centavos');
            $haber = (int) (clone $movimientos)->sum('movimientos.haber_centavos');
            return in_array($naturaleza, [NaturalezaCuenta::Activo, NaturalezaCuenta::Gasto], true) ? $debe - $haber : $haber - $debe;
        });
    }
}
