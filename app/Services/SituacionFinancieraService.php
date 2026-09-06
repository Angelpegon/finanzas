<?php

namespace App\Services;

use App\Enums\NaturalezaCuenta;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\HechoTesoreria;
use App\Models\MetaAhorro;
use App\Models\MovimientoLibro;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\Presupuesto;
use App\Models\Recurrencia;
use App\Models\TarjetaCredito;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SituacionFinancieraService
{
    public function __construct(
        private readonly ProyeccionService $proyeccion,
        private readonly CalendarioFinancieroService $calendario,
    ) {}

    /**
     * Distingue patrimonio líquido (todas las cuentas) del cash libre.
     * Disponible = liquidez sin bolsillos de metas activas − cuotas − aportes
     * planificados − gastos proyectados pendientes. El dinero ya etiquetado
     * en una meta no es libre para gastar.
     *
     * @param  bool  $dashboard  Widgets densos solo para Situación (no el composer global).
     */
    public function responder(int $usuarioId, ?Carbon $fecha = null, bool $dashboard = false): array
    {
        $fecha ??= now();
        $cuentas = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->get();
        $bolsilloIds = $this->idsBolsillosMetaActiva($usuarioId);
        $liquidez = (int) $cuentas->sum(fn (CuentaLiquida $cuenta) => $cuenta->saldoCentavos());
        $reservadoMetas = (int) $cuentas
            ->whereIn('id', $bolsilloIds)
            ->sum(fn (CuentaLiquida $cuenta) => $cuenta->saldoCentavos());
        $liquidezLibre = $liquidez - $reservadoMetas;
        $deuda = (int) CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('naturaleza', NaturalezaCuenta::Pasivo)->get()->sum(fn (CuentaContable $cuenta) => $cuenta->saldoCentavos());
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
        $disponible = $liquidezLibre - $salidasPendientes;
        $tasaAhorro = $ingresosMes > 0 ? round(($flujo / $ingresosMes) * 100, 1) : 0;
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
            'intereses_mes_centavos' => $this->interesesPosteadosMes($usuarioId, $inicio, $fin),
            'deuda_mayor_costo' => $this->deudaMayorCosto($usuarioId),
            'cuando_termino' => $this->cuandoTermino($usuarioId),
            'puede_asumir_deuda' => $proyeccion['capacidad_ahorro'] > 0,
            'capacidad_ahorro_centavos' => $proyeccion['capacidad_ahorro'],
            'proyeccion' => $proyeccion,
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
            ));
        }

        return $payload;
    }

    private function widgetsDashboard(
        int $usuarioId,
        Carbon $fecha,
        ?Presupuesto $presupuesto,
        int $disponible,
        int $ingresosMes,
        int $gastosMes,
        int $pagosDeudaMes,
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
            ->orderBy('nombre')
            ->get()
            ->map(fn (CuentaLiquida $cuenta) => [
                'id' => $cuenta->id,
                'nombre' => $cuenta->nombre,
                'tipo' => $cuenta->tipo,
                'institucion' => $cuenta->institucion,
                'saldo_centavos' => $cuenta->saldoCentavos(),
            ])->values();

        $metas = MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->orderByDesc('prioridad')
            ->orderBy('nombre')
            ->limit(6)
            ->get();

        $eventosMes = $this->calendario->mensual($usuarioId, $fecha->year, $fecha->month);
        $porDia = collect($eventosMes)->groupBy('fecha');
        $calendarioGrilla = $this->construirGrillaCalendario($fecha, $porDia);

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
            'metas' => $metas,
            'detalle_deuda' => $this->detalleDeudaDestacada($usuarioId),
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

    /**
     * @return list<int>
     */
    private function idsBolsillosMetaActiva(int $usuarioId): array
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

    private function liquidezLibreHasta(int $usuarioId, Carbon $fin): int
    {
        $bolsilloIds = $this->idsBolsillosMetaActiva($usuarioId);
        $cuentas = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->when($bolsilloIds !== [], fn ($q) => $q->whereNotIn('id', $bolsilloIds))
            ->get();

        return (int) $cuentas->sum(function (CuentaLiquida $cuenta) use ($fin): int {
            $movimientos = DB::table('movimientos')->join('asientos', 'asientos.id', '=', 'movimientos.asiento_id')
                ->where('movimientos.usuario_id', $cuenta->usuario_id)
                ->where('movimientos.cuenta_contable_id', $cuenta->cuenta_contable_id)
                ->whereDate('asientos.fecha', '<=', $fin->toDateString());
            $debe = (int) (clone $movimientos)->sum('movimientos.debe_centavos');
            $haber = (int) (clone $movimientos)->sum('movimientos.haber_centavos');

            return $debe - $haber;
        });
    }

    private function variacionPorcentaje(int $actual, int $anterior): ?float
    {
        if ($anterior === 0) {
            return $actual === 0 ? 0.0 : null;
        }

        return round((($actual - $anterior) / abs($anterior)) * 100, 1);
    }

    private function construirGrillaCalendario(Carbon $fecha, $porDia): array
    {
        $inicioMes = $fecha->copy()->startOfMonth();
        $diasEnMes = $inicioMes->daysInMonth;
        // Carbon: 0 = domingo … 6 = sábado; grilla Lun–Dom
        $offset = ($inicioMes->dayOfWeek + 6) % 7;
        $celdas = [];
        for ($i = 0; $i < $offset; $i++) {
            $celdas[] = null;
        }
        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fechaDia = $inicioMes->copy()->day($dia)->toDateString();
            $eventos = ($porDia[$fechaDia] ?? collect())->values()->all();
            $celdas[] = [
                'dia' => $dia,
                'fecha' => $fechaDia,
                'hoy' => $fechaDia === now()->toDateString(),
                'eventos' => $eventos,
                'monto_centavos' => (int) collect($eventos)->sum('monto_centavos'),
                'tiene_pago' => collect($eventos)->contains(fn ($e) => in_array($e['tipo'], ['cuota', 'pago', 'limite_tarjeta', 'gasto'], true)),
                'tiene_ingreso' => collect($eventos)->contains(fn ($e) => $e['tipo'] === 'ingreso'),
            ];
        }
        while (count($celdas) % 7 !== 0) {
            $celdas[] = null;
        }

        return [
            'anio' => $fecha->year,
            'mes' => $fecha->month,
            'etiqueta' => ucfirst($fecha->copy()->locale('es')->monthName).' '.$fecha->year,
            'celdas' => $celdas,
        ];
    }

    private function detalleDeudaDestacada(int $usuarioId): ?array
    {
        $prestamos = Prestamo::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', '!=', 'cancelada')
            ->get()
            ->filter(fn (Prestamo $p) => $p->saldo_actual_centavos > 0)
            ->sortByDesc(fn (Prestamo $p) => $p->saldo_actual_centavos)
            ->values();

        $prestamo = $prestamos->first();
        if ($prestamo) {
            $proxima = $prestamo->cuotas()->where('pagada', false)->orderBy('fecha_vencimiento')->first();
            $pagadas = $prestamo->cuotas_pagadas;
            $total = $prestamo->cuotas()->count();
            $principal = (int) $prestamo->principal_centavos;
            $saldo = $prestamo->saldo_actual_centavos;
            $avance = $principal > 0 ? round((($principal - $saldo) / $principal) * 100, 1) : 0;

            return [
                'tipo' => 'prestamo',
                'id' => $prestamo->id,
                'nombre' => $prestamo->nombre,
                'estado' => $prestamo->estado,
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

        $tarjeta = TarjetaCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->get()
            ->sortByDesc(fn (TarjetaCredito $t) => $t->saldo_actual_centavos)
            ->first();

        if (! $tarjeta || $tarjeta->saldo_actual_centavos <= 0) {
            return null;
        }

        $proxima = CuotaTarjeta::withoutGlobalScopes()
            ->where('tarjeta_credito_id', $tarjeta->id)
            ->where('pagada', false)
            ->orderBy('fecha_vencimiento')
            ->first();

        return [
            'tipo' => 'tarjeta',
            'id' => $tarjeta->id,
            'nombre' => $tarjeta->nombre,
            'estado' => 'activa',
            'saldo_centavos' => (int) $tarjeta->saldo_actual_centavos,
            'principal_centavos' => (int) $tarjeta->cupo_centavos,
            'avance_porcentaje' => $tarjeta->cupo_centavos > 0
                ? round((1 - ($tarjeta->saldo_actual_centavos / $tarjeta->cupo_centavos)) * 100, 1)
                : 0,
            'cuota_centavos' => $proxima ? (int) $proxima->total_centavos : (int) $tarjeta->pago_minimo_centavos,
            'ea_porcentaje' => (float) $tarjeta->ea_porcentaje,
            'cuotas_pagadas' => null,
            'cuotas_total' => null,
            'proximo_pago' => $proxima?->fecha_vencimiento?->toDateString(),
            'proximo_monto_centavos' => $proxima ? (int) $proxima->total_centavos : (int) $tarjeta->pago_minimo_centavos,
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

    /** Intereses reales = movimientos posteados a 5200 en el periodo (libro, no calendario). */
    private function interesesPosteadosMes(int $usuarioId, Carbon $inicio, Carbon $fin): int
    {
        $cuentaId = CuentaContable::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('codigo', '5200')->value('id');
        if (! $cuentaId) {
            return 0;
        }

        $debe = (int) MovimientoLibro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('cuenta_contable_id', $cuentaId)
            ->whereHas('asiento', fn ($q) => $q->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]))
            ->sum('debe_centavos');
        $haber = (int) MovimientoLibro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('cuenta_contable_id', $cuentaId)
            ->whereHas('asiento', fn ($q) => $q->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]))
            ->sum('haber_centavos');

        return $debe - $haber;
    }

    private function deudaMayorCosto(int $usuarioId): ?array
    {
        $candidatos = collect();
        foreach (Prestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('estado', '!=', 'cancelada')->get() as $p) {
            $interesRestante = (int) $p->cuotas()->where('pagada', false)->sum('interes_centavos');
            if ($interesRestante <= 0 && (float) $p->ea_porcentaje <= 0) {
                continue;
            }
            $candidatos->push([
                'tipo' => 'prestamo',
                'id' => $p->id,
                'nombre' => $p->nombre,
                'ea_porcentaje' => (float) $p->ea_porcentaje,
                'interes_restante_centavos' => $interesRestante,
            ]);
        }
        foreach (TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->get() as $t) {
            $interesRestante = (int) CuotaTarjeta::withoutGlobalScopes()
                ->where('tarjeta_credito_id', $t->id)->where('pagada', false)->sum('interes_centavos');
            $candidatos->push([
                'tipo' => 'tarjeta',
                'id' => $t->id,
                'nombre' => $t->nombre,
                'ea_porcentaje' => (float) $t->ea_porcentaje,
                'interes_restante_centavos' => $interesRestante,
            ]);
        }

        return $candidatos->sort(function (array $a, array $b): int {
            return [$b['ea_porcentaje'], $b['interes_restante_centavos']]
                <=> [$a['ea_porcentaje'], $a['interes_restante_centavos']];
        })->values()->first();
    }

    private function cuandoTermino(int $usuarioId): array
    {
        $items = [];
        foreach (Prestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->get() as $p) {
            $ultima = $p->cuotas()->where('pagada', false)->orderByDesc('fecha_vencimiento')->first();
            $items[] = [
                'tipo' => 'prestamo',
                'nombre' => $p->nombre,
                'fecha' => $ultima?->fecha_vencimiento?->toDateString() ?? $p->fecha_vencimiento?->toDateString(),
            ];
        }
        foreach (TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->get() as $t) {
            $ultima = CuotaTarjeta::withoutGlobalScopes()
                ->where('tarjeta_credito_id', $t->id)->where('pagada', false)
                ->orderByDesc('fecha_vencimiento')->first();
            $items[] = [
                'tipo' => 'tarjeta',
                'nombre' => $t->nombre,
                'fecha' => $ultima?->fecha_vencimiento?->toDateString(),
            ];
        }

        return collect($items)->filter(fn ($i) => $i['fecha'])->sortBy('fecha')->values()->all();
    }
}
