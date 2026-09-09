@extends('layouts.app', [
    'title' => 'Cuentas',
    'heading' => 'Mis cuentas',
    'subtitle' => 'Tu dinero, en un solo lugar.',
    'actionUrl' => route('app.cuentas.create'),
    'actionLabel' => 'Nueva cuenta',
])
@section('content')
@include('layouts.partials.form-errors')
@php
    $destinosActivos = $cuentas;
@endphp

<div class="list-block list-block--flush">
<div class="list-block__head">
    <h2>Activas</h2>
    <span>{{ $cuentas->count() }}</span>
</div>
<div class="card-stack">
@forelse ($cuentas as $cuenta)
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'wallet', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $cuenta->nombre }}</h2>
                    <small>{{ ucfirst($cuenta->tipo) }}@if($cuenta->institucion) · {{ $cuenta->institucion }}@endif</small>
                </div>
                <strong class="account-card__amount">@cop($cuenta->saldo_actual_centavos)</strong>
            </div>
            <div class="account-card__actions mt-2">
                <form method="POST" action="{{ route('app.cuentas.destroy', $cuenta) }}" class="d-inline" data-swal-confirm data-swal-title="¿Archivar esta cuenta?" data-swal-text="Deja de verse en tesorería activa. Puedes restaurarla después. El libro no se borra." data-swal-icon="warning" data-swal-confirm-text="Archivar">
                    @csrf @method('DELETE')
                    <button class="card-btn card-btn--warn" type="submit">
                        @include('layouts.partials.icon', ['name' => 'archive', 'class' => 'ui-icon ui-icon--xs'])
                        Archivar
                    </button>
                </form>
                <button type="button" class="card-btn card-btn--muted" data-bs-toggle="collapse" data-bs-target="#cancelar-{{ $cuenta->id }}" aria-expanded="false">
                    @include('layouts.partials.icon', ['name' => 'ban', 'class' => 'ui-icon ui-icon--xs'])
                    Cancelar…
                </button>
            </div>
            <div class="collapse mt-3" id="cancelar-{{ $cuenta->id }}">
                @include('cuentas.partials.cancelar-form', [
                    'cuenta' => $cuenta,
                    'destinos' => $destinosActivos->where('id', '!=', $cuenta->id),
                ])
            </div>
        </div>
    </div>
</article>
@empty
    <div class="alert alert-light empty-state">Aún no tienes cuentas activas. <a href="{{ route('app.cuentas.create') }}">Crear una</a>.</div>
@endforelse
</div>
</div>

<div class="list-block">
<div class="list-block__head">
    <h2>Archivadas</h2>
    <span>{{ $archivadas->count() }}</span>
</div>
<div class="card-stack">
@forelse ($archivadas as $cuenta)
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'wallet', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $cuenta->nombre }}</h2>
                    <small>Archivada · {{ ucfirst($cuenta->tipo) }}@if($cuenta->institucion) · {{ $cuenta->institucion }}@endif</small>
                </div>
                <strong class="account-card__amount">@cop($cuenta->saldo_actual_centavos)</strong>
            </div>
            <div class="account-card__actions mt-2">
                <form method="POST" action="{{ route('app.cuentas.restore', $cuenta) }}" class="d-inline">
                    @csrf
                    <button class="card-btn card-btn--ok" type="submit">
                        @include('layouts.partials.icon', ['name' => 'restore', 'class' => 'ui-icon ui-icon--xs'])
                        Restaurar
                    </button>
                </form>
                <button type="button" class="card-btn card-btn--muted" data-bs-toggle="collapse" data-bs-target="#cancelar-arch-{{ $cuenta->id }}" aria-expanded="false">
                    @include('layouts.partials.icon', ['name' => 'ban', 'class' => 'ui-icon ui-icon--xs'])
                    Cancelar…
                </button>
            </div>
            <div class="collapse mt-3" id="cancelar-arch-{{ $cuenta->id }}">
                @include('cuentas.partials.cancelar-form', [
                    'cuenta' => $cuenta,
                    'destinos' => $destinosActivos,
                ])
            </div>
        </div>
    </div>
</article>
@empty
    <div class="alert alert-light empty-state mb-0">No hay cuentas archivadas.</div>
@endforelse
</div>
</div>
@endsection
