@extends('layouts.app', [
    'title' => 'Deudas',
    'heading' => 'Mis deudas',
    'subtitle' => 'Todo lo que debes, con claridad.',
    'actionUrl' => route('app.deudas.create'),
    'actionLabel' => 'Nueva deuda',
])
@section('content')
@php
    $errPagoPrestamo = $errors->pago_prestamo;
    $prestamoConError = $errPagoPrestamo->any() ? (int) old('prestamo_id') : null;
@endphp
@include('layouts.partials.form-errors', ['bag' => 'pago_prestamo'])
<div class="card-stack">
@foreach($prestamos as $deuda)
@php
    $proxima = $deuda->cuotas->firstWhere('pagada', false);
    $montoCuotaCentavos = $proxima
        ? (int) ($proxima->total_centavos ?: ($proxima->capital_centavos + $proxima->interes_centavos + $proxima->seguro_centavos + $proxima->otros_cargos_centavos))
        : 0;
    $montoCuotaPrefijo = $proxima ? \App\Support\Dinero::centavosAPesos($montoCuotaCentavos) : '';
    $esteForm = $proxima && $prestamoConError === (int) $deuda->id;
@endphp
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'file-invoice', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $deuda->nombre }}</h2>
                    <small>{{ $deuda->entidad ?: 'Obligación' }} · {{ str_replace('_', ' ', $deuda->tipo_obligacion) }}</small>
                </div>
                <strong class="account-card__amount">@cop($deuda->saldo_actual_centavos)</strong>
            </div>
            <div class="debt-meta">
                <span>{{ $deuda->cuotas_pagadas }} pagadas</span>
                <span>{{ $deuda->cuotas_pendientes }} pendientes</span>
                <span class="status-pill">{{ ucfirst($deuda->estado) }}</span>
            </div>
        </div>
    </div>
    <div class="account-card__footer">
        @if($proxima)
        <form method="POST" action="{{ route('app.deudas.pagos.store') }}" class="row g-2 align-items-end" novalidate>
            @csrf
            <input type="hidden" name="prestamo_id" value="{{ $deuda->id }}">
            <div class="col-md-4">
                <label class="form-label small mb-1">Pagar cuota {{ $proxima->numero }}</label>
                <input name="monto" data-miles inputmode="decimal" class="form-control @if($esteForm) @error('monto', 'pago_prestamo') is-invalid @enderror @endif" value="{{ $esteForm ? old('monto', $montoCuotaPrefijo) : $montoCuotaPrefijo }}" required>
                @if($esteForm)
                    @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'pago_prestamo'])
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Fecha</label>
                <input name="fecha" type="date" value="{{ $esteForm ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control @if($esteForm) @error('fecha', 'pago_prestamo') is-invalid @enderror @endif" required>
                @if($esteForm)
                    @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'pago_prestamo'])
                @endif
            </div>
            <div class="col-md-4">
                <button class="card-btn card-btn--primary card-btn--block" type="submit">
                    @include('layouts.partials.icon', ['name' => 'money', 'class' => 'ui-icon ui-icon--xs'])
                    Registrar pago
                </button>
            </div>
        </form>
        @endif
        <details class="{{ $proxima ? 'mt-2' : '' }}">
            <summary class="small">Ver cronograma</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm small mb-0">
                    <thead><tr><th>Cuota</th><th>Fecha</th><th>Capital</th><th>Interés</th><th>Otros</th><th>Total</th><th>Saldo</th><th></th></tr></thead>
                    <tbody>
                    @foreach($deuda->cuotas as $cuota)
                    <tr class="{{ $cuota->pagada ? 'text-secondary' : '' }}">
                        <td>{{ $cuota->numero }}</td>
                        <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                        <td>@cop($cuota->capital_centavos)</td>
                        <td>@cop($cuota->interes_centavos)</td>
                        <td>@cop($cuota->seguro_centavos + $cuota->otros_cargos_centavos)</td>
                        <td>@cop($cuota->total_centavos)</td>
                        <td>@cop($cuota->saldo_capital_centavos)</td>
                        <td>{{ $cuota->pagada ? 'Pagada' : 'Pendiente' }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</article>
@endforeach
@foreach($tarjetas as $deuda)
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'credit-card', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $deuda->nombre }}</h2>
                    <small>{{ $deuda->entidad ?: 'Tarjeta de crédito' }}</small>
                </div>
                <strong class="account-card__amount">@cop($deuda->saldo_actual_centavos)</strong>
            </div>
            <div class="debt-meta">
                <span>Cupo @cop($deuda->cupo_centavos)</span>
                <span>{{ $deuda->cuotas_pendientes }} pendientes</span>
                <span class="status-pill">{{ ucfirst($deuda->estado) }}</span>
            </div>
            <p class="small text-secondary mb-0 mt-2">Gestiona compras y pagos en <a href="{{ route('app.tarjetas.index') }}">Tarjetas</a>.</p>
        </div>
    </div>
</article>
@endforeach
@if($prestamos->isEmpty() && $tarjetas->isEmpty())
    <div class="alert alert-light">Aún no tienes obligaciones registradas.</div>
@endif
</div>
@endsection
