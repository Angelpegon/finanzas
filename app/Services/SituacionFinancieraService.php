<?php

namespace App\Services;

use App\Enums\NaturalezaCuenta;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\Presupuesto;
use App\Models\TarjetaCredito;
use App\Support\CuentasOperativas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SituacionFinancieraService
{
    private const CACHE_SHELL_TTL_SEGUNDOS = 45;

    public function __construct(
        private readonly ProyeccionService $proyeccion,
        private readonly CalendarioFinancieroService $calendario,
    ) {}

    public static function claveCacheShell(int $usuarioId, ?Carbon $fecha = null): string
    {
        $fecha ??= now();

        return 'situacion.shell.'.$usuarioId.'.'.$fecha->format('Y-m');
    }

    public static function olvidarResumenShell(int $usuarioId): void
    {
        // Mes actual + clave legada sin mes (por si quedó algo en caché antigua).
        Cache::forget(self::claveCacheShell($usuarioId));
        Cache::forget('situacion.shell.'.$usuarioId);
    }

    /**
     * Distingue patrimonio líquido (todas las cuentas) del cash libre.
     * Disponible = liquidez sin bolsillos de meta − cuotas − aportes
     * planificados netos − gastos proyectados pendientes. El dinero ya
     * etiquetado en una meta no es libre para gastar.
     *
     * @param  bool  $dashboard  Widgets densos solo para Situación (no el composer global).
     */
    public function responder(int $usuarioId, ?Carbon $fecha = null, bool $dashboard = false): array
    {
        $fecha ??= now();
        $bolsilloIds = CuentasOperativas::idsBolsillosActivos($usuarioId);
        $cuentas = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->get();
        $saldos = CuentaContable::saldosCentavosMap(
            $usuarioId,
            $cuentas->pluck('cuenta_contable_id')->map(fn ($id) => (int) $id)->all()
        );
        $saldoDe = fn (CuentaLiquida $cuenta): int => (int) ($saldos[(int) $cuenta->cuenta_contable_id] ?? 0);
        $liquidez = (int) $cuentas->sum($saldoDe);
        $reservadoMetas = (int) $cuentas
            ->whereIn('id', $bolsilloIds)
            ->sum($saldoDe);
        $liquidezLibre = $liquidez - $reservadoMetas;

        $pasivos = CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('naturaleza', NaturalezaCuenta::Pasivo)->get();
        $saldosPasivo = CuentaContable::saldosCentavosMap(
            $usuarioId,
            $pasivos->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $deuda = (int) $pasivos->sum(fn (CuentaContable $cuenta) => (int) ($saldosPasivo[(int) $cuenta->id] ?? 0));

        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();
        $proyeccion = $this->proyeccion->horizonteMensual($usuarioId, $fecha);
        $ingresosMes = \App\Support\AgregadosLibro::ingresosReales($usuarioId, $inicio, $fin);
        $gastosMes = \App\Support\AgregadosLibro::gastosReales($usuarioId, $inicio, $fin);
        $pagosDeudaMes = (int) Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->whereIn('tipo', ['prestamo', 'credito', 'tarjeta'])->whereBetween('fecha', [$inicio, $fin])->sum('monto_centavos');

        $cuotasProximas = CuotaPrestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(30)->endOfDay()])
            ->with('prestamo')->orderBy('fecha_vencimiento')->limit(10)->get();
        $cuotasTarjeta = CuotaTarjeta::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('pagada', false)
            ->whereBetween('fecha_vencimiento', [$fecha->copy()->startOfDay(), $fecha->copy()->addDays(30)->endOfDay()])
            ->with(['compra.tarjetaCredito'])->orderBy('fecha_vencimiento')->limit(10)->get();
        $pagosProximos = $cuotasProximas->concat($cuotasTarjeta)->sortBy('fecha_vencimiento')->values();

        $presupuesto = Presupuesto::with('lineas.categoria')->withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('anio', $fecha->year)->where('mes', $fecha->month)->first();
        app(PresupuestoService::class)->enriquecer($presupuesto);

        $flujo = $ingresosMes - $gastosMes - $pagosDeudaMes;
        $gastosProyectadosPendientes = max(0, $proyeccion['gastos'] - $gastosMes);
        $dineroComprometido = $proyeccion['cuotas'] + $proyeccion['metas'];
        $disponible = $liquidezLibre - $dineroComprometido - $gastosProyectadosPendientes;
        $nivelEndeudamiento = $liquidez > 0
            ? round(($deuda / $liquidez) * 100, 1)
            : ($deuda > 0 ? 100 : 0);

        $payload = [
            'tengo_centavos' => $liquidez,
            'saldo_cuentas_centavos' => $liquidez,
            'reservado_metas_centavos' => $reservadoMetas,
            'dinero_comprometido_centavos' => $dineroComprometido,
            'gastos_proyectados_pendientes_centavos' => $gastosProyectadosPendientes,
            'dinero_disponible_real_centavos' => $disponible,
            'debo_centavos' => $deuda,
            'ingresos_mes_centavos' => $ingresosMes,
            'gastos_mes_centavos' => $gastosMes,
            'pagos_deuda_mes_centavos' => $pagosDeudaMes,
            'flujo_caja_centavos' => $flujo,
            'nivel_endeudamiento_porcentaje' => $nivelEndeudamiento,
            'proximos_vencimientos' => $pagosProximos,
            'periodo_etiqueta' => ucfirst($fecha->copy()->locale('es')->monthName).' '.$fecha->year,
        ];

        if ($dashboard) {
            $payload = array_merge($payload, $this->widgetsDashboard(
                $usuarioId,
                $fecha,
                $presupuesto,
                $disponible,
                $ingresosMes,
                $gastosMes,
                $pagosDeudaMes,
                $bolsilloIds,
            ));
        }

        return $payload;
    }

    /**
     * Payload liviano para el shell (sidebar/header + alertas) en páginas
     * que no son el dashboard. Siempre “hoy” (mes corriente). Cache Laravel
     * con invalidación en Contabilizacion/Metas/Cuentas — sin static de proceso.
     *
     * @return array{
     *     dinero_disponible_real_centavos: int,
     *     flujo_caja_centavos: int,
     *     nivel_endeudamiento_porcentaje: float|int,
     *     alertas: list<array{nivel: string, titulo: string, mensaje: string, enlace?: ?string}>
     * }
     */
    public function resumenShell(int $usuarioId, ?Carbon $fecha = null): array
    {
        $fecha ??= now();
        $clave = self::claveCacheShell($usuarioId, $fecha);

        return Cache::remember(
            $clave,
            self::CACHE_SHELL_TTL_SEGUNDOS,
            fn (): array => $this->calcularResumenShell($usuarioId, $fecha)
        );
    }

    /**
     * @return array{
     *     dinero_disponible_real_centavos: int,
     *     flujo_caja_centavos: int,
     *     nivel_endeudamiento_porcentaje: float|int,
     *     alertas: list<array{nivel: string, titulo: string, mensaje: string, enlace?: ?string}>
     * }
     */
    private function calcularResumenShell(int $usuarioId, Carbon $fecha): array
    {
        $bolsilloIds = CuentasOperativas::idsBolsillosActivos($usuarioId);
        $cuentas = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->get();
        $saldos = CuentaContable::saldosCentavosMap(
            $usuarioId,
            $cuentas->pluck('cuenta_contable_id')->map(fn ($id) => (int) $id)->all()
        );
        $saldoDe = fn (CuentaLiquida $cuenta): int => (int) ($saldos[(int) $cuenta->cuenta_contable_id] ?? 0);
        $liquidez = (int) $cuentas->sum($saldoDe);
        $reservadoMetas = (int) $cuentas->whereIn('id', $bolsilloIds)->sum($saldoDe);
        $liquidezLibre = $liquidez - $reservadoMetas;

        $pasivos = CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('naturaleza', NaturalezaCuenta::Pasivo)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $saldosPasivo = CuentaContable::saldosCentavosMap($usuarioId, $pasivos);
        $deuda = (int) array_sum($saldosPasivo);

        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();
        $proyeccion = $this->proyeccion->horizonteMensual($usuarioId, $fecha);
        $ingresosMes = \App\Support\AgregadosLibro::ingresosReales($usuarioId, $inicio, $fin);
        $gastosMes = \App\Support\AgregadosLibro::gastosReales($usuarioId, $inicio, $fin);
        $pagosDeudaMes = (int) Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->whereIn('tipo', ['prestamo', 'credito', 'tarjeta'])->whereBetween('fecha', [$inicio, $fin])->sum('monto_centavos');
        $flujo = $ingresosMes - $gastosMes - $pagosDeudaMes;
        $gastosProyectadosPendientes = max(0, $proyeccion['gastos'] - $gastosMes);
        $dineroComprometido = $proyeccion['cuotas'] + $proyeccion['metas'];
        $disponible = $liquidezLibre - $dineroComprometido - $gastosProyectadosPendientes;
        $nivelEndeudamiento = $liquidez > 0
            ? round(($deuda / $liquidez) * 100, 1)
            : ($deuda > 0 ? 100 : 0);

        $payload = [
            'dinero_disponible_real_centavos' => $disponible,
            'flujo_caja_centavos' => $flujo,
            'nivel_endeudamiento_porcentaje' => $nivelEndeudamiento,
        ];
        $payload['alertas'] = app(AlertaService::class)->evaluar($usuarioId, $payload, $fecha);

        return $payload;
    }

    /**
     * @param  list<int>  $bolsilloIds
     */
    private function widgetsDashboard(
        int $usuarioId,
        Carbon $fecha,
        ?Presupuesto $presupuesto,
        int $disponible,
        int $ingresosMes,
        int $gastosMes,
        int $pagosDeudaMes,
        array $bolsilloIds,
    ): array {
        $anterior = $fecha->copy()->subMonth();
        $inicioAnt = $anterior->copy()->startOfMonth();
        $finAnt = $anterior->copy()->endOfMonth();
        $ingresosAnt = \App\Support\AgregadosLibro::ingresosReales($usuarioId, $inicioAnt, $finAnt);
        $gastosAnt = \App\Support\AgregadosLibro::gastosReales($usuarioId, $inicioAnt, $finAnt);
        $pagosDeudaAnt = (int) Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->whereIn('tipo', ['prestamo', 'credito', 'tarjeta'])->whereBetween('fecha', [$inicioAnt, $finAnt])->sum('monto_centavos');
        $disponibleAnt = $this->disponibleEn($usuarioId, $anterior);

        $cuentas = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->where('estado', '!=', 'cancelada')
            ->when($bolsilloIds !== [], fn ($q) => $q->whereNotIn('id', $bolsilloIds))
            ->orderBy('nombre')
            ->get();
        $saldosCuentas = CuentaContable::saldosCentavosMap(
            $usuarioId,
            $cuentas->pluck('cuenta_contable_id')->map(fn ($id) => (int) $id)->all()
        );
        $cuentas = $cuentas->map(fn (CuentaLiquida $cuenta) => [
            'id' => $cuenta->id,
            'nombre' => $cuenta->nombre,
            'tipo' => $cuenta->tipo,
            'institucion' => $cuenta->institucion,
            'saldo_centavos' => (int) ($saldosCuentas[(int) $cuenta->cuenta_contable_id] ?? 0),
        ])->values();

        $eventosMes = $this->calendario->mensual($usuarioId, $fecha->year, $fecha->month);
        $calendarioGrilla = $this->calendario->grillaMensual($fecha, $eventosMes);

        $movimientos = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereIn('tipo', ['ingreso', 'gasto', 'transferencia', 'aporte_meta'])
            ->with('categoria')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (HechoTesoreria $hecho) => [
                'id' => $hecho->id,
                'tipo' => $hecho->tipo->value,
                'descripcion' => $hecho->descripcion ?: ucfirst(str_replace('_', ' ', $hecho->tipo->value)),
                'categoria' => $hecho->categoria?->nombre,
                'fecha' => $hecho->fecha?->toDateString(),
                'monto_centavos' => (int) $hecho->monto_centavos,
                'signo' => match ($hecho->tipo->value) {
                    'ingreso' => 1,
                    'transferencia', 'aporte_meta' => 0,
                    default => -1,
                },
            ])->values();

        return [
            'variaciones' => [
                'ingresos_porcentaje' => $this->variacionPorcentaje($ingresosMes, $ingresosAnt),
                'gastos_porcentaje' => $this->variacionPorcentaje($gastosMes, $gastosAnt),
                'pagos_deuda_porcentaje' => $this->variacionPorcentaje($pagosDeudaMes, $pagosDeudaAnt),
                'disponible_porcentaje' => $this->variacionPorcentaje($disponible, $disponibleAnt),
            ],
            'cuentas' => $cuentas,
            'total_cuentas_centavos' => (int) $cuentas->sum('saldo_centavos'),
            'presupuesto_lineas' => $presupuesto?->lineas?->values() ?? collect(),
            'deudas' => $this->listadoDeudas($usuarioId),
            'calendario_grilla' => $calendarioGrilla,
            'movimientos_recientes' => $movimientos,
        ];
    }

    /**
     * Disponible real al cierre del mes de referencia (liquidez libre histórica − salidas pendientes).
     */
    private function disponibleEn(int $usuarioId, Carbon $fecha): int
    {
        $fin = $fecha->copy()->endOfMonth();
        $inicio = $fecha->copy()->startOfMonth();
        $liquidezLibre = $this->liquidezLibreHasta($usuarioId, $fin);
        $proyeccion = $this->proyeccion->horizonteMensual($usuarioId, $fecha);
        $gastosMes = \App\Support\AgregadosLibro::gastosReales($usuarioId, $inicio, $fin);
        $gastosProyectadosPendientes = max(0, $proyeccion['gastos'] - $gastosMes);
        $dineroComprometido = $proyeccion['cuotas'] + $proyeccion['metas'];

        return $liquidezLibre - $dineroComprometido - $gastosProyectadosPendientes;
    }

    private function liquidezLibreHasta(int $usuarioId, Carbon $fin): int
    {
        $bolsilloIds = CuentasOperativas::idsBolsillosActivos($usuarioId);
        $cuentas = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->when($bolsilloIds !== [], fn ($q) => $q->whereNotIn('id', $bolsilloIds))
            ->get();

        if ($cuentas->isEmpty()) {
            return 0;
        }

        $saldos = CuentaContable::saldosCentavosMap(
            $usuarioId,
            $cuentas->pluck('cuenta_contable_id')->map(fn ($id) => (int) $id)->all(),
            $fin->toDateString()
        );

        return (int) array_sum($saldos);
    }

    private function variacionPorcentaje(int $actual, int $anterior): ?float
    {
        if ($anterior === 0) {
            return $actual === 0 ? 0.0 : null;
        }

        return round((($actual - $anterior) / abs($anterior)) * 100, 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listadoDeudas(int $usuarioId): array
    {
        $items = [];

        $prestamos = Prestamo::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->with(['cuotas' => fn ($q) => $q->orderBy('numero')])
            ->get();

        foreach ($prestamos as $prestamo) {
            $cuotas = $prestamo->cuotas;
            $pendientes = $cuotas->where('pagada', false);
            $saldo = (int) $pendientes->sum('capital_centavos');
            if ($saldo <= 0) {
                continue;
            }
            $proxima = $pendientes->sortBy('fecha_vencimiento')->first();
            $principal = (int) $prestamo->principal_centavos;
            $pagadas = $cuotas->where('pagada', true)->count();
            $total = $cuotas->count();
            $avance = $principal > 0 ? round((($principal - $saldo) / $principal) * 100, 1) : 0;

            $estado = $prestamo->getAttributes()['estado'] ?? 'activa';
            if ($pendientes->where('fecha_vencimiento', '<', now()->toDateString())->isNotEmpty()) {
                $estado = 'vencida';
            }

            $items[] = [
                'tipo' => 'prestamo',
                'id' => $prestamo->id,
                'nombre' => $prestamo->nombre,
                'estado' => $estado,
                'saldo_centavos' => $saldo,
                'principal_centavos' => $principal,
                'avance_porcentaje' => $avance,
                'cuota_centavos' => (int) $prestamo->cuota_centavos,
                'ea_porcentaje' => (float) $prestamo->ea_porcentaje,
                'cuotas_pagadas' => $pagadas,
                'cuotas_total' => $total,
                'proximo_pago' => $proxima?->fecha_vencimiento?->toDateString(),
                'proximo_monto_centavos' => $proxima ? (int) $proxima->total_centavos : null,
            ];
        }

        $tarjetas = TarjetaCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->with(['compras.cuotasProgramadas'])
            ->get();

        foreach ($tarjetas as $tarjeta) {
            $compras = $tarjeta->compras;
            $pagosCapital = (int) Pago::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $tarjeta->id)
                ->sum('capital_centavos');
            $saldo = (int) $compras->sum('monto_centavos') - $pagosCapital;
            if ($saldo <= 0) {
                continue;
            }

            $cuotasPendientes = $compras->flatMap(fn ($c) => $c->cuotasProgramadas->where('pagada', false));
            $proxima = $cuotasPendientes->sortBy('fecha_vencimiento')->first();
            $cupo = (int) $tarjeta->cupo_centavos;

            $items[] = [
                'tipo' => 'tarjeta',
                'id' => $tarjeta->id,
                'nombre' => $tarjeta->nombre,
                'estado' => 'activa',
                'saldo_centavos' => $saldo,
                'principal_centavos' => $cupo,
                'avance_porcentaje' => $cupo > 0 ? round((1 - ($saldo / $cupo)) * 100, 1) : 0,
                'cuota_centavos' => $proxima
                    ? ((int) $proxima->capital_centavos + (int) $proxima->interes_centavos)
                    : 0,
                'ea_porcentaje' => (float) $tarjeta->ea_porcentaje,
                'cuotas_pagadas' => null,
                'cuotas_total' => null,
                'proximo_pago' => $proxima?->fecha_vencimiento?->toDateString(),
                'proximo_monto_centavos' => $proxima
                    ? ((int) $proxima->capital_centavos + (int) $proxima->interes_centavos)
                    : null,
            ];
        }

        return collect($items)->sortByDesc('saldo_centavos')->values()->all();
    }
}
