@extends('layouts.app', ['title' => 'Agregar cuenta'])
@section('content')
<a href="{{ route('app.cuentas.index') }}" class="back-link">‹ Volver a cuentas</a>
<p class="eyebrow mt-4 mb-2">Nueva cuenta</p><h1 class="display-title mb-1">¿Dónde guardas tu dinero?</h1><p class="text-secondary mb-4">Registra una cuenta para tener una vista completa.</p>
<form method="POST" action="{{ route('app.cuentas.store') }}" class="vstack gap-3">
@csrf
<div><label class="form-label" for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre') }}" class="form-control form-control-lg" placeholder="Ej. Cuenta nómina" required>@error('nombre')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div><label class="form-label" for="tipo">Tipo</label><select id="tipo" name="tipo" class="form-select form-select-lg" required>@foreach(['bancaria'=>'Cuenta bancaria','ahorros'=>'Cuenta de ahorros','corriente'=>'Cuenta corriente','efectivo'=>'Efectivo','billetera'=>'Billetera digital','otra'=>'Otra cuenta'] as $valor => $texto)<option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $texto }}</option>@endforeach</select></div>
<div><label class="form-label" for="institucion">Institución <span class="text-secondary">(opcional)</span></label><input id="institucion" name="institucion" value="{{ old('institucion') }}" class="form-control form-control-lg" placeholder="Banco o billetera"></div>
<div><label class="form-label" for="numero_cuenta_enmascarado">Número enmascarado <span class="text-secondary">(opcional)</span></label><input id="numero_cuenta_enmascarado" name="numero_cuenta_enmascarado" value="{{ old('numero_cuenta_enmascarado') }}" class="form-control form-control-lg" placeholder="**** 1234"></div>
<div><label class="form-label" for="saldo_inicial">Saldo inicial en pesos</label><input id="saldo_inicial" name="saldo_inicial" value="{{ old('saldo_inicial', '0') }}" type="text" inputmode="decimal" data-miles class="form-control form-control-lg" placeholder="0" autocomplete="off" required><small class="text-secondary">Genera una apertura; no se edita el saldo manualmente después.</small></div>
<button class="btn btn-primary btn-lg w-100 rounded-4 py-3" type="submit">Guardar cuenta</button>
</form>
@endsection
