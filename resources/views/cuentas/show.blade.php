@extends('layouts.app', [
    'title' => $cuenta->nombre,
    'heading' => $cuenta->nombre,
    'subtitle' => $tipoEtiqueta.($cuenta->institucion ? ' · '.$cuenta->institucion : ''),
    'backUrl' => route('app.cuentas.index'),
    'backLabel' => 'Volver a cuentas',
])
@section('content')
<div class="account-detail">
    <section class="dash-card account-detail__summary">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <p class="text-secondary mb-1">Saldo actual</p>
                <p class="account-detail__saldo mb-0">@cop($saldo)</p>
                @if($cuenta->numero_cuenta_enmascarado)
                    <small class="text-secondary">{{ $cuenta->numero_cuenta_enmascarado }}</small>
                @endif
                <p class="small text-secondary mt-2 mb-0">
                    Estado:
                    <strong>{{ $cuenta->estado === 'inactiva' ? 'Archivada' : 'Activa' }}</strong>
                </p>
            </div>
            <div class="account-card__actions">
                <a class="card-btn card-btn--muted" href="{{ route('app.cuentas.edit', $cuenta) }}">Editar</a>
                <a class="card-btn card-btn--ok" href="{{ route('app.ingresos.create') }}">Nuevo ingreso</a>
                <a class="card-btn card-btn--warn" href="{{ route('app.gastos.create') }}">Nuevo gasto</a>
            </div>
        </div>
    </section>

    <section class="dash-card mt-3">
        <div class="dash-card__head">
            <h2>Movimientos de tesorería</h2>
            <span class="text-secondary">{{ $hechos->count() }}</span>
        </div>
        <div class="dash-list">
            @forelse($hechos as $hecho)
                <div class="dash-list__row">
                    <span class="dash-list__icon {{ $hecho->tipo->value === 'ingreso' || $hecho->tipo->value === 'apertura' ? 'dash-list__icon--green' : ($hecho->tipo->value === 'gasto' || $hecho->tipo->value === 'cierre' ? 'dash-list__icon--red' : '') }}">
                        @include('layouts.partials.icon', ['name' => $hecho->tipo->value === 'ingreso' || $hecho->tipo->value === 'apertura' ? 'arrow-up' : ($hecho->tipo->value === 'transferencia' || $hecho->tipo->value === 'aporte_meta' ? 'exchange' : 'arrow-down'), 'class' => 'ui-icon ui-icon--sm'])
                    </span>
                    <div class="dash-list__body">
                        <strong>{{ $hecho->descripcion ?: ucfirst(str_replace('_', ' ', $hecho->tipo->value)) }}</strong>
                        <small>
                            {{ ucfirst(str_replace('_', ' ', $hecho->tipo->value)) }}
                            @if($hecho->categoria) · {{ $hecho->categoria->nombre }}@endif
                            · {{ $hecho->fecha?->format('d/m/Y') }}
                        </small>
                    </div>
                    <strong class="dash-list__amount">@cop($hecho->monto_centavos)</strong>
                </div>
            @empty
                <p class="text-secondary mb-0">Sin movimientos de tesorería en esta cuenta.</p>
            @endforelse
        </div>
    </section>

    <section class="dash-card mt-3">
        <div class="dash-card__head">
            <h2>Pagos registrados</h2>
            <span class="text-secondary">{{ $pagos->count() }}</span>
        </div>
        <div class="dash-list">
            @forelse($pagos as $pago)
                <div class="dash-list__row">
                    <span class="dash-list__icon dash-list__icon--amber">@include('layouts.partials.icon', ['name' => 'banknote', 'class' => 'ui-icon ui-icon--sm'])</span>
                    <div class="dash-list__body">
                        <strong>{{ $pago->destino ?: ($pago->descripcion ?: 'Pago') }}</strong>
                        <small>
                            {{ ucfirst($pago->tipo) }}
                            @if($pago->referencia) · {{ $pago->referencia }}@endif
                            · {{ $pago->fecha?->format('d/m/Y') }}
                        </small>
                    </div>
                    <strong class="dash-list__amount text-danger">@cop($pago->monto_centavos)</strong>
                </div>
            @empty
                <p class="text-secondary mb-0">Sin pagos desde esta cuenta.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
