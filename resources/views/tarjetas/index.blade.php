@extends('layouts.app', [
    'title' => 'Tarjetas',
    'heading' => 'Mis tarjetas',
    'subtitle' => 'Compra, financia y paga con trazabilidad.',
    'actionUrl' => route('app.tarjetas.create'),
    'actionLabel' => 'Nueva tarjeta',
])
@section('content')
@php
    $errCompra = $errors->compra;
    $errPagoTarjeta = $errors->pago_tarjeta;
    $oldCompra = $errCompra->any();
@endphp
@include('layouts.partials.form-errors', ['bag' => 'pago_tarjeta'])
<div class="card-stack">
@forelse($tarjetas as $tarjeta)
<article class="account-card">
    <div class="account-card__main">
        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'credit-card', 'class' => 'ui-icon ui-icon--sm'])</div>
        <div class="account-card__body">
            <div class="account-card__head">
                <div class="account-card__title">
                    <h2>{{ $tarjeta->nombre }}</h2>
                    <small>{{ $tarjeta->entidad ?: 'Tarjeta de crédito' }}</small>
                </div>
                <strong class="account-card__amount">@cop($tarjeta->saldo_actual_centavos)</strong>
            </div>
            <div class="debt-meta">
                <span>Disponible @cop($tarjeta->cupo_disponible_centavos)</span>
                <span>Cupo @cop($tarjeta->cupo_centavos)</span>
            </div>
            <div class="small text-secondary mt-2">Pago mínimo: <strong>@cop($tarjeta->pago_minimo_centavos)</strong> · Pago total: <strong>@cop($tarjeta->pago_total_centavos)</strong></div>
        </div>
    </div>
    <div class="account-card__footer">
        <details @if($errPagoTarjeta->any() && (int) old('tarjeta_credito_id') === (int) $tarjeta->id) open @endif>
            <summary class="small">Ver compras y cuotas</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm small mb-0">
                    <thead><tr><th>Compra</th><th>Fecha</th><th>Monto</th><th>Cuotas</th></tr></thead>
                    <tbody>
                    @foreach($tarjeta->compras as $compra)
                        <tr>
                            <td>{{ $compra->descripcion }}</td>
                            <td>{{ $compra->fecha->format('d/m/Y') }}</td>
                            <td>@cop($compra->monto_centavos)</td>
                            <td>{{ $compra->cuotas }} ({{ $compra->cuotasProgramadas->where('pagada', false)->count() }} pendientes)</td>
                        </tr>
                        @foreach($compra->cuotasProgramadas as $cuota)
                        <tr class="text-secondary">
                            <td>↳ Cuota {{ $cuota->numero }}</td>
                            <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                            <td>@cop($cuota->capital_centavos + $cuota->interes_centavos)</td>
                            <td>
                                {{ $cuota->pagada ? 'Pagada' : 'Pendiente' }}
                                @unless($cuota->pagada)
                                @php $estePagoCuota = $errPagoTarjeta->any() && (int) old('cuota_tarjeta_id') === (int) $cuota->id; @endphp
                                <form method="POST" action="{{ route('app.tarjetas.pagos.store') }}" class="d-inline-flex flex-wrap align-items-center gap-1 ms-2" novalidate>
                                    @csrf
                                    <input type="hidden" name="tarjeta_credito_id" value="{{ $tarjeta->id }}">
                                    <input type="hidden" name="cuota_tarjeta_id" value="{{ $cuota->id }}">
                                    <input type="hidden" name="fecha" value="{{ now()->toDateString() }}">
                                    <select name="cuenta_liquida_id" class="form-select form-select-sm d-inline-block w-auto @if($estePagoCuota) @error('cuenta_liquida_id', 'pago_tarjeta') is-invalid @enderror @endif" required>
                                        <option value="">Cuenta</option>
                                        @foreach($cuentas as $cuenta)
                                            <option value="{{ $cuenta->id }}" @selected($estePagoCuota && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                                        @endforeach
                                    </select>
                                    <button class="card-btn card-btn--primary" type="submit">
                                        @include('layouts.partials.icon', ['name' => 'money', 'class' => 'ui-icon ui-icon--xs'])
                                        Pagar
                                    </button>
                                    @if($estePagoCuota)
                                        @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'pago_tarjeta'])
                                    @endif
                                </form>
                                @endunless
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</article>
@empty
    <div class="alert alert-light">Aún no tienes tarjetas registradas.</div>
@endforelse
</div>
@if($tarjetas->isNotEmpty())
<form method="POST" action="{{ route('app.tarjetas.compras.store') }}" class="dash-card form-panel mt-4" novalidate>
    @csrf
    <h2 class="h5 mb-0">Registrar compra</h2>
    @include('layouts.partials.form-errors', ['bag' => 'compra'])
    <div>
        <label class="form-label" for="tarjeta_credito_id">Tarjeta</label>
        <select id="tarjeta_credito_id" name="tarjeta_credito_id" class="form-select @error('tarjeta_credito_id', 'compra') is-invalid @enderror" required>
            <option value="">Tarjeta</option>
            @foreach($tarjetas as $tarjeta)
                <option value="{{ $tarjeta->id }}" @selected($oldCompra && old('tarjeta_credito_id') == $tarjeta->id)>{{ $tarjeta->nombre }}</option>
            @endforeach
        </select>
        @include('layouts.partials.field-error', ['name' => 'tarjeta_credito_id', 'bag' => 'compra'])
    </div>
    <div>
        <label class="form-label" for="descripcion_compra">Descripción</label>
        <input id="descripcion_compra" name="descripcion" value="{{ $oldCompra ? old('descripcion') : '' }}" class="form-control @error('descripcion', 'compra') is-invalid @enderror" placeholder="Descripción" required>
        @include('layouts.partials.field-error', ['name' => 'descripcion', 'bag' => 'compra'])
    </div>
    <div>
        <label class="form-label" for="monto_compra">Monto</label>
        <input id="monto_compra" name="monto" data-miles inputmode="decimal" value="{{ $oldCompra ? old('monto') : '' }}" class="form-control @error('monto', 'compra') is-invalid @enderror" placeholder="Monto" required>
        @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'compra'])
    </div>
    <div>
        <label class="form-label" for="fecha_compra">Fecha</label>
        <input id="fecha_compra" name="fecha" type="date" value="{{ $oldCompra ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control @error('fecha', 'compra') is-invalid @enderror" required>
        @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'compra'])
    </div>
    <div>
        <label class="form-label" for="categoria_compra">Categoría</label>
        <select id="categoria_compra" name="categoria_id" class="form-select @error('categoria_id', 'compra') is-invalid @enderror" required>
            <option value="">Categoría</option>
            @foreach($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected($oldCompra && old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
            @endforeach
        </select>
        @include('layouts.partials.field-error', ['name' => 'categoria_id', 'bag' => 'compra'])
    </div>
    <div class="row g-2">
        <div class="col-6">
            <label class="form-label" for="cuotas">Cuotas</label>
            <input id="cuotas" name="cuotas" type="number" min="1" max="60" value="{{ $oldCompra ? old('cuotas', 1) : 1 }}" class="form-control @error('cuotas', 'compra') is-invalid @enderror" required>
            @include('layouts.partials.field-error', ['name' => 'cuotas', 'bag' => 'compra'])
        </div>
        <div class="col-6">
            <label class="form-label" for="interes">Interés mensual %</label>
            <input id="interes" name="interes" type="number" min="0" step="0.01" value="{{ $oldCompra ? old('interes', 0) : 0 }}" class="form-control @error('interes', 'compra') is-invalid @enderror">
            @include('layouts.partials.field-error', ['name' => 'interes', 'bag' => 'compra'])
        </div>
    </div>
    <button class="btn btn-primary rounded-4" type="submit">Guardar compra financiada</button>
</form>
@endif
@endsection
