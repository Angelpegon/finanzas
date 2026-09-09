@extends('layouts.app', [
    'title' => 'Presupuesto',
    'heading' => 'Mi presupuesto',
    'subtitle' => 'Compara lo planeado, lo gastado y lo proyectado.',
])
@section('content')
<div class="capture-flow capture-flow--wide">
    @include('layouts.partials.form-errors')
    <form method="POST" action="{{ route('app.presupuestos.store') }}" class="dash-card form-panel" novalidate>
        @csrf
        <div class="form-section__head">
            <p class="form-section__eyebrow">Mes</p>
            <h2 class="form-section__title">Definir topes</h2>
        </div>

        <section class="form-section">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="anio">Año</label>
                    <input id="anio" name="anio" type="number" min="2020" max="2100" value="{{ old('anio', now()->year) }}" class="form-control form-control-lg @error('anio') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'anio'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="mes">Mes</label>
                    <select id="mes" name="mes" class="form-select form-select-lg @error('mes') is-invalid @enderror" required>
                        @foreach(range(1,12) as $mes)
                            <option value="{{ $mes }}" @selected(old('mes', now()->month) == $mes)>{{ \Carbon\Carbon::create()->month($mes)->locale('es')->monthName }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'mes'])
                </div>
            </div>
            <div>
                <label class="form-label">Alertar al alcanzar (%)</label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach([70,80,90,100] as $umbral)
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="umbrales[]" value="{{ $umbral }}" @checked(in_array($umbral, old('umbrales', $presupuesto?->umbrales_alerta ?? [70,80,90,100])))>
                            {{ $umbral }}%
                        </label>
                    @endforeach
                </div>
                @include('layouts.partials.field-error', ['name' => 'umbrales'])
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Categorías</p>
                <h2 class="form-section__title">Monto presupuestado</h2>
            </div>
            @include('layouts.partials.field-error', ['name' => 'lineas'])
            <div class="budget-lines">
                @foreach($categorias as $categoria)
                    @php($lineaExistente = $presupuesto?->lineas->firstWhere('categoria_id', $categoria->id))
                    <label class="budget-line">
                        <span class="budget-line__name">{{ $categoria->nombre }}</span>
                        <input name="lineas[]" type="text" inputmode="decimal" data-miles class="form-control budget-line__amount @error('lineas.'.$loop->index) is-invalid @enderror" value="{{ old('lineas.'.$loop->index, $lineaExistente ? \App\Support\Dinero::centavosAPesos($lineaExistente->tope_centavos) : '') }}" placeholder="$ 0" autocomplete="off">
                        <input type="hidden" name="categoria_ids[]" value="{{ $categoria->id }}">
                    </label>
                    @include('layouts.partials.field-error', ['name' => 'lineas.'.$loop->index])
                @endforeach
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar presupuesto</button>
        </div>
    </form>

    @if($presupuesto)
    <div class="list-block">
        <div class="list-block__head">
            <h2>Seguimiento · {{ \Carbon\Carbon::create($presupuesto->anio, $presupuesto->mes)->locale('es')->monthName }}</h2>
            <span>{{ $presupuesto->lineas->count() }} líneas</span>
        </div>
        <div class="card-stack">
        @foreach($presupuesto->lineas as $linea)
        <article class="account-card">
            <div class="account-card__main">
                <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'percent', 'class' => 'ui-icon ui-icon--sm'])</div>
                <div class="account-card__body">
                    <div class="account-card__head">
                        <div class="account-card__title">
                            <h2>{{ $linea->categoria->nombre }}</h2>
                            <small>Presupuesto @cop($linea->tope_centavos)</small>
                        </div>
                        <strong class="account-card__amount">@cop($linea->gasto_real_centavos)</strong>
                    </div>
                    <div class="progress progress--thin mt-3"><div class="progress-bar" style="width: {{ min(100, $linea->porcentaje_consumido) }}%"></div></div>
                    <div class="small text-secondary mt-2">Real vs proyección: @cop($linea->gasto_real_centavos) · @cop($linea->proyeccion_centavos) · {{ $linea->porcentaje_consumido }}%</div>
                    @if($linea->alertas)<div class="small text-danger mt-1">Alertas alcanzadas: {{ implode('%, ', $linea->alertas) }}%</div>@endif
                </div>
            </div>
        </article>
        @endforeach
        </div>
    </div>
    @else
        <div class="alert alert-light empty-state">Aún no hay presupuesto para el mes actual.</div>
    @endif
</div>
@endsection
