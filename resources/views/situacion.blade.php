@extends('layouts.app', ['title' => 'Situación financiera'])
@section('content')
<div class="page-intro d-flex justify-content-between align-items-start">
    <div>
        <p class="eyebrow mb-2">Tu resumen</p>
        <h1 class="display-title mb-1">Hola, {{ auth()->user()->nombre }}</h1>
        <p class="text-secondary mb-0">Así se ve tu dinero este mes.</p>
    </div>
    <div class="avatar">{{ strtoupper(substr(auth()->user()->nombre, 0, 1)) }}</div>
</div>
<section class="hero-balance mt-4">
    <div class="d-flex justify-content-between align-items-center"><span class="hero-label">Dinero disponible</span><span class="hero-dot"></span></div>
    <p class="hero-amount">@cop($situacion['dinero_disponible_real_centavos'])</p>
    <p class="hero-note mb-0">Después de apartar compromisos y salidas pendientes</p>
</section>
@if(count($situacion['alertas']) > 0)
    <section class="section-block mt-4">
        <div class="section-heading"><div><p class="eyebrow mb-1">Atención</p><h2>Alertas</h2></div></div>
        <div class="vstack gap-2">
            @foreach($situacion['alertas'] as $alerta)
                <div class="alert alert-{{ $alerta['nivel'] }} mb-0"><strong>{{ $alerta['titulo'] }}</strong><br><span>{{ $alerta['mensaje'] }}</span></div>
            @endforeach
        </div>
    </section>
@endif
<section class="section-block mt-4">
    <div class="section-heading"><div><p class="eyebrow mb-1">Motor de análisis</p><h2>Lo que realmente tienes</h2></div></div>
    <div class="projection-row"><span>Saldo en cuentas<small class="d-block text-secondary">Libro contable, antes de compromisos futuros</small></span><strong>@cop($situacion['saldo_cuentas_centavos'])</strong></div>
    <div class="projection-row"><span>Dinero comprometido<small class="d-block text-secondary">Cuotas pendientes y aportes de metas</small></span><strong class="text-warning">@cop($situacion['dinero_comprometido_centavos'])</strong></div>
    <div class="projection-row"><span>Gastos proyectados pendientes<small class="d-block text-secondary">No son deuda hasta registrarse</small></span><strong>@cop($situacion['gastos_proyectados_pendientes_centavos'])</strong></div>
    <div class="projection-row projection-total"><span>Dinero disponible real</span><strong class="{{ $situacion['dinero_disponible_real_centavos'] >= 0 ? 'text-success' : 'text-danger' }}">@cop($situacion['dinero_disponible_real_centavos'])</strong></div>
</section>
<div class="row g-3 mt-1">
    <div class="col-6"><div class="metric-card"><span class="metric-icon metric-icon--green">↑</span><span class="metric-label">Ingresos del mes</span><strong>@cop($situacion['ingresos_mes_centavos'])</strong></div></div>
    <div class="col-6"><div class="metric-card"><span class="metric-icon metric-icon--red">↓</span><span class="metric-label">Gastos del mes</span><strong>@cop($situacion['gastos_mes_centavos'])</strong></div></div>
    <div class="col-6"><div class="metric-card"><span class="metric-icon metric-icon--amber">!</span><span class="metric-label">Pagos de deuda</span><strong>@cop($situacion['pagos_deuda_mes_centavos'])</strong></div></div>
    <div class="col-6"><div class="metric-card"><span class="metric-icon metric-icon--red">-</span><span class="metric-label">Deuda total</span><strong>@cop($situacion['debo_centavos'])</strong></div></div>
</div>
<section class="section-block mt-4">
    <div class="section-heading"><div><p class="eyebrow mb-1">Proyección</p><h2>Este mes</h2></div><span class="soft-badge">COP</span></div>
    <div class="projection-row"><span>Ingresos del mes</span><strong class="text-success">@cop($situacion['ingresos_mes_centavos'])</strong></div>
    <div class="projection-row"><span>Gastos y deuda</span><strong class="text-danger">@cop($situacion['gastos_mes_centavos'] + $situacion['pagos_deuda_mes_centavos'])</strong></div>
    <div class="projection-row projection-total"><span>Ahorro / flujo de caja</span><strong class="{{ $situacion['flujo_caja_centavos'] >= 0 ? 'text-success' : 'text-danger' }}">@cop($situacion['flujo_caja_centavos'])</strong></div>
</section>
<div class="row g-3 mt-1">
    <div class="col-6"><div class="metric-card"><span class="metric-label">Tasa de ahorro</span><strong>{{ $situacion['tasa_ahorro_porcentaje'] }}%</strong><small class="text-secondary">ahorro / ingresos</small></div></div>
    <div class="col-6"><div class="metric-card"><span class="metric-label">Endeudamiento</span><strong>{{ $situacion['nivel_endeudamiento_porcentaje'] }}%</strong><small class="text-secondary">deuda / liquidez</small></div></div>
    <div class="col-6"><div class="metric-card"><span class="metric-label">Ingresos comprometidos</span><strong>{{ $situacion['ingresos_comprometidos_porcentaje'] }}%</strong><small class="text-secondary">gastos y cuotas</small></div></div>
</div>
<p class="small text-secondary mt-3 mb-0">Endeudamiento: {{ $situacion['nivel_endeudamiento_definicion'] }}. Ingresos comprometidos: {{ $situacion['ingresos_comprometidos_definicion'] }}.</p>
<section class="section-block mt-4"><div class="section-heading"><div><p class="eyebrow mb-1">Evolución</p><h2>Patrimonio y deuda</h2></div></div><div class="projection-row"><span>Deuda actual</span><strong>@cop($situacion['debo_centavos'])</strong></div><div class="small text-secondary mt-2">Últimos seis meses</div><div class="table-responsive mt-2"><table class="table table-sm small"><thead><tr><th>Mes</th><th>Deuda</th><th>Patrimonio</th></tr></thead><tbody>@foreach($situacion['evolucion_deuda'] as $indice => $punto)<tr><td>{{ $punto['periodo'] }}</td><td>@cop($punto['centavos'])</td><td>@cop($situacion['evolucion_patrimonial'][$indice]['centavos'])</td></tr>@endforeach</tbody></table></div></section>
<section class="section-block mt-4">
    <div class="section-heading"><div><p class="eyebrow mb-1">Agenda financiera</p><h2>Próximos eventos</h2></div></div>
    @forelse($situacion['proximos_vencimientos'] as $evento)
        <div class="projection-row"><span>{{ $evento->prestamo?->nombre ?: 'Tarjeta de crédito' }} · cuota {{ $evento->numero }}<small class="d-block text-secondary">{{ $evento->fecha_vencimiento->format('d/m/Y') }}</small></span><strong>@cop($evento->total_centavos)</strong></div>
    @empty
        <p class="text-secondary mb-0">No tienes vencimientos en los próximos 30 días.</p>
    @endforelse
    @foreach($situacion['ingresos_esperados'] as $ingreso)
        <div class="projection-row"><span>{{ $ingreso->nombre }}<small class="d-block text-secondary">Ingreso {{ $ingreso->periodicidad }}</small></span><strong class="text-success">@cop($ingreso->monto_centavos)</strong></div>
    @endforeach
</section>
@if($situacion['presupuestos_alertas']->isNotEmpty())<section class="section-block mt-4"><div class="section-heading"><div><p class="eyebrow mb-1">Atención</p><h2>Presupuestos próximos a agotarse</h2></div></div>@foreach($situacion['presupuestos_alertas'] as $linea)<div class="projection-row"><span>{{ $linea->categoria->nombre }}<small class="d-block text-secondary">@cop($linea->gasto_real_centavos) de @cop($linea->tope_centavos)</small></span><strong class="text-danger">{{ $linea->porcentaje_consumido }}%</strong></div>@endforeach</section>@endif
<a href="{{ route('app.cuentas.index') }}" class="quick-action mt-4"><span class="quick-action-icon">+</span><span><strong>Organiza tus cuentas</strong><small>Agrega bancos, efectivo o billeteras</small></span><span class="arrow">›</span></a>
@endsection
