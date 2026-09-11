@extends('layouts.app', [
    'title' => 'Cuentas',
    'heading' => 'Mis cuentas',
    'subtitle' => 'Tu dinero operativo, sin bolsillos de meta.',
])
@section('content')
@include('layouts.partials.form-errors')
@php
    $destinosActivos = $cuentas;
@endphp

<div class="page-toolbar">
    <a class="btn btn-primary btn-lg" href="{{ route('app.cuentas.create') }}">
        @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon ui-icon--sm'])
        Nueva cuenta
    </a>
</div>

<div class="list-block list-block--flush">
<div class="list-block__head">
    <h2>Activas</h2>
    <span>{{ $cuentas->count() }}</span>
</div>
<div class="card-stack">
@forelse ($cuentas as $cuenta)
@php $saldo = (int) ($saldos[(int) $cuenta->cuenta_contable_id] ?? 0); @endphp
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'wallet', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $cuenta->nombre }}</h2>
                    <small>{{ \App\Support\CuentasOperativas::etiquetaTipo($cuenta->tipo) }}@if($cuenta->institucion) · {{ $cuenta->institucion }}@endif</small>
                </div>
                <strong class="account-card__amount">@cop($saldo)</strong>
            </div>
            <div class="account-card__actions mt-2">
                <a class="card-btn card-btn--ok" href="{{ route('app.cuentas.show', $cuenta) }}">
                    @include('layouts.partials.icon', ['name' => 'list', 'class' => 'ui-icon ui-icon--xs'])
                    Detalle
                </a>
                <a class="card-btn card-btn--muted" href="{{ route('app.cuentas.edit', $cuenta) }}">
                    @include('layouts.partials.icon', ['name' => 'file-invoice', 'class' => 'ui-icon ui-icon--xs'])
                    Editar
                </a>
                <button type="button" class="card-btn card-btn--warn" data-bs-toggle="collapse" data-bs-target="#archivar-{{ $cuenta->id }}" aria-expanded="false">
                    @include('layouts.partials.icon', ['name' => 'archive', 'class' => 'ui-icon ui-icon--xs'])
                    Archivar…
                </button>
                <button type="button" class="card-btn card-btn--muted" data-bs-toggle="collapse" data-bs-target="#cancelar-{{ $cuenta->id }}" aria-expanded="false">
                    @include('layouts.partials.icon', ['name' => 'ban', 'class' => 'ui-icon ui-icon--xs'])
                    Cancelar…
                </button>
            </div>
            <div class="collapse mt-3" id="archivar-{{ $cuenta->id }}">
                @include('cuentas.partials.archivar-form', [
                    'cuenta' => $cuenta,
                    'saldo' => $saldo,
                    'destinos' => $destinosActivos->where('id', '!=', $cuenta->id),
                ])
            </div>
            <div class="collapse mt-3" id="cancelar-{{ $cuenta->id }}">
                @include('cuentas.partials.cancelar-form', [
                    'cuenta' => $cuenta,
                    'saldo' => $saldo,
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
@php $saldo = (int) ($saldos[(int) $cuenta->cuenta_contable_id] ?? 0); @endphp
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'wallet', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $cuenta->nombre }}</h2>
                    <small>Archivada · {{ \App\Support\CuentasOperativas::etiquetaTipo($cuenta->tipo) }}@if($cuenta->institucion) · {{ $cuenta->institucion }}@endif</small>
                </div>
                <strong class="account-card__amount">@cop($saldo)</strong>
            </div>
            <div class="account-card__actions mt-2">
                <a class="card-btn card-btn--ok" href="{{ route('app.cuentas.show', $cuenta) }}">
                    @include('layouts.partials.icon', ['name' => 'list', 'class' => 'ui-icon ui-icon--xs'])
                    Detalle
                </a>
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
                    'saldo' => $saldo,
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
