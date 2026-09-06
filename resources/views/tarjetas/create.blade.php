@extends('layouts.app', [
    'title' => 'Nueva tarjeta',
    'heading' => 'Registra tu tarjeta',
    'subtitle' => 'El cupo y las compras quedarán separados de tus cuentas de efectivo.',
    'backUrl' => route('app.tarjetas.index'),
    'backLabel' => 'Volver a tarjetas',
])
@section('content')
@include('layouts.partials.form-errors')
<form method="POST" action="{{ route('app.tarjetas.store') }}" class="dash-card form-panel" novalidate>
    @csrf
    <div>
        <label class="form-label" for="entidad">Banco o entidad</label>
        <input id="entidad" name="entidad" value="{{ old('entidad') }}" class="form-control form-control-lg @error('entidad') is-invalid @enderror" required>
        @include('layouts.partials.field-error', ['name' => 'entidad'])
    </div>
    <div>
        <label class="form-label" for="nombre">Nombre de la tarjeta</label>
        <input id="nombre" name="nombre" value="{{ old('nombre') }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" placeholder="Ej. Visa clásica" required>
        @include('layouts.partials.field-error', ['name' => 'nombre'])
    </div>
    <div>
        <label class="form-label" for="cupo">Cupo total</label>
        <input id="cupo" name="cupo" value="{{ old('cupo') }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg @error('cupo') is-invalid @enderror" required>
        @include('layouts.partials.field-error', ['name' => 'cupo'])
    </div>
    <div>
        <label class="form-label" for="tasa">Tasa mensual (%)</label>
        <input id="tasa" name="tasa" value="{{ old('tasa', 0) }}" type="number" min="0" step="0.01" class="form-control form-control-lg @error('tasa') is-invalid @enderror" required>
        @include('layouts.partials.field-error', ['name' => 'tasa'])
    </div>
    <div class="row g-3">
        <div class="col-6">
            <label class="form-label" for="dia_corte">Día de corte</label>
            <input id="dia_corte" name="dia_corte" value="{{ old('dia_corte', 15) }}" type="number" min="1" max="31" class="form-control form-control-lg @error('dia_corte') is-invalid @enderror" required>
            @include('layouts.partials.field-error', ['name' => 'dia_corte'])
        </div>
        <div class="col-6">
            <label class="form-label" for="dia_pago">Día límite de pago</label>
            <input id="dia_pago" name="dia_pago" value="{{ old('dia_pago', 30) }}" type="number" min="1" max="31" class="form-control form-control-lg @error('dia_pago') is-invalid @enderror" required>
            @include('layouts.partials.field-error', ['name' => 'dia_pago'])
        </div>
    </div>
    <button class="btn btn-primary btn-lg w-100 rounded-4 py-3" type="submit">Guardar tarjeta</button>
</form>
@endsection
