@extends('layouts.app', ['title' => 'Nueva tarjeta'])
@section('content')
<a href="{{ route('app.tarjetas.index') }}" class="back-link">‹ Volver a tarjetas</a>
<p class="eyebrow mt-4 mb-2">Nueva tarjeta</p>
<h1 class="display-title mb-1">Registra tu tarjeta</h1>
<p class="text-secondary mb-4">El cupo y las compras quedarán separados de tus cuentas de efectivo.</p>
<form method="POST" action="{{ route('app.tarjetas.store') }}" class="vstack gap-3">@csrf
    <div><label class="form-label">Banco o entidad</label><input name="entidad" value="{{ old('entidad') }}" class="form-control form-control-lg" required></div>
    <div><label class="form-label">Nombre de la tarjeta</label><input name="nombre" value="{{ old('nombre') }}" class="form-control form-control-lg" placeholder="Ej. Visa clásica" required></div>
    <div><label class="form-label">Cupo total</label><input name="cupo" value="{{ old('cupo') }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg" required></div>
    <div><label class="form-label">Tasa mensual (%)</label><input name="tasa" value="{{ old('tasa', 0) }}" type="number" min="0" step="0.01" class="form-control form-control-lg" required></div>
    <div class="row g-3"><div class="col-6"><label class="form-label">Día de corte</label><input name="dia_corte" value="{{ old('dia_corte', 15) }}" type="number" min="1" max="31" class="form-control form-control-lg" required></div><div class="col-6"><label class="form-label">Día límite de pago</label><input name="dia_pago" value="{{ old('dia_pago', 30) }}" type="number" min="1" max="31" class="form-control form-control-lg" required></div></div>
    <button class="btn btn-primary btn-lg w-100 rounded-4 py-3" type="submit">Guardar tarjeta</button>
</form>
@endsection
