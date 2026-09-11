@extends('layouts.app', [
    'title' => 'Gastos',
    'heading' => 'Gastos',
    'subtitle' => 'Salidas reales. Las correcciones se hacen con reverso contable.',
])
@section('content')
@include('layouts.partials.form-errors')

<div class="page-toolbar">
    <a class="btn btn-primary btn-lg" href="{{ route('app.gastos.create') }}">
        @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon ui-icon--sm'])
        Nuevo gasto
    </a>
</div>

<div class="list-block list-block--flush">
    <div class="list-block__head">
        <h2>Registrados</h2>
        <span>{{ $gastos->total() }}</span>
    </div>
    <div class="card-stack">
        @forelse($gastos as $gasto)
            @php $corregido = in_array((int) $gasto->id, $idsRevertidos, true); @endphp
            <article class="account-card">
                <div class="account-card__main">
                    <div class="account-card__icon dash-list__icon--red">@include('layouts.partials.icon', ['name' => 'arrow-down', 'class' => 'ui-icon ui-icon--sm'])</div>
                    <div class="account-card__body">
                        <div class="account-card__head">
                            <div class="account-card__title">
                                <h2>{{ $gasto->descripcion ?: ($gasto->categoria?->nombre ?: 'Gasto') }}</h2>
                                <small>
                                    {{ $gasto->fecha?->format('d/m/Y') }}
                                    @if($gasto->categoria) · {{ $gasto->categoria->nombre }}@endif
                                    @if($gasto->tipo_gasto) · {{ ucfirst($gasto->tipo_gasto) }}@endif
                                    @if($gasto->cuentaLiquida) · {{ $gasto->cuentaLiquida->nombre }}@endif
                                    @if($corregido) · <span class="text-danger">Corregido</span>@endif
                                </small>
                            </div>
                            <strong class="account-card__amount {{ $corregido ? 'text-secondary' : 'text-danger' }}">
                                @if($corregido)<s>@endif @cop($gasto->monto_centavos) @if($corregido)</s>@endif
                            </strong>
                        </div>
                        @unless($corregido)
                            <div class="account-card__actions mt-2">
                                <form method="POST" action="{{ route('app.gastos.corregir', $gasto) }}" class="d-inline"
                                    data-swal-confirm
                                    data-swal-title="¿Corregir este gasto?"
                                    data-swal-text="Se registrará un reverso en el libro. El saldo sube; el historial no se borra."
                                    data-swal-icon="warning"
                                    data-swal-confirm-text="Corregir">
                                    @csrf
                                    <input type="hidden" name="motivo" value="Corrección de gasto #{{ $gasto->id }}">
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
            <div class="alert alert-light empty-state">Aún no hay gastos reales. <a href="{{ route('app.gastos.create') }}">Registrar uno</a>.</div>
        @endforelse
    </div>
</div>

@if($gastos->hasPages())
    <div class="mt-3">{{ $gastos->links() }}</div>
@endif
@endsection
