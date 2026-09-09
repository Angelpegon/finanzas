@extends('layouts.app', [
    'title' => 'Registrar ingreso',
    'heading' => '¿Cuánto recibiste?',
    'subtitle' => 'Los ingresos recurrentes solo proyectan; no cambian tu saldo hasta que los recibas.',
    'backUrl' => route('app.situacion'),
    'backLabel' => 'Volver al resumen',
])
@section('content')
<div class="capture-flow">
    <p class="capture-hint"><strong>Ingreso real</strong> suma a tu cuenta. Márcalo como recurrente si solo quieres proyectarlo.</p>
    @include('layouts.partials.form-errors')
    <form method="POST" action="{{ route('app.ingresos.store') }}" class="dash-card form-panel" novalidate>
        @csrf
        <div class="money-hero">
            <label class="form-label" for="monto">Monto en pesos</label>
            <input id="monto" name="monto" type="text" inputmode="decimal" data-miles value="{{ old('monto') }}" class="form-control form-control-lg @error('monto') is-invalid @enderror" placeholder="0" autocomplete="off" required>
            @include('layouts.partials.field-error', ['name' => 'monto'])
        </div>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Detalle</p>
                <h2 class="form-section__title">Cuándo y de dónde viene</h2>
            </div>
            <div>
                <label class="form-label" for="fecha">Fecha</label>
                <input id="fecha" name="fecha" type="date" value="{{ old('fecha', now()->toDateString()) }}" class="form-control form-control-lg @error('fecha') is-invalid @enderror" required>
                @include('layouts.partials.field-error', ['name' => 'fecha'])
            </div>
            <div>
                <label class="form-label" for="categoria_id">Tipo de ingreso</label>
                <select id="categoria_id" name="categoria_id" class="form-select form-select-lg @error('categoria_id') is-invalid @enderror" required>
                    <option value="">Selecciona un tipo</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'categoria_id'])
            </div>
            <div>
                <label class="form-label" for="cuenta_liquida_id">Cuenta destino</label>
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
                <input id="descripcion" name="descripcion" value="{{ old('descripcion') }}" class="form-control form-control-lg @error('descripcion') is-invalid @enderror" placeholder="Ej. Pago de nómina">
                @include('layouts.partials.field-error', ['name' => 'descripcion'])
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Plan</p>
                <h2 class="form-section__title">¿Se repite?</h2>
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
                    <input id="recurrente" name="recurrente" value="1" type="checkbox" class="form-check-input" @checked(old('recurrente'))>
                    <label for="recurrente" class="form-check-label">Es un ingreso recurrente</label>
                </div>
                <small class="text-secondary">Se guardará como proyección, sin sumarlo al saldo recibido.</small>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar ingreso</button>
        </div>
    </form>
</div>
@endsection
