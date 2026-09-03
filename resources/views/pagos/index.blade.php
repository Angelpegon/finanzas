@extends('layouts.app', ['title' => 'Pagos'])
@section('content')
<p class="eyebrow mb-2">Salidas de dinero</p>
<h1 class="display-title mb-1">Registrar un pago</h1>
<p class="text-secondary mb-4">Cada referencia solo puede registrarse una vez.</p>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('app.pagos.store') }}" class="card border-0 shadow-sm rounded-4 p-3 mb-4 vstack gap-3">@csrf
    <div class="row g-3"><div class="col-6"><label class="form-label">Tipo</label><select name="tipo" class="form-select" required>@foreach(['gasto'=>'Gasto','servicio'=>'Servicio','deuda_personal'=>'Deuda personal','otra_obligacion'=>'Otra obligación'] as $v=>$t)<option value="{{ $v }}">{{ $t }}</option>@endforeach</select><small class="text-secondary">Los pagos de créditos, préstamos y tarjetas se registran desde sus módulos.</small></div><div class="col-6"><label class="form-label">Fecha</label><input name="fecha" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div></div>
    <div><label class="form-label">Monto</label><input name="monto" data-miles inputmode="decimal" class="form-control form-control-lg" required></div>
    <div><label class="form-label">Cuenta utilizada</label><select name="cuenta_liquida_id" class="form-select" required><option value="">Selecciona una cuenta</option>@foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>@endforeach</select></div>
    <div><label class="form-label">Categoría de gasto</label><select name="categoria_id" class="form-select"><option value="">Para gasto o servicio</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>@endforeach</select></div>
    <input name="destino" class="form-control" placeholder="Destino: proveedor, banco o persona" required>
    <input name="referencia" class="form-control" placeholder="Referencia única: factura o recibo" required>
    <textarea name="observaciones" class="form-control" rows="2" placeholder="Observaciones (opcional)"></textarea>
    <button class="btn btn-primary btn-lg rounded-4" type="submit">Guardar pago</button>
</form>
<h2 class="h5 mb-3">Historial de pagos</h2>
<div class="vstack gap-2">@forelse($pagos as $pago)<article class="account-card"><div class="account-card__icon">↓</div><div class="account-card__body"><div class="d-flex justify-content-between"><div><h2>{{ $pago->destino }}</h2><small>{{ ucfirst($pago->tipo) }} · {{ $pago->fecha->format('d/m/Y') }}</small></div><strong>@cop($pago->monto_centavos)</strong></div><div class="small text-secondary mt-1">Ref. {{ $pago->referencia }} · {{ $pago->cuentaLiquida?->nombre }}</div></div></article>@empty<div class="alert alert-light">Aún no hay pagos registrados.</div>@endforelse</div>
@endsection
