@extends('layouts.app', [
    'title' => 'Agregar cuenta',
    'heading' => '¿Dónde guardas tu dinero?',
    'subtitle' => 'Registra una cuenta para tener una vista completa.',
    'backUrl' => route('app.cuentas.index'),
    'backLabel' => 'Volver a cuentas',
])
@section('content')
@include('layouts.partials.form-errors')
<form method="POST" action="{{ route('app.cuentas.store') }}" class="dash-card form-panel" novalidate>
    @csrf
    <div>
        <label class="form-label" for="nombre">Nombre</label>
        <input id="nombre" name="nombre" value="{{ old('nombre') }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" placeholder="Ej. Cuenta nómina" required>
        @include('layouts.partials.field-error', ['name' => 'nombre'])
    </div>
    <div>
        <label class="form-label" for="tipo">Tipo</label>
        <select id="tipo" name="tipo" class="form-select form-select-lg @error('tipo') is-invalid @enderror" required>
            <option value="">Selecciona un tipo</option>
            @foreach(['bancaria'=>'Cuenta bancaria','ahorros'=>'Cuenta de ahorros','corriente'=>'Cuenta corriente','efectivo'=>'Efectivo','billetera'=>'Billetera digital','otra'=>'Otra cuenta'] as $valor => $texto)
                <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $texto }}</option>
            @endforeach
        </select>
        @include('layouts.partials.field-error', ['name' => 'tipo'])
    </div>
    <div>
        <label class="form-label" for="institucion">Institución <span class="text-secondary">(opcional)</span></label>
        <input id="institucion" name="institucion" value="{{ old('institucion') }}" class="form-control form-control-lg @error('institucion') is-invalid @enderror" placeholder="Banco o billetera">
        @include('layouts.partials.field-error', ['name' => 'institucion'])
    </div>
    <div>
        <label class="form-label" for="numero_cuenta_enmascarado">Número enmascarado <span class="text-secondary">(opcional)</span></label>
        <input id="numero_cuenta_enmascarado" name="numero_cuenta_enmascarado" value="{{ old('numero_cuenta_enmascarado') }}" class="form-control form-control-lg @error('numero_cuenta_enmascarado') is-invalid @enderror" placeholder="**** 1234">
        @include('layouts.partials.field-error', ['name' => 'numero_cuenta_enmascarado'])
    </div>
    <div>
        <label class="form-label" for="saldo_inicial">Saldo inicial en pesos</label>
        <input id="saldo_inicial" name="saldo_inicial" value="{{ old('saldo_inicial', '0') }}" type="text" inputmode="decimal" data-miles class="form-control form-control-lg @error('saldo_inicial') is-invalid @enderror" placeholder="0" autocomplete="off" required>
        <small class="text-secondary">Genera una apertura; no se edita el saldo manualmente después.</small>
        @include('layouts.partials.field-error', ['name' => 'saldo_inicial'])
    </div>
    <button class="btn btn-primary btn-lg w-100 rounded-4 py-3" type="submit">Guardar cuenta</button>
</form>
@endsection
