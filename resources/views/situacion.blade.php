@extends('layouts.app', [
    'title' => 'Situación',
    'heading' => 'Situación',
    'subtitle' => 'Resumen del mes seleccionado',
])
@section('header-utils')
    <nav class="dashboard-period d-none d-md-inline-flex" aria-label="Mes del resumen">
        <a class="dashboard-period__nav" href="{{ route('app.situacion', ['anio' => $mesAnterior->year, 'mes' => $mesAnterior->month]) }}" aria-label="Mes anterior">‹</a>
        <span class="dashboard-period__label">
            @include('layouts.partials.icon', ['name' => 'calendar', 'class' => 'ui-icon ui-icon--sm'])
            <span>{{ $situacion['periodo_etiqueta'] }}</span>
        </span>
        <a class="dashboard-period__nav" href="{{ route('app.situacion', ['anio' => $mesSiguiente->year, 'mes' => $mesSiguiente->month]) }}" aria-label="Mes siguiente">›</a>
        @unless($esMesActual)
            <a class="dashboard-period__today" href="{{ route('app.situacion') }}">Hoy</a>
        @endunless
    </nav>
@endsection
@section('content')
@php
    $v = $situacion['variaciones'] ?? [];
    $fmtVar = function (?float $pct, bool $positivoEsBueno = true): array {
        if ($pct === null) {
            return ['texto' => 'Sin base mes ant.', 'clase' => 'kpi-delta--flat', 'icono' => null];
        }
        $signo = $pct > 0 ? '+' : '';
        if ($pct == 0.0) {
            return [
                'texto' => '0% vs mes anterior',
                'clase' => 'kpi-delta--flat',
                'icono' => null,
            ];
        }
        $favorable = $positivoEsBueno ? ($pct > 0) : ($pct < 0);

        return [
            'texto' => $signo.number_format($pct, 1, ',', '.').'% vs mes anterior',
            'clase' => $favorable ? 'kpi-delta--up' : 'kpi-delta--down',
            'icono' => $pct > 0 ? 'arrow-up' : 'arrow-down',
        ];
    };
    $varIngresos = $fmtVar($v['ingresos_porcentaje'] ?? null, true);
    $varGastos = $fmtVar($v['gastos_porcentaje'] ?? null, false);
    $varPagos = $fmtVar($v['pagos_deuda_porcentaje'] ?? null, false);
    $varDisponible = $fmtVar($v['disponible_porcentaje'] ?? null, true);
    $deudas = $situacion['deudas'] ?? [];
@endphp

<nav class="dashboard-period-bar d-md-none" aria-label="Mes del resumen">
    <a class="dashboard-period__nav" href="{{ route('app.situacion', ['anio' => $mesAnterior->year, 'mes' => $mesAnterior->month]) }}" aria-label="Mes anterior">‹</a>
    <span class="dashboard-period__label">
        @include('layouts.partials.icon', ['name' => 'calendar', 'class' => 'ui-icon ui-icon--sm'])
        <span>{{ $situacion['periodo_etiqueta'] }}</span>
    </span>
    <a class="dashboard-period__nav" href="{{ route('app.situacion', ['anio' => $mesSiguiente->year, 'mes' => $mesSiguiente->month]) }}" aria-label="Mes siguiente">›</a>
    @unless($esMesActual)
        <a class="dashboard-period__today" href="{{ route('app.situacion') }}">Hoy</a>
    @endunless
</nav>

<div class="dashboard">
    <div class="dashboard-main">
        <div class="kpi-grid">
            <article class="kpi-card kpi-card--green">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Ingresos del mes</span>
                    <span class="kpi-card__icon">@include('layouts.partials.icon', ['name' => 'trending-up', 'class' => 'ui-icon'])</span>
                </div>
                <strong class="kpi-card__value">@cop($situacion['ingresos_mes_centavos'])</strong>
                <span class="kpi-delta {{ $varIngresos['clase'] }}">
                    @if($varIngresos['icono'])@include('layouts.partials.icon', ['name' => $varIngresos['icono'], 'class' => 'ui-icon ui-icon--xs'])@endif
                    {{ $varIngresos['texto'] }}
                </span>
            </article>
            <article class="kpi-card kpi-card--red">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Gastos del mes</span>
                    <span class="kpi-card__icon">@include('layouts.partials.icon', ['name' => 'wallet', 'class' => 'ui-icon'])</span>
                </div>
                <strong class="kpi-card__value">@cop($situacion['gastos_mes_centavos'])</strong>
                <span class="kpi-delta {{ $varGastos['clase'] }}">
                    @if($varGastos['icono'])@include('layouts.partials.icon', ['name' => $varGastos['icono'], 'class' => 'ui-icon ui-icon--xs'])@endif
                    {{ $varGastos['texto'] }}
                </span>
            </article>
            <article class="kpi-card kpi-card--amber">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Pagos de deudas</span>
                    <span class="kpi-card__icon">@include('layouts.partials.icon', ['name' => 'credit-card', 'class' => 'ui-icon'])</span>
                </div>
                <strong class="kpi-card__value">@cop($situacion['pagos_deuda_mes_centavos'])</strong>
                <span class="kpi-delta {{ $varPagos['clase'] }}">
                    @if($varPagos['icono'])@include('layouts.partials.icon', ['name' => $varPagos['icono'], 'class' => 'ui-icon ui-icon--xs'])@endif
                    {{ $varPagos['texto'] }}
                </span>
            </article>
            <article class="kpi-card kpi-card--blue">
                <div class="kpi-card__top">
                    <span class="kpi-card__label">Disponible libre</span>
                    <span class="kpi-card__icon">@include('layouts.partials.icon', ['name' => 'banknote', 'class' => 'ui-icon'])</span>
                </div>
                <strong class="kpi-card__value">@cop($situacion['dinero_disponible_real_centavos'])</strong>
                <span class="kpi-delta {{ $varDisponible['clase'] }}">
                    @if($varDisponible['icono'])@include('layouts.partials.icon', ['name' => $varDisponible['icono'], 'class' => 'ui-icon ui-icon--xs'])@endif
                    {{ $varDisponible['texto'] }}
                </span>
                <small class="kpi-card__hint">Excluye @cop($situacion['reservado_metas_centavos']) en bolsillos de meta y compromisos del mes</small>
            </article>
        </div>

        <div class="dash-row dash-row--2">
            <section class="dash-card">
                <div class="dash-card__head">
                    <h2>Próximos pagos</h2>
                    <a href="{{ route('app.calendario', ['anio' => $mesRef->year, 'mes' => $mesRef->month]) }}">Ver todo</a>
                </div>
                <div class="dash-list">
                    @forelse($situacion['proximos_vencimientos'] as $evento)
                        @php
                            $nombre = $evento->prestamo?->nombre
                                ?? $evento->compra?->tarjetaCredito?->nombre
                                ?? 'Obligación';
                        @endphp
                        <div class="dash-list__row">
                            <span class="dash-list__icon dash-list__icon--amber">@include('layouts.partials.icon', ['name' => 'file-invoice', 'class' => 'ui-icon ui-icon--sm'])</span>
                            <div class="dash-list__body">
                                <strong>
                                    <a href="{{ route('app.calendario', ['anio' => $evento->fecha_vencimiento->year, 'mes' => $evento->fecha_vencimiento->month, 'dia' => $evento->fecha_vencimiento->toDateString()]) }}">
                                        {{ $nombre }}
                                    </a>
                                </strong>
                                <small>Cuota {{ $evento->numero }} · {{ $evento->fecha_vencimiento->format('d M') }}</small>
                            </div>
                            <strong class="dash-list__amount text-danger">@cop($evento->total_centavos)</strong>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">Sin vencimientos en 30 días.</p>
                    @endforelse
                </div>
            </section>

            <section class="dash-card">
                <div class="dash-card__head">
                    <h2>Presupuesto</h2>
                    <a href="{{ route('app.presupuestos.index') }}">Gestionar</a>
                </div>
                <div class="dash-list">
                    @forelse($situacion['presupuesto_lineas'] as $linea)
                        @php $pct = min(100, (float) $linea->porcentaje_consumido); @endphp
                        <div class="budget-row">
                            <div class="d-flex justify-content-between gap-2">
                                <strong>{{ $linea->categoria->nombre }}</strong>
                                <span class="{{ $pct >= 100 ? 'text-danger' : 'text-secondary' }}">{{ number_format($pct, 0) }}%</span>
                            </div>
                            <div class="progress progress--thin mt-2">
                                <div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $pct }}%"></div>
                            </div>
                            <small class="text-secondary">@cop($linea->gasto_real_centavos) de @cop($linea->tope_centavos)</small>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">Aún no hay presupuesto este mes. <a href="{{ route('app.presupuestos.index') }}">Crear</a></p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="dash-row dash-row--2">
            <section class="dash-card">
                <div class="dash-card__head">
                    <h2>Deudas</h2>
                    <a href="{{ route('app.deudas.index') }}">Ver todas</a>
                </div>
                @if(count($deudas) > 0)
                    <div class="debt-carousel" data-debt-carousel data-interval="5000">
                        @foreach($deudas as $index => $d)
                            <div class="debt-detail" data-debt-slide @if($index > 0) hidden @endif>
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <span class="dash-list__icon dash-list__icon--amber">@include('layouts.partials.icon', ['name' => 'credit-card', 'class' => 'ui-icon ui-icon--sm'])</span>
                                        <div class="min-w-0">
                                            <strong class="d-block text-truncate">{{ $d['nombre'] }}</strong>
                                            <small class="d-block text-secondary text-capitalize">{{ $d['tipo'] }}</small>
                                        </div>
                                    </div>
                                    <span class="status-pill">{{ $d['estado'] }}</span>
                                </div>
                                <p class="debt-detail__saldo mb-1">@cop($d['saldo_centavos'])</p>
                                <small class="text-secondary">Saldo actual · {{ $d['avance_porcentaje'] }}% avanzado</small>
                                <div class="progress progress--thin mt-2 mb-3">
                                    <div class="progress-bar bg-warning" style="width: {{ min(100, max(0, $d['avance_porcentaje'])) }}%"></div>
                                </div>
                                <div class="debt-detail__stats">
                                    <div><span>Cuota</span><strong>@cop($d['cuota_centavos'])</strong></div>
                                    <div><span>Tasa EA</span><strong>{{ number_format($d['ea_porcentaje'], 2, ',', '.') }}%</strong></div>
                                    @if($d['cuotas_pagadas'] !== null)
                                        <div><span>Cuotas</span><strong>{{ $d['cuotas_pagadas'] }}/{{ $d['cuotas_total'] }}</strong></div>
                                    @endif
                                    <div><span>Próximo pago</span><strong>{{ $d['proximo_pago'] ? \Carbon\Carbon::parse($d['proximo_pago'])->format('d/m/Y') : '—' }}</strong></div>
                                </div>
                            </div>
                        @endforeach
                        @if(count($deudas) > 1)
                            <div class="debt-carousel__dots" role="tablist" aria-label="Deudas">
                                @foreach($deudas as $index => $d)
                                    <button type="button" class="debt-carousel__dot {{ $index === 0 ? 'is-active' : '' }}" data-debt-dot="{{ $index }}" aria-label="{{ $d['nombre'] }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-secondary mb-0">No tienes deudas activas con saldo.</p>
                @endif
            </section>

            <section class="dash-card">
                <div class="dash-card__head">
                    <h2>Calendario financiero</h2>
                    <a href="{{ route('app.calendario', ['anio' => $mesRef->year, 'mes' => $mesRef->month]) }}">{{ $situacion['calendario_grilla']['etiqueta'] ?? '' }}</a>
                </div>
                <div class="mini-cal">
                    <div class="mini-cal__weekdays">
                        @foreach(['L','M','X','J','V','S','D'] as $wd)
                            <span>{{ $wd }}</span>
                        @endforeach
                    </div>
                    <div class="mini-cal__grid">
                        @foreach($situacion['calendario_grilla']['celdas'] ?? [] as $celda)
                            @if($celda === null)
                                <div class="mini-cal__cell mini-cal__cell--empty"></div>
                            @else
                                <a
                                    class="mini-cal__cell {{ $celda['hoy'] ? 'is-today' : '' }} {{ $celda['tiene_pago'] ? 'has-pago' : '' }} {{ $celda['tiene_ingreso'] ? 'has-ingreso' : '' }} {{ ($celda['tiene_vencido'] ?? false) ? 'has-vencido' : '' }}"
                                    href="{{ route('app.calendario', ['anio' => $mesRef->year, 'mes' => $mesRef->month, 'dia' => $celda['fecha']]) }}"
                                    title="{{ collect($celda['eventos'])->pluck('descripcion')->join(' · ') ?: 'Sin eventos' }}"
                                >
                                    <span class="mini-cal__day">{{ $celda['dia'] }}</span>
                                    @if(count($celda['eventos']) > 0)
                                        <span class="mini-cal__dots">
                                            @if($celda['tiene_ingreso'])<i class="dot dot--green"></i>@endif
                                            @if($celda['tiene_pago'])<i class="dot dot--red"></i>@endif
                                            @if($celda['tiene_vencido'] ?? false)<i class="dot dot--amber"></i>@endif
                                        </span>
                                    @endif
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>

    <aside class="dashboard-rail">
        <section class="dash-card">
            <div class="dash-card__head">
                <h2>Cuentas operativas</h2>
                <a class="btn-chip" href="{{ route('app.cuentas.create') }}">+ Nueva</a>
            </div>
            <div class="dash-list">
                @forelse($situacion['cuentas'] as $cuenta)
                    <div class="dash-list__row">
                        <span class="dash-list__icon">{{ strtoupper(substr($cuenta['nombre'], 0, 1)) }}</span>
                        <div class="dash-list__body">
                            <strong>{{ $cuenta['nombre'] }}</strong>
                            <small>{{ ucfirst($cuenta['tipo']) }}@if($cuenta['institucion']) · {{ $cuenta['institucion'] }}@endif</small>
                        </div>
                        <strong>@cop($cuenta['saldo_centavos'])</strong>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Sin cuentas. <a href="{{ route('app.cuentas.create') }}">Crear</a></p>
                @endforelse
            </div>
            @if(($situacion['cuentas'] ?? collect())->isNotEmpty())
                <div class="rail-total">
                    <span>Total operativo</span>
                    <strong>@cop($situacion['total_cuentas_centavos'])</strong>
                </div>
            @endif
        </section>

        <section class="dash-card">
            <div class="dash-card__head">
                <h2>Movimientos recientes</h2>
            </div>
            <div class="filter-tabs" data-mov-tabs>
                <button type="button" class="is-active" data-mov-filter="todos">Todos</button>
                <button type="button" data-mov-filter="ingreso">Ingresos</button>
                <button type="button" data-mov-filter="gasto">Gastos</button>
                <button type="button" data-mov-filter="transferencia">Transfer.</button>
            </div>
            <div class="dash-list" data-mov-list>
                @forelse($situacion['movimientos_recientes'] as $mov)
                    <div class="dash-list__row" data-mov-tipo="{{ $mov['tipo'] === 'aporte_meta' ? 'transferencia' : $mov['tipo'] }}">
                        <span class="dash-list__icon {{ $mov['signo'] > 0 ? 'dash-list__icon--green' : ($mov['signo'] < 0 ? 'dash-list__icon--red' : '') }}">
                            @include('layouts.partials.icon', ['name' => $mov['signo'] > 0 ? 'arrow-up' : ($mov['signo'] < 0 ? 'arrow-down' : 'exchange'), 'class' => 'ui-icon ui-icon--sm'])
                        </span>
                        <div class="dash-list__body">
                            <strong>{{ $mov['descripcion'] }}</strong>
                            <small>{{ ucfirst(str_replace('_', ' ', $mov['tipo'])) }}@if($mov['categoria']) · {{ $mov['categoria'] }}@endif · {{ \Carbon\Carbon::parse($mov['fecha'])->format('d/m') }}</small>
                        </div>
                        <strong class="{{ $mov['signo'] > 0 ? 'text-success' : ($mov['signo'] < 0 ? 'text-danger' : '') }}">
                            {{ $mov['signo'] > 0 ? '+' : ($mov['signo'] < 0 ? '-' : '') }}@cop($mov['monto_centavos'])
                        </strong>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Aún no hay movimientos.</p>
                @endforelse
            </div>
        </section>

        <section class="dash-card">
            <div class="dash-card__head"><h2>Acciones rápidas</h2></div>
            <div class="quick-grid">
                <a href="{{ route('app.ingresos.create') }}" class="quick-tile quick-tile--green"><span>@include('layouts.partials.icon', ['name' => 'arrow-up', 'class' => 'ui-icon'])</span>Nuevo ingreso</a>
                <a href="{{ route('app.gastos.create') }}" class="quick-tile quick-tile--red"><span>@include('layouts.partials.icon', ['name' => 'arrow-down', 'class' => 'ui-icon'])</span>Nuevo gasto</a>
                <a href="{{ route('app.pagos.index') }}" class="quick-tile quick-tile--purple"><span>@include('layouts.partials.icon', ['name' => 'exchange', 'class' => 'ui-icon'])</span>Transferencia</a>
                <a href="{{ route('app.pagos.index', ['pago' => 1]) }}" class="quick-tile quick-tile--green"><span>@include('layouts.partials.icon', ['name' => 'banknote', 'class' => 'ui-icon'])</span>Nuevo pago</a>
                <a href="{{ route('app.presupuestos.index') }}" class="quick-tile quick-tile--amber"><span>@include('layouts.partials.icon', ['name' => 'percent', 'class' => 'ui-icon'])</span>Presupuesto</a>
                <a href="{{ route('app.deudas.create') }}" class="quick-tile quick-tile--amber"><span>@include('layouts.partials.icon', ['name' => 'file-invoice', 'class' => 'ui-icon'])</span>Nuevo crédito</a>
            </div>
        </section>
    </aside>
</div>
@endsection
