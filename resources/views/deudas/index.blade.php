@extends('layouts.app', ['title' => 'Deudas'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><p class="eyebrow mb-2">Obligaciones</p><h1 class="display-title mb-1">Mis deudas</h1><p class="text-secondary mb-0">Todo lo que debes, con claridad.</p></div><a class="add-button" href="{{ route('app.deudas.create') }}">+</a></div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="vstack gap-3">
@foreach($prestamos as $deuda)
@php $proxima = $deuda->cuotas->firstWhere('pagada', false); @endphp
<article class="account-card"><div class="account-card__icon">↘</div><div class="account-card__body">
    <div class="d-flex justify-content-between"><div><h2>{{ $deuda->nombre }}</h2><small>{{ $deuda->entidad ?: 'Obligación' }} · {{ str_replace('_', ' ', $deuda->tipo_obligacion) }}</small></div><strong>@cop($deuda->saldo_actual_centavos)</strong></div>
    <div class="debt-meta"><span>{{ $deuda->cuotas_pagadas }} pagadas</span><span>{{ $deuda->cuotas_pendientes }} pendientes</span><span class="status-pill">{{ ucfirst($deuda->estado) }}</span></div>
    @if($proxima)
    <form method="POST" action="{{ route('app.deudas.pagos.store') }}" class="row g-2 align-items-end mt-3">
        @csrf
        <input type="hidden" name="prestamo_id" value="{{ $deuda->id }}">
        <div class="col-md-4"><label class="form-label small mb-1">Pagar cuota {{ $proxima->numero }}</label><input name="monto" data-miles inputmode="decimal" class="form-control" value="{{ number_format(($proxima->total_centavos ?: ($proxima->capital_centavos + $proxima->interes_centavos)) / 100, 0, ',', '.') }}" required></div>
        <div class="col-md-4"><label class="form-label small mb-1">Fecha</label><input name="fecha" type="date" value="{{ now()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-4"><button class="btn btn-outline-primary w-100" type="submit">Registrar pago</button></div>
    </form>
    @endif
    <details class="mt-2"><summary class="small text-primary">Ver cronograma</summary><div class="table-responsive mt-2"><table class="table table-sm small"><thead><tr><th>Cuota</th><th>Fecha</th><th>Capital</th><th>Interés</th><th>Otros</th><th>Total</th><th>Saldo</th><th></th></tr></thead><tbody>
    @foreach($deuda->cuotas as $cuota)
    <tr class="{{ $cuota->pagada ? 'text-secondary' : '' }}">
        <td>{{ $cuota->numero }}</td>
        <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
        <td>@cop($cuota->capital_centavos)</td>
        <td>@cop($cuota->interes_centavos)</td>
        <td>@cop($cuota->seguro_centavos + $cuota->otros_cargos_centavos)</td>
        <td>@cop($cuota->total_centavos)</td>
        <td>@cop($cuota->saldo_capital_centavos)</td>
        <td>{{ $cuota->pagada ? 'Pagada' : 'Pendiente' }}</td>
    </tr>
    @endforeach
    </tbody></table></div></details>
</div></article>
@endforeach
@foreach($tarjetas as $deuda)<article class="account-card"><div class="account-card__icon">▣</div><div class="account-card__body"><div class="d-flex justify-content-between"><div><h2>{{ $deuda->nombre }}</h2><small>{{ $deuda->entidad ?: 'Tarjeta de crédito' }}</small></div><strong>@cop($deuda->saldo_actual_centavos)</strong></div><div class="debt-meta"><span>Cupo @cop($deuda->cupo_centavos)</span><span>{{ $deuda->cuotas_pendientes }} pendientes</span><span class="status-pill">{{ ucfirst($deuda->estado) }}</span></div><p class="small text-secondary mb-0 mt-2">Gestiona compras y pagos en <a href="{{ route('app.tarjetas.index') }}">Tarjetas</a>.</p></div></article>@endforeach
@if($prestamos->isEmpty() && $tarjetas->isEmpty())<div class="alert alert-light">Aún no tienes obligaciones registradas.</div>@endif
</div>
@endsection
