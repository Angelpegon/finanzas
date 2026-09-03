@extends('layouts.app', ['title' => 'Metas de ahorro'])
@section('content')
<p class="eyebrow mb-2">Ahorro con propósito</p>
<h1 class="display-title mb-1">Mis metas</h1>
<p class="text-secondary mb-4">El avance solo crece con aportes contabilizados al bolsillo de la meta.</p>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('app.metas.store') }}" class="card border-0 shadow-sm rounded-4 p-3 mb-4 vstack gap-3">@csrf
    <input name="nombre" class="form-control form-control-lg" placeholder="Nombre de la meta" required>
    <div class="row g-3"><div class="col-6"><label class="form-label">Objetivo</label><input name="objetivo" data-miles inputmode="decimal" class="form-control" required></div><div class="col-6"><label class="form-label">Ahorro mensual planificado</label><input name="aporte_mensual" data-miles inputmode="decimal" class="form-control" value="0"></div></div>
    <div class="row g-3"><div class="col-6"><label class="form-label">Fecha objetivo</label><input name="fecha_objetivo" type="date" class="form-control"></div><div class="col-6"><label class="form-label">Bolsillo (cuenta destino)</label><select name="cuenta_liquida_id" class="form-select" required><option value="">Selecciona cuenta</option>@foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>@endforeach</select></div></div>
    <div class="row g-3"><div class="col-6"><label class="form-label">Prioridad</label><select name="prioridad" class="form-select"><option value="alta">Alta</option><option value="media" selected>Media</option><option value="baja">Baja</option></select></div><div class="col-6"><label class="form-label">Estado</label><select name="estado" class="form-select"><option value="activa">Activa</option><option value="pausada">Pausada</option><option value="cumplida">Cumplida</option><option value="cancelada">Cancelada</option></select></div></div>
    <button class="btn btn-primary btn-lg rounded-4" type="submit">Crear meta</button>
</form>
@if($metas->isNotEmpty() && $cuentas->count() > 1)
<form method="POST" action="{{ route('app.metas.aportes.store') }}" class="card border-0 shadow-sm rounded-4 p-3 mb-4 vstack gap-3">@csrf
    <h2 class="h5 mb-0">Registrar aporte</h2>
    <select name="meta_ahorro_id" class="form-select" required><option value="">Meta</option>@foreach($metas->where('estado', '!=', 'cancelada') as $meta)<option value="{{ $meta->id }}">{{ $meta->nombre }}</option>@endforeach</select>
    <select name="cuenta_liquida_id" class="form-select" required><option value="">Sale de</option>@foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>@endforeach</select>
    <input name="monto" data-miles inputmode="decimal" class="form-control" placeholder="Monto" required>
    <input name="fecha" type="date" value="{{ now()->toDateString() }}" class="form-control" required>
    <button class="btn btn-outline-primary rounded-4" type="submit">Contabilizar aporte</button>
</form>
@endif
<div class="vstack gap-3">@forelse($metas as $meta)<article class="account-card"><div class="account-card__icon">★</div><div class="account-card__body"><div class="d-flex justify-content-between"><div><h2>{{ $meta->nombre }}</h2><small>{{ ucfirst($meta->prioridad) }} · {{ ucfirst($meta->estado) }} @if($meta->cuentaLiquida) · {{ $meta->cuentaLiquida->nombre }} @endif</small></div><strong>{{ $meta->porcentaje_completado }}%</strong></div><div class="progress mt-3" style="height:8px"><div class="progress-bar" style="width: {{ min(100, $meta->porcentaje_completado) }}%"></div></div><div class="small text-secondary mt-2">Avance @cop($meta->progreso_centavos) de @cop($meta->objetivo_centavos) · Plan/mes: @cop($meta->aporte_mensual_centavos)</div><div class="small text-secondary">Cumplimiento estimado: {{ $meta->fecha_estimada_cumplimiento ? \Carbon\Carbon::parse($meta->fecha_estimada_cumplimiento)->format('d/m/Y') : 'sin fecha (define un ahorro mensual)' }}</div></div></article>@empty<div class="alert alert-light">Aún no tienes metas de ahorro.</div>@endforelse</div>
@endsection
