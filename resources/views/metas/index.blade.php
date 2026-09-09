@extends('layouts.app', [
    'title' => 'Metas de ahorro',
    'heading' => 'Mis metas',
    'subtitle' => 'El avance solo crece con aportes contabilizados al bolsillo de la meta.',
])
@section('content')
@php
    $errMeta = $errors->meta;
    $errAporte = $errors->aporte;
    $oldMeta = $errMeta->any();
    $oldAporte = $errAporte->any();
    $modoAporte = $oldAporte || request()->boolean('aporte');
@endphp

<div class="capture-flow">
    <nav class="flow-tabs" aria-label="Acción de metas">
        <a href="{{ route('app.metas.index') }}" class="{{ ! $modoAporte ? 'is-active' : '' }}">Nueva meta</a>
        <a href="{{ route('app.metas.index', ['aporte' => 1]) }}" class="{{ $modoAporte ? 'is-active' : '' }}">Aportar</a>
    </nav>

    @if(! $modoAporte)
        <form method="POST" action="{{ route('app.metas.store') }}" class="dash-card form-panel" novalidate>
            @csrf
            <div class="form-section__head">
                <p class="form-section__eyebrow">Objetivo</p>
                <h2 class="form-section__title">Crear una meta</h2>
            </div>
            @include('layouts.partials.form-errors', ['bag' => 'meta'])

            <div class="money-hero">
                <label class="form-label" for="objetivo">Monto objetivo</label>
                <input id="objetivo" name="objetivo" data-miles inputmode="decimal" value="{{ $oldMeta ? old('objetivo') : '' }}" class="form-control form-control-lg @error('objetivo', 'meta') is-invalid @enderror" placeholder="0" required>
                @include('layouts.partials.field-error', ['name' => 'objetivo', 'bag' => 'meta'])
            </div>

            <section class="form-section">
                <div>
                    <label class="form-label" for="nombre">Nombre de la meta</label>
                    <input id="nombre" name="nombre" value="{{ $oldMeta ? old('nombre') : '' }}" class="form-control form-control-lg @error('nombre', 'meta') is-invalid @enderror" placeholder="Ej. Fondo de emergencia" required>
                    @include('layouts.partials.field-error', ['name' => 'nombre', 'bag' => 'meta'])
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="aporte_mensual">Ahorro mensual planificado</label>
                        <input id="aporte_mensual" name="aporte_mensual" data-miles inputmode="decimal" value="{{ $oldMeta ? old('aporte_mensual', 0) : 0 }}" class="form-control form-control-lg @error('aporte_mensual', 'meta') is-invalid @enderror">
                        @include('layouts.partials.field-error', ['name' => 'aporte_mensual', 'bag' => 'meta'])
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="fecha_objetivo">Fecha objetivo</label>
                        <input id="fecha_objetivo" name="fecha_objetivo" type="date" value="{{ $oldMeta ? old('fecha_objetivo') : '' }}" class="form-control form-control-lg @error('fecha_objetivo', 'meta') is-invalid @enderror">
                        @include('layouts.partials.field-error', ['name' => 'fecha_objetivo', 'bag' => 'meta'])
                    </div>
                </div>
                <div>
                    <label class="form-label" for="cuenta_liquida_id">Cuenta de referencia</label>
                    <select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'meta') is-invalid @enderror" required>
                        <option value="">Selecciona cuenta</option>
                        @foreach($cuentasOperativas as $cuenta)
                            <option value="{{ $cuenta->id }}" @selected($oldMeta && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    <small class="text-secondary">Se crea un bolsillo dedicado; no se usa la cuenta operativa como destino.</small>
                    @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'meta'])
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="prioridad">Prioridad</label>
                        <select id="prioridad" name="prioridad" class="form-select form-select-lg @error('prioridad', 'meta') is-invalid @enderror" required>
                            @foreach(['alta'=>'Alta','media'=>'Media','baja'=>'Baja'] as $v=>$t)
                                <option value="{{ $v }}" @selected(($oldMeta ? old('prioridad', 'media') : 'media') === $v)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @include('layouts.partials.field-error', ['name' => 'prioridad', 'bag' => 'meta'])
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-select form-select-lg @error('estado', 'meta') is-invalid @enderror" required>
                            @foreach(['activa'=>'Activa','pausada'=>'Pausada','cumplida'=>'Cumplida','cancelada'=>'Cancelada'] as $v=>$t)
                                <option value="{{ $v }}" @selected(($oldMeta ? old('estado', 'activa') : 'activa') === $v)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @include('layouts.partials.field-error', ['name' => 'estado', 'bag' => 'meta'])
                    </div>
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit">Crear meta</button>
            </div>
        </form>
    @else
        @if($metas->isNotEmpty() && $cuentasOperativas->isNotEmpty())
        <form method="POST" action="{{ route('app.metas.aportes.store') }}" class="dash-card form-panel" novalidate>
            @csrf
            <div class="form-section__head">
                <p class="form-section__eyebrow">Movimiento</p>
                <h2 class="form-section__title">Registrar aporte</h2>
            </div>
            @include('layouts.partials.form-errors', ['bag' => 'aporte'])

            <div class="money-hero">
                <label class="form-label" for="monto_aporte">Monto</label>
                <input id="monto_aporte" name="monto" data-miles inputmode="decimal" value="{{ $oldAporte ? old('monto') : '' }}" class="form-control form-control-lg @error('monto', 'aporte') is-invalid @enderror" placeholder="0" required>
                @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'aporte'])
            </div>

            <section class="form-section">
                <div>
                    <label class="form-label" for="meta_ahorro_id">Meta</label>
                    <select id="meta_ahorro_id" name="meta_ahorro_id" class="form-select form-select-lg @error('meta_ahorro_id', 'aporte') is-invalid @enderror" required>
                        <option value="">Selecciona una meta</option>
                        @foreach($metas->where('estado', '!=', 'cancelada') as $meta)
                            <option value="{{ $meta->id }}" @selected($oldAporte && old('meta_ahorro_id') == $meta->id)>{{ $meta->nombre }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'meta_ahorro_id', 'bag' => 'aporte'])
                </div>
                <div>
                    <label class="form-label" for="cuenta_origen_aporte">Sale de</label>
                    <select id="cuenta_origen_aporte" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'aporte') is-invalid @enderror" required>
                        <option value="">Cuenta operativa</option>
                        @foreach($cuentasOperativas as $cuenta)
                            <option value="{{ $cuenta->id }}" @selected($oldAporte && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'aporte'])
                </div>
                <div>
                    <label class="form-label" for="fecha_aporte">Fecha</label>
                    <input id="fecha_aporte" name="fecha" type="date" value="{{ $oldAporte ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control form-control-lg @error('fecha', 'aporte') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'aporte'])
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit">Contabilizar aporte</button>
            </div>
        </form>
        @elseif($metas->isNotEmpty())
            <div class="alert alert-light empty-state">
                Para aportar necesitas una cuenta operativa (no bolsillo) con saldo.
                <a href="{{ route('app.cuentas.create') }}">Crear cuenta</a>.
            </div>
        @else
            <div class="alert alert-light empty-state">Crea una meta primero para poder aportar.</div>
        @endif
    @endif

    <div class="list-block">
        <div class="list-block__head">
            <h2>Tus metas</h2>
            <span>{{ $metas->count() }}</span>
        </div>
        <div class="card-stack">
        @forelse($metas as $meta)
        <article class="account-card">
            <div class="account-card__main">
                <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'piggy-bank', 'class' => 'ui-icon ui-icon--sm'])</div>
                <div class="account-card__body">
                    <div class="account-card__head">
                        <div class="account-card__title">
                            <h2>{{ $meta->nombre }}</h2>
                            <small>{{ ucfirst($meta->prioridad) }} · {{ ucfirst($meta->estado) }}@if($meta->cuentaLiquida) · {{ $meta->cuentaLiquida->nombre }}@endif</small>
                        </div>
                        <strong class="account-card__amount">{{ $meta->porcentaje_completado }}%</strong>
                    </div>
                    <div class="progress progress--thin mt-3"><div class="progress-bar" style="width: {{ min(100, $meta->porcentaje_completado) }}%"></div></div>
                    <div class="small text-secondary mt-2">Avance @cop($meta->progreso_centavos) de @cop($meta->objetivo_centavos) · Plan/mes: @cop($meta->aporte_mensual_centavos)</div>
                    <div class="small text-secondary">Cumplimiento estimado: {{ $meta->fecha_estimada_cumplimiento ? \Carbon\Carbon::parse($meta->fecha_estimada_cumplimiento)->format('d/m/Y') : 'sin fecha (define un ahorro mensual)' }}</div>
                </div>
            </div>
        </article>
        @empty
            <div class="alert alert-light empty-state">Aún no tienes metas de ahorro.</div>
        @endforelse
        </div>
    </div>
</div>
@endsection
