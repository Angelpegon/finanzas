@extends('layouts.app', ['title' => 'Tarjetas'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><p class="eyebrow mb-2">Crédito</p><h1 class="display-title mb-1">Mis tarjetas</h1><p class="text-secondary mb-0">Compra, financia y paga con trazabilidad.</p></div><a class="add-button" href="{{ route('app.tarjetas.create') }}">+</a></div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="vstack gap-4">
@forelse($tarjetas as $tarjeta)
<article class="account-card"><div class="account-card__icon">▣</div><div class="account-card__body">
    <div class="d-flex justify-content-between"><div><h2>{{ $tarjeta->nombre }}</h2><small>{{ $tarjeta->entidad ?: 'Tarjeta de crédito' }}</small></div><strong>@cop($tarjeta->saldo_actual_centavos)</strong></div>
    <div class="debt-meta"><span>Disponible @cop($tarjeta->cupo_disponible_centavos)</span><span>Cupo @cop($tarjeta->cupo_centavos)</span></div>
    <div class="small text-secondary mt-2">Pago mínimo: <strong>@cop($tarjeta->pago_minimo_centavos)</strong> · Pago total: <strong>@cop($tarjeta->pago_total_centavos)</strong></div>
    <details class="mt-3"><summary class="small text-primary">Ver compras y cuotas</summary>
        <div class="table-responsive mt-2"><table class="table table-sm small"><thead><tr><th>Compra</th><th>Fecha</th><th>Monto</th><th>Cuotas</th></tr></thead><tbody>
        @foreach($tarjeta->compras as $compra)<tr><td>{{ $compra->descripcion }}</td><td>{{ $compra->fecha->format('d/m/Y') }}</td><td>@cop($compra->monto_centavos)</td><td>{{ $compra->cuotas }} ({{ $compra->cuotasProgramadas->where('pagada', false)->count() }} pendientes)</td></tr>
        @foreach($compra->cuotasProgramadas as $cuota)<tr class="text-secondary"><td>↳ Cuota {{ $cuota->numero }}</td><td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td><td>@cop($cuota->capital_centavos + $cuota->interes_centavos)</td><td>{{ $cuota->pagada ? 'Pagada' : 'Pendiente' }} @unless($cuota->pagada)<form method="POST" action="{{ route('app.tarjetas.pagos.store') }}" class="d-inline ms-2">@csrf<input type="hidden" name="tarjeta_credito_id" value="{{ $tarjeta->id }}"><input type="hidden" name="cuota_tarjeta_id" value="{{ $cuota->id }}"><input type="hidden" name="fecha" value="{{ now()->toDateString() }}"><select name="cuenta_liquida_id" class="form-select form-select-sm d-inline-block w-auto" required><option value="">Cuenta</option>@foreach($cuentas as $cuenta)<option value="{{ $cuenta->id }}">{{ $cuenta->nombre }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary" type="submit">Pagar</button></form>@endunless</td></tr>@endforeach
        @endforeach</tbody></table></div>
    </details>
</div></article>
@empty <div class="alert alert-light">Aún no tienes tarjetas registradas.</div>
@endforelse
</div>
@if($tarjetas->isNotEmpty())<div class="card border-0 shadow-sm rounded-4 mt-4 p-3"><h2 class="h5">Registrar compra</h2><form method="POST" action="{{ route('app.tarjetas.compras.store') }}" class="vstack gap-3">@csrf
<select name="tarjeta_credito_id" class="form-select" required><option value="">Tarjeta</option>@foreach($tarjetas as $tarjeta)<option value="{{ $tarjeta->id }}">{{ $tarjeta->nombre }}</option>@endforeach</select>
<input name="descripcion" class="form-control" placeholder="Descripción" required><input name="monto" data-miles inputmode="decimal" class="form-control" placeholder="Monto" required><input name="fecha" type="date" value="{{ now()->toDateString() }}" class="form-control" required>
<select name="categoria_id" class="form-select" required>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>@endforeach</select>
<div class="row g-2"><div class="col-6"><input name="cuotas" type="number" min="1" max="60" value="1" class="form-control" placeholder="Cuotas" required></div><div class="col-6"><input name="interes" type="number" min="0" step="0.01" value="0" class="form-control" placeholder="Interés mensual %"></div></div>
<button class="btn btn-primary rounded-4" type="submit">Guardar compra financiada</button></form></div>@endif
@endsection
