@extends('layouts.app', ['title' => 'Registrar ingreso'])
@section('content')
<a href="{{ route('app.situacion') }}" class="back-link">‹ Volver al resumen</a>
<p class="eyebrow mt-4 mb-2">Nuevo ingreso</p>
<h1 class="display-title mb-1">¿Cuánto recibiste?</h1>
<p class="text-secondary mb-4">Los ingresos recurrentes solo proyectan; no cambian tu saldo hasta que los recibas.</p>
<form method="POST" action="{{ route('app.ingresos.store') }}" class="vstack gap-3">
@csrf
<div><label class="form-label" for="monto">Monto en pesos</label><input id="monto" name="monto" type="text" inputmode="decimal" data-miles value="{{ old('monto') }}" class="form-control form-control-lg" placeholder="0" autocomplete="off" required>@error('monto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div><label class="form-label" for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" value="{{ old('fecha', now()->toDateString()) }}" class="form-control form-control-lg" required></div>
<div><label class="form-label" for="categoria_id">Tipo de ingreso</label><select id="categoria_id" name="categoria_id" class="form-select form-select-lg" required>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div>
<div><label class="form-label" for="cuenta_liquida_id">Cuenta destino</label><select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg" required>@foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}" @selected(old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>@endforeach</select></div>
<div><label class="form-label" for="descripcion">Descripción <span class="text-secondary">(opcional)</span></label><input id="descripcion" name="descripcion" value="{{ old('descripcion') }}" class="form-control form-control-lg" placeholder="Ej. Pago de nómina"></div>
<div><label class="form-label" for="periodicidad">Periodicidad</label><select id="periodicidad" name="periodicidad" class="form-select form-select-lg" required>@foreach(['unico'=>'Único','diario'=>'Diario','semanal'=>'Semanal','quincenal'=>'Quincenal','mensual'=>'Mensual','anual'=>'Anual'] as $valor => $texto)<option value="{{ $valor }}" @selected(old('periodicidad', 'unico') === $valor)>{{ $texto }}</option>@endforeach</select></div>
<div class="form-check form-switch"><input id="recurrente" name="recurrente" value="1" type="checkbox" class="form-check-input" @checked(old('recurrente'))><label for="recurrente" class="form-check-label">Es un ingreso recurrente</label><small class="d-block text-secondary">Se guardará como proyección, sin sumarlo al saldo recibido.</small></div>
<button class="btn btn-primary btn-lg w-100 rounded-4 py-3" type="submit">Guardar ingreso</button>
</form>
@endsection
