@extends('layouts.app', [
    'title' => 'Ingresos',
    'heading' => 'Ingresos',
    'subtitle' => 'Cobros reales. Las correcciones se hacen con reverso contable.',
])
@section('content')
@include('layouts.partials.form-errors')

<div class="page-toolbar">
    <a class="btn btn-primary btn-lg" href="{{ route('app.ingresos.create') }}">
        @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon ui-icon--sm'])
        Nuevo ingreso
    </a>
</div>

<div class="list-block list-block--flush">
    <div class="list-block__head">
        <h2>Registrados</h2>
        <span>{{ $ingresos->total() }}</span>
    </div>
    <div class="card-stack">
        @forelse($ingresos as $ingreso)
            @php $corregido = in_array((int) $ingreso->id, $idsRevertidos, true); @endphp
            <article class="account-card">
                <div class="account-card__main">
                    <div class="account-card__icon dash-list__icon--green">@include('layouts.partials.icon', ['name' => 'arrow-up', 'class' => 'ui-icon ui-icon--sm'])</div>
                    <div class="account-card__body">
                        <div class="account-card__head">
                            <div class="account-card__title">
                                <h2>{{ $ingreso->descripcion ?: ($ingreso->categoria?->nombre ?: 'Ingreso') }}</h2>
                                <small>
                                    {{ $ingreso->fecha?->format('d/m/Y') }}
                                    @if($ingreso->categoria) · {{ $ingreso->categoria->nombre }}@endif
                                    @if($ingreso->cuentaLiquida) · {{ $ingreso->cuentaLiquida->nombre }}@endif
                                    @if($corregido) · <span class="text-danger">Corregido</span>@endif
                                </small>
                            </div>
                            <strong class="account-card__amount {{ $corregido ? 'text-secondary' : 'text-success' }}">
                                @if($corregido)<s>@endif @cop($ingreso->monto_centavos) @if($corregido)</s>@endif
                            </strong>
                        </div>
                        @unless($corregido)
                            <div class="account-card__actions mt-2">
                                <form method="POST" action="{{ route('app.ingresos.corregir', $ingreso) }}" class="d-inline"
                                    data-swal-confirm
                                    data-swal-title="¿Corregir este ingreso?"
                                    data-swal-text="Se registrará un reverso en el libro. El saldo baja; el historial no se borra."
                                    data-swal-icon="warning"
                                    data-swal-confirm-text="Corregir">
                                    @csrf
                                    <input type="hidden" name="motivo" value="Corrección de ingreso #{{ $ingreso->id }}">
                                    <button class="card-btn card-btn--warn" type="submit">
                                        @include('layouts.partials.icon', ['name' => 'ban', 'class' => 'ui-icon ui-icon--xs'])
                                        Corregir
                                    </button>
                                </form>
                            </div>
                        @endunless
                    </div>
                </div>
            </article>
        @empty
            <div class="alert alert-light empty-state">Aún no hay ingresos reales. <a href="{{ route('app.ingresos.create') }}">Registrar uno</a>.</div>
        @endforelse
    </div>
</div>

@if($ingresos->hasPages())
    <div class="mt-3">{{ $ingresos->links() }}</div>
@endif
@endsection
