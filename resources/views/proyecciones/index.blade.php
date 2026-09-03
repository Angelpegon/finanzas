@extends('layouts.app', ['title' => 'Proyecciones'])
@section('content')
<p class="eyebrow mb-2">Planeación financiera</p>
<h1 class="display-title mb-1">Proyecciones</h1>
<p class="text-secondary mb-4">Una estimación de lo que puede ocurrir. No modifica ni reemplaza tus movimientos reales.</p>
<div class="d-flex gap-2 mb-4">
    @foreach([3, 6, 12] as $opcion)
        <a class="btn {{ $cantidadMeses === $opcion ? 'btn-primary' : 'btn-outline-primary' }} rounded-4" href="{{ route('app.proyecciones', ['meses' => $opcion]) }}">{{ $opcion }} meses</a>
    @endforeach
</div>
<div class="vstack gap-3">
    @foreach($meses as $mes)
        <article class="section-block">
            <div class="section-heading"><div><p class="eyebrow mb-1">PROYECTADO</p><h2>{{ $mes['etiqueta'] }}</h2></div><span class="soft-badge">COP</span></div>
            <div class="projection-row"><span>Ingresos</span><strong class="text-success">@cop($mes['ingresos_centavos'])</strong></div>
            <div class="projection-row"><span>Gastos recurrentes</span><strong class="text-danger">@cop($mes['gastos_centavos'])</strong></div>
            <div class="projection-row"><span>Deudas programadas</span><strong class="text-warning">@cop($mes['deudas_centavos'])</strong></div>
            <div class="projection-row"><span>Pagos programados</span><strong>@cop($mes['pagos_centavos'])</strong></div>
            <div class="projection-row projection-total"><span>Disponible proyectado</span><strong class="{{ $mes['disponible_centavos'] >= 0 ? 'text-success' : 'text-danger' }}">@cop($mes['disponible_centavos'])</strong></div>
        </article>
    @endforeach
</div>
<p class="small text-secondary mt-4 mb-0">Las deudas corresponden a cuotas pendientes de préstamos y tarjetas. Los pagos de deuda no se vuelven a restar en esta vista para evitar contabilizar dos veces la misma salida.</p>
@endsection
