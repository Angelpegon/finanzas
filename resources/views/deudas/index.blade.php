@extends('layouts.app', [
    'title' => 'Deudas',
    'heading' => 'Mis deudas',
    'subtitle' => 'Todo lo que debes, con claridad. Los pagos se corrigen con reverso contable.',
])
@section('content')
@php
    $errPagoPrestamo = $errors->pago_prestamo;
    $prestamoConError = $errPagoPrestamo->any() ? (int) old('prestamo_id') : null;
@endphp
@include('layouts.partials.form-errors')
@include('layouts.partials.form-errors', ['bag' => 'pago_prestamo'])

<div class="page-toolbar">
    <a class="btn btn-primary btn-lg" href="{{ route('app.deudas.create') }}">
        @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon ui-icon--sm'])
        Nueva deuda
    </a>
</div>

<div class="list-block list-block--flush">
<div class="list-block__head">
    <h2>Obligaciones</h2>
    <span>{{ $prestamos->count() + $tarjetas->count() }}</span>
</div>
<div class="card-stack">
@forelse($prestamos as $deuda)
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
                    <small>{{ $deuda->entidad ?: 'Obligación' }} · {{ str_replace('_', ' ', $deuda->tipo_obligacion) }} · día {{ $deuda->dia_pago }}</small>
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
            <input type="hidden" name="idempotency_key" value="{{ $esteForm ? old('idempotency_key', $idempotencyKeysPago[$deuda->id] ?? '') : ($idempotencyKeysPago[$deuda->id] ?? '') }}">
            <div class="col-md-3">
                <label class="form-label small mb-1">Pagar cuota {{ $proxima->numero }}</label>
                <input name="monto" data-miles inputmode="decimal" class="form-control @if($esteForm) @error('monto', 'pago_prestamo') is-invalid @enderror @endif" value="{{ $esteForm ? old('monto', $montoCuotaPrefijo) : $montoCuotaPrefijo }}" required>
                @if($esteForm)
                    @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'pago_prestamo'])
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Fecha</label>
                <input name="fecha" type="date" value="{{ $esteForm ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control @if($esteForm) @error('fecha', 'pago_prestamo') is-invalid @enderror @endif" required>
                @if($esteForm)
                    @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'pago_prestamo'])
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Cuenta de pago</label>
                <select name="cuenta_liquida_id" class="form-select @if($esteForm) @error('cuenta_liquida_id', 'pago_prestamo') is-invalid @enderror @endif">
                    @foreach($cuentasPago as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected(($esteForm ? old('cuenta_liquida_id', $deuda->cuenta_liquida_id) : $deuda->cuenta_liquida_id) == $cuenta->id)>{{ $cuenta->nombre }}</option>
                    @endforeach
                </select>
                @if($esteForm)
                    @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'pago_prestamo'])
                @endif
            </div>
            <div class="col-md-3">
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
@empty
    @if($tarjetas->isEmpty())
        <div class="alert alert-light empty-state">Aún no tienes obligaciones. <a href="{{ route('app.deudas.create') }}">Registrar una deuda</a> o una <a href="{{ route('app.tarjetas.create') }}">tarjeta</a>.</div>
    @endif
@endforelse
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
</div>
</div>

@if($pagosRecientes->isNotEmpty())
<div class="list-block mt-4">
    <div class="list-block__head">
        <h2>Pagos recientes</h2>
        <span>{{ $pagosRecientes->count() }}</span>
    </div>
    <div class="card-stack">
        @foreach($pagosRecientes as $pago)
            @php
                $corregido = in_array((int) $pago->id, $idsRevertidos, true);
                $esUltimo = in_array((int) $pago->id, $ultimoPagoPorPrestamo, true);
            @endphp
            <article class="account-card">
                <div class="account-card__main">
                    <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'money', 'class' => 'ui-icon ui-icon--sm'])</div>
                    <div class="account-card__body">
                        <div class="account-card__head">
                            <div class="account-card__title">
                                <h2>{{ $pago->prestamo?->nombre ?: 'Préstamo' }}</h2>
                                <small>
                                    {{ $pago->fecha?->format('d/m/Y') }}
                                    @if($pago->cuentaLiquida) · {{ $pago->cuentaLiquida->nombre }}@endif
                                    @if($pago->extraordinario) · Abono extra @endif
                                    @if($corregido) · <span class="text-danger">Corregido</span>@endif
                                </small>
                            </div>
                            <strong class="account-card__amount {{ $corregido ? 'text-secondary' : '' }}">
                                @if($corregido)<s>@endif @cop($pago->monto_centavos) @if($corregido)</s>@endif
                            </strong>
                        </div>
                        @if(! $corregido && $esUltimo)
                            <div class="account-card__actions mt-2">
                                <form method="POST" action="{{ route('app.deudas.pagos.corregir', $pago) }}" class="d-inline"
                                    data-swal-confirm
                                    data-swal-title="¿Corregir este pago?"
                                    data-swal-text="Se registra un reverso en el libro y se reabre la cuota. Si hubo abono extra, se restaura el cronograma anterior."
                                    data-swal-icon="warning"
                                    data-swal-confirm-text="Corregir">
                                    @csrf
                                    <input type="hidden" name="motivo" value="Corrección de pago #{{ $pago->id }}">
                                    <button class="card-btn card-btn--warn" type="submit">
                                        @include('layouts.partials.icon', ['name' => 'ban', 'class' => 'ui-icon ui-icon--xs'])
                                        Corregir
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>
@endif
@endsection
