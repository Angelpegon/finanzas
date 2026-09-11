@extends('layouts.app', [
    'title' => 'Proyecciones',
    'heading' => 'Proyecciones',
    'subtitle' => 'Flujo esperado por mes. No modifica el libro ni es el “disponible” de Situación (ese resta liquidez libre).',
])
@section('content')
<div class="d-flex gap-2 mb-4">
    @foreach([3, 6, 12] as $opcion)
        <a class="btn {{ $cantidadMeses === $opcion ? 'btn-primary' : 'btn-outline-primary' }} rounded-4" href="{{ route('app.proyecciones', ['meses' => $opcion]) }}">{{ $opcion }} meses</a>
    @endforeach
</div>
<div class="card-stack">
    @foreach($meses as $mes)
        <article class="dash-card">
            <div class="dash-card__head">
                <h2>{{ $mes['etiqueta'] }}</h2>
                <span class="soft-badge">PROYECTADO</span>
            </div>
            <div class="projection-row"><span>Ingresos esperados</span><strong class="text-success">@cop($mes['ingresos_centavos'])</strong></div>
            <div class="projection-row"><span>Gastos esperados</span><strong class="text-danger">@cop($mes['gastos_centavos'])</strong></div>
            <div class="projection-row"><span>Deudas (cuotas)</span><strong class="text-warning">@cop($mes['deudas_centavos'])</strong></div>
            <div class="projection-row"><span>Aportes a metas (plan neto)</span><strong class="text-warning">@cop($mes['metas_centavos'] ?? 0)</strong></div>
            <div class="projection-row projection-total">
                <span>Residual del mes</span>
                <strong class="{{ ($mes['residual_centavos'] ?? $mes['disponible_centavos'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    @cop($mes['residual_centavos'] ?? $mes['disponible_centavos'] ?? 0)
                </strong>
            </div>
        </article>
    @endforeach
</div>
<p class="small text-secondary mt-4 mb-0">
    El residual es ingresos − gastos − cuotas − metas del mes; <strong>no</strong> incluye el saldo de tus cuentas.
    En el mes actual, las cuotas vencidas impagas se arrastran aquí. El “disponible” de Situación parte de liquidez libre y resta los mismos compromisos.
    Las recurrencias siguen la misma semántica que el calendario (<code>unico</code>, <code>anual</code>, cobertura por monto).
</p>
@endsection
