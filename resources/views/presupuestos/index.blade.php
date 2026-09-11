@extends('layouts.app', [
    'title' => 'Presupuesto',
    'heading' => 'Mi presupuesto',
    'subtitle' => 'Topes por categoría. El % solo mira gasto real del mes.',
])
@section('content')
@php
    $periodo = \Carbon\Carbon::create($anio, $mes, 1)->locale('es');
    $prev = $periodo->copy()->subMonth();
    $next = $periodo->copy()->addMonth();
    $errCat = $errors->categoria;
@endphp

@include('layouts.partials.form-errors')
@include('layouts.partials.form-errors', ['bag' => 'categoria'])

<div class="page-toolbar d-flex flex-wrap gap-2 align-items-center">
    <a class="btn btn-outline-secondary" href="{{ route('app.presupuestos.index', ['anio' => $prev->year, 'mes' => $prev->month]) }}">← {{ $prev->translatedFormat('M Y') }}</a>
    <strong class="px-2">{{ ucfirst($periodo->translatedFormat('F Y')) }}</strong>
    <a class="btn btn-outline-secondary" href="{{ route('app.presupuestos.index', ['anio' => $next->year, 'mes' => $next->month]) }}">{{ $next->translatedFormat('M Y') }} →</a>
</div>

<div class="capture-flow capture-flow--wide">
    @if($categorias->isEmpty())
        <div class="alert alert-light empty-state mb-4">
            No tienes categorías de gasto. Crea una abajo para poder presupuestar.
        </div>
    @else
    <form method="POST" action="{{ route('app.presupuestos.store') }}" class="dash-card form-panel" novalidate
        data-swal-confirm
        data-swal-title="¿Guardar presupuesto de {{ $periodo->translatedFormat('F Y') }}?"
        data-swal-text="Se reemplazan las líneas de ese mes. Las categorías en blanco se quitan del presupuesto."
        data-swal-icon="warning"
        data-swal-confirm-text="Guardar">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
        <input type="hidden" name="anio" value="{{ $anio }}">
        <input type="hidden" name="mes" value="{{ $mes }}">

        <div class="form-section__head">
            <p class="form-section__eyebrow">{{ ucfirst($periodo->translatedFormat('F Y')) }}</p>
            <h2 class="form-section__title">Definir topes</h2>
        </div>
        <p class="small text-secondary mb-3">Deja en blanco una categoría para no incluirla (o quitarla). El seguimiento de abajo es de este mismo mes.</p>

        <section class="form-section">
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
                <p class="form-section__eyebrow">Categorías de gasto</p>
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
    @endif

    <div class="dash-card form-panel mt-4">
        <div class="form-section__head">
            <p class="form-section__eyebrow">Catálogo</p>
            <h2 class="form-section__title">Categorías</h2>
        </div>
        <p class="small text-secondary mb-3">Las de gasto alimentan el presupuesto. Las de ingreso sirven en Ingresos.</p>

        <form method="POST" action="{{ route('app.categorias.store') }}" class="mb-4" novalidate>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKeyCategoria) }}">
            <input type="hidden" name="anio" value="{{ $anio }}">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="cat_nombre">Nueva categoría</label>
                    <input id="cat_nombre" name="nombre" value="{{ $errCat->any() ? old('nombre') : '' }}" class="form-control form-control-lg @error('nombre', 'categoria') is-invalid @enderror" placeholder="Ej. Mascotas" required>
                    @include('layouts.partials.field-error', ['name' => 'nombre', 'bag' => 'categoria'])
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cat_tipo">Tipo</label>
                    <select id="cat_tipo" name="tipo" class="form-select form-select-lg @error('tipo', 'categoria') is-invalid @enderror" required>
                        <option value="gasto" @selected(($errCat->any() ? old('tipo', 'gasto') : 'gasto') === 'gasto')>Gasto</option>
                        <option value="ingreso" @selected(($errCat->any() ? old('tipo') : '') === 'ingreso')>Ingreso</option>
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'tipo', 'bag' => 'categoria'])
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-lg w-100" type="submit">Agregar</button>
                </div>
            </div>
        </form>

        <div class="card-stack">
            @foreach($categorias as $categoria)
                <article class="account-card">
                    <form method="POST" action="{{ route('app.categorias.update', $categoria) }}" class="account-card__main w-100">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <input type="hidden" name="mes" value="{{ $mes }}">
                        <div class="account-card__body w-100">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-8">
                                    <label class="form-label visually-hidden" for="edit_cat_{{ $categoria->id }}">Nombre</label>
                                    <input id="edit_cat_{{ $categoria->id }}" name="nombre" value="{{ $categoria->nombre }}" class="form-control" required>
                                </div>
                                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                                    <span class="small text-secondary align-self-center">Gasto</span>
                                    <button class="card-btn card-btn--primary" type="submit">Guardar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </article>
            @endforeach
        </div>
        @if($categoriasIngreso->isNotEmpty())
            <p class="small text-secondary mt-3 mb-2">Ingreso</p>
            <div class="card-stack">
                @foreach($categoriasIngreso as $categoria)
                    <article class="account-card">
                        <form method="POST" action="{{ route('app.categorias.update', $categoria) }}" class="account-card__main w-100">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <input type="hidden" name="mes" value="{{ $mes }}">
                            <div class="account-card__body w-100">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-8">
                                        <input name="nombre" value="{{ $categoria->nombre }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                                        <span class="small text-secondary align-self-center">Ingreso</span>
                                        <button class="card-btn card-btn--primary" type="submit">Guardar</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    @if($presupuesto)
    <div class="list-block mt-4">
        <div class="list-block__head">
            <h2>Seguimiento · {{ ucfirst($periodo->translatedFormat('F Y')) }}</h2>
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
                    <div class="small text-secondary mt-2">
                        Real @cop($linea->gasto_real_centavos) · {{ $linea->porcentaje_consumido }}% del tope
                    </div>
                    <div class="small text-secondary mt-1">
                        Recurrente proyectado (no suma al %): @cop($linea->proyeccion_centavos)
                        · pendiente est. @cop($linea->recurrente_pendiente_centavos)
                    </div>
                    @if(!empty($linea->alertas))<div class="small text-danger mt-1">Alertas: {{ implode('%, ', $linea->alertas) }}%</div>@endif
                </div>
            </div>
        </article>
        @endforeach
        </div>
    </div>
    @else
        <div class="alert alert-light empty-state mt-4">Aún no hay presupuesto para {{ $periodo->translatedFormat('F Y') }}.</div>
    @endif
</div>
@endsection
