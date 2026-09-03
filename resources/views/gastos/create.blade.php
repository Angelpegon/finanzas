@extends('layouts.app', ['title' => 'Registrar gasto'])
@section('content')
<a href="{{ route('app.situacion') }}" class="back-link">‹ Volver al resumen</a>
<p class="eyebrow mt-4 mb-2">Nuevo gasto</p>
<h1 class="display-title mb-1">¿En qué gastaste?</h1>
<p class="text-secondary mb-4">Un gasto real descuenta tu cuenta; uno proyectado solo ayuda a planear.</p>
<form method="POST" action="{{ route('app.gastos.store') }}" class="vstack gap-3">
@csrf
<div><label class="form-label" for="monto">Monto en pesos</label><input id="monto" name="monto" type="text" inputmode="decimal" data-miles value="{{ old('monto') }}" class="form-control form-control-lg" placeholder="0" autocomplete="off" required>@error('monto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
<div><label class="form-label" for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" value="{{ old('fecha', now()->toDateString()) }}" class="form-control form-control-lg" required></div>
<div><label class="form-label" for="categoria_id">Categoría</label><select id="categoria_id" name="categoria_id" class="form-select form-select-lg" required>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected(old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>@endforeach</select></div>
<div><label class="form-label" for="tipo_gasto">Tipo</label><select id="tipo_gasto" name="tipo_gasto" class="form-select form-select-lg" required>@foreach(['fijo'=>'Fijo','variable'=>'Variable','extraordinario'=>'Extraordinario'] as $valor => $texto)<option value="{{ $valor }}" @selected(old('tipo_gasto', 'variable') === $valor)>{{ $texto }}</option>@endforeach</select></div>
<div><label class="form-label" for="cuenta_liquida_id">Cuenta de pago</label><select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg" required>@foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}" @selected(old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>@endforeach</select></div>
<div><label class="form-label" for="descripcion">Descripción <span class="text-secondary">(opcional)</span></label><input id="descripcion" name="descripcion" value="{{ old('descripcion') }}" class="form-control form-control-lg" placeholder="Ej. Mercado del mes"></div>
<div><label class="form-label" for="periodicidad">Periodicidad</label><select id="periodicidad" name="periodicidad" class="form-select form-select-lg" required>@foreach(['unico'=>'Único','diario'=>'Diario','semanal'=>'Semanal','quincenal'=>'Quincenal','mensual'=>'Mensual','anual'=>'Anual'] as $valor => $texto)<option value="{{ $valor }}" @selected(old('periodicidad', 'unico') === $valor)>{{ $texto }}</option>@endforeach</select></div>
<div class="form-check form-switch"><input id="proyectado" name="proyectado" value="1" type="checkbox" class="form-check-input" @checked(old('proyectado'))><label for="proyectado" class="form-check-label">Es un gasto proyectado</label><small class="d-block text-secondary">No afecta saldos ni movimientos reales.</small></div>
<button class="btn btn-primary btn-lg w-100 rounded-4 py-3" type="submit">Guardar gasto</button>
</form>
@endsection
