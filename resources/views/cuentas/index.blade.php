@extends('layouts.app', ['title' => 'Cuentas'])
@section('content')
<div class="page-intro d-flex justify-content-between align-items-center mb-4">
    <div><p class="eyebrow mb-2">Patrimonio</p><h1 class="display-title mb-1">Mis cuentas</h1><p class="text-secondary mb-0">Tu dinero, en un solo lugar.</p></div>
    <a class="add-button" href="{{ route('app.cuentas.create') }}">+</a>
</div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="vstack gap-3">
@forelse ($cuentas as $cuenta)
<article class="account-card"><div class="account-card__icon">{{ strtoupper(substr($cuenta->nombre, 0, 1)) }}</div><div class="account-card__body">
    <div class="d-flex justify-content-between align-items-start"><div><h2>{{ $cuenta->nombre }}</h2><small>{{ ucfirst($cuenta->tipo) }}@if($cuenta->institucion) · {{ $cuenta->institucion }}@endif</small></div><strong>@cop($cuenta->saldo_actual_centavos)</strong></div>
    <form method="POST" action="{{ route('app.cuentas.destroy', $cuenta) }}">@csrf @method('DELETE')<button class="archive-link">Archivar cuenta</button></form>
</div></article>
@empty <div class="alert alert-light">Aún no tienes cuentas adicionales.</div> @endforelse
</div>
@endsection
