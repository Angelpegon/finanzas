@extends('layouts.app', [
    'title' => 'Calendario financiero',
    'heading' => 'Calendario',
    'subtitle' => 'Todo lo que ocurrió y lo que viene, en un solo lugar.',
])
@section('content')
<div class="dash-card form-panel mb-4">
    <div class="d-flex justify-content-between align-items-center gap-2">
        <a class="btn btn-outline-primary rounded-4" href="{{ route('app.calendario', ['anio' => $fecha->copy()->subMonth()->year, 'mes' => $fecha->copy()->subMonth()->month]) }}">‹ Anterior</a>
        <strong>{{ $fecha->locale('es')->monthName }} {{ $fecha->year }}</strong>
        <a class="btn btn-outline-primary rounded-4" href="{{ route('app.calendario', ['anio' => $fecha->copy()->addMonth()->year, 'mes' => $fecha->copy()->addMonth()->month]) }}">Siguiente ›</a>
    </div>
    <div class="d-flex flex-wrap gap-2"><span class="status-pill">REAL</span><span class="status-pill text-primary">PROYECTADO</span><span class="status-pill text-danger">VENCIDO</span></div>
</div>
<div class="card-stack">
@forelse($eventos as $evento)
    @php
        $iconoEvento = match ($evento['tipo']) {
            'ingreso' => 'arrow-up',
            'gasto' => 'arrow-down',
            'pago', 'cuota', 'limite_tarjeta' => 'banknote',
            'corte' => 'credit-card',
            default => 'circle',
        };
    @endphp
    <article class="account-card">
        <div class="account-card__main">
            <div class="account-card__icon">@include('layouts.partials.icon', ['name' => $iconoEvento, 'class' => 'ui-icon ui-icon--sm'])</div>
            <div class="account-card__body">
                <div class="account-card__head">
                    <div class="account-card__title">
                        <h2>{{ $evento['descripcion'] }}</h2>
                        <small>{{ \Carbon\Carbon::parse($evento['fecha'])->format('d/m/Y') }} · {{ ucfirst($evento['tipo']) }}</small>
                    </div>
                    <strong class="account-card__amount {{ $evento['tipo'] === 'ingreso' ? 'text-success' : '' }}">@if($evento['monto_centavos'] > 0)@cop($evento['monto_centavos'])@else — @endif</strong>
                </div>
                <div class="mt-2"><span class="status-pill {{ $evento['estado'] === 'vencido' ? 'text-danger' : ($evento['estado'] === 'proyectado' ? 'text-primary' : '') }}">{{ strtoupper($evento['estado']) }}</span></div>
            </div>
        </div>
    </article>
@empty
    <div class="alert alert-light">No hay eventos para este mes.</div>
@endforelse
</div>
@endsection
