@extends('layouts.app', [
    'title' => 'Registrar gasto',
    'heading' => '¿En qué gastaste?',
    'subtitle' => 'Un gasto real descuenta tu cuenta; uno proyectado solo ayuda a planear.',
    'backUrl' => route('app.situacion'),
    'backLabel' => 'Volver al resumen',
])
@section('content')
<div class="capture-flow">
    <p class="capture-hint"><strong>Gasto real</strong> mueve el libro hoy. Activa “proyectado” si solo quieres anticiparlo en el plan.</p>
    @include('layouts.partials.form-errors')
    <form method="POST" action="{{ route('app.gastos.store') }}" class="dash-card form-panel" novalidate>
        @csrf
        <div class="money-hero">
            <label class="form-label" for="monto">Monto en pesos</label>
            <input id="monto" name="monto" type="text" inputmode="decimal" data-miles value="{{ old('monto') }}" class="form-control form-control-lg @error('monto') is-invalid @enderror" placeholder="0" autocomplete="off" required>
            @include('layouts.partials.field-error', ['name' => 'monto'])
        </div>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Clasificación</p>
                <h2 class="form-section__title">Categoría y cuenta</h2>
            </div>
            <div>
                <label class="form-label" for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" value="{{ old('fecha', now()->toDateString()) }}" class="form-control form-control-lg @error('fecha') is-invalid @enderror" required>
                @include('layouts.partials.field-error', ['name' => 'fecha'])
            </div>
            <div>
                <label class="form-label" for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id" class="form-select form-select-lg @error('categoria_id') is-invalid @enderror" required>
                    <option value="">Selecciona una categoría</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'categoria_id'])
            </div>
            <div>
                <label class="form-label" for="tipo_gasto">Tipo</label>
                <select id="tipo_gasto" name="tipo_gasto" class="form-select form-select-lg @error('tipo_gasto') is-invalid @enderror" required>
                    @foreach(['fijo'=>'Fijo','variable'=>'Variable','extraordinario'=>'Extraordinario'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('tipo_gasto', 'variable') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'tipo_gasto'])
            </div>
            <div>
                <label class="form-label" for="cuenta_liquida_id">Cuenta de pago</label>
                <select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id') is-invalid @enderror" required>
                    <option value="">Selecciona una cuenta</option>
                    @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected(old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id'])
            </div>
            <div>
                <label class="form-label" for="descripcion">Descripción <span class="text-secondary">(opcional)</span></label>
                <input id="descripcion" name="descripcion" value="{{ old('descripcion') }}" class="form-control form-control-lg @error('descripcion') is-invalid @enderror" placeholder="Ej. Mercado del mes">
                @include('layouts.partials.field-error', ['name' => 'descripcion'])
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Plan</p>
                <h2 class="form-section__title">¿Se repite o es proyección?</h2>
            </div>
            <div>
                <label class="form-label" for="periodicidad">Periodicidad</label>
                <select id="periodicidad" name="periodicidad" class="form-select form-select-lg @error('periodicidad') is-invalid @enderror" required>
                    @foreach(['unico'=>'Único','diario'=>'Diario','semanal'=>'Semanal','quincenal'=>'Quincenal','mensual'=>'Mensual','anual'=>'Anual'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('periodicidad', 'unico') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'periodicidad'])
            </div>
            <div class="form-switch-card">
                <div class="form-check form-switch">
                    <input id="proyectado" name="proyectado" value="1" type="checkbox" class="form-check-input" @checked(old('proyectado'))>
                    <label for="proyectado" class="form-check-label">Es un gasto proyectado</label>
                </div>
                <small class="text-secondary">No afecta saldos ni movimientos reales.</small>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar gasto</button>
        </div>
    </form>
</div>
@endsection
