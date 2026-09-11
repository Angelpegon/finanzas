@extends('layouts.app', [
    'title' => 'Editar cuenta',
    'heading' => 'Editar cuenta',
    'subtitle' => $cuenta->nombre,
    'backUrl' => route('app.cuentas.show', $cuenta),
    'backLabel' => 'Volver al detalle',
])
@section('content')
<div class="capture-flow">
    <p class="capture-hint">Puedes corregir nombre, tipo e institución. El <strong>saldo no se edita</strong>; solo cambia con movimientos del libro.</p>
    @include('layouts.partials.form-errors')
    <form method="POST" action="{{ route('app.cuentas.update', $cuenta) }}" class="dash-card form-panel" novalidate>
        @csrf
        @method('PUT')
        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Identidad</p>
                <h2 class="form-section__title">Datos de la cuenta</h2>
            </div>
            <div>
                <label class="form-label" for="nombre">Nombre</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $cuenta->nombre) }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" required>
                @include('layouts.partials.field-error', ['name' => 'nombre'])
            </div>
            <div>
                <label class="form-label" for="tipo">Tipo</label>
                <select id="tipo" name="tipo" class="form-select form-select-lg @error('tipo') is-invalid @enderror" required>
                    @foreach($tipos as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('tipo', $cuenta->tipo) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'tipo'])
            </div>
            <div>
                <label class="form-label" for="institucion">Institución <span class="text-secondary">(opcional)</span></label>
                <input id="institucion" name="institucion" value="{{ old('institucion', $cuenta->institucion) }}" class="form-control form-control-lg @error('institucion') is-invalid @enderror">
                @include('layouts.partials.field-error', ['name' => 'institucion'])
            </div>
            <div>
                <label class="form-label" for="numero_cuenta_enmascarado">Número enmascarado <span class="text-secondary">(opcional)</span></label>
                <input id="numero_cuenta_enmascarado" name="numero_cuenta_enmascarado" value="{{ old('numero_cuenta_enmascarado', $cuenta->numero_cuenta_enmascarado) }}" class="form-control form-control-lg @error('numero_cuenta_enmascarado') is-invalid @enderror" placeholder="**** 1234">
                @include('layouts.partials.field-error', ['name' => 'numero_cuenta_enmascarado'])
            </div>
        </section>
        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
