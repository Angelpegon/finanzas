@extends('layouts.app', [
    'title' => 'Calendario financiero',
    'heading' => 'Calendario',
    'subtitle' => 'Mapa del mes y detalle del día seleccionado.',
])
@section('content')
@php
    $diaCarbon = \Carbon\Carbon::parse($diaSeleccionado)->locale('es');
    $etiquetaDia = ucfirst($diaCarbon->isoFormat('dddd D [de] MMMM'));
@endphp

<div class="cal-page">
    <div class="cal-toolbar dash-card">
        <div class="cal-toolbar__nav">
            <a class="cal-nav-btn" href="{{ route('app.calendario', ['anio' => $mesAnterior->year, 'mes' => $mesAnterior->month]) }}" aria-label="Mes anterior">‹</a>
            <div class="cal-toolbar__title">
                <strong>{{ $grilla['etiqueta'] }}</strong>
                @unless($esMesActual)
                    <a class="cal-today-link" href="{{ route('app.calendario') }}">Hoy</a>
                @endunless
            </div>
            <a class="cal-nav-btn" href="{{ route('app.calendario', ['anio' => $mesSiguiente->year, 'mes' => $mesSiguiente->month]) }}" aria-label="Mes siguiente">›</a>
        </div>
        <div class="cal-summary">
            <div class="cal-summary__item cal-summary__item--danger">
                <span>Vencidos</span>
                <strong>{{ $resumen['vencidos'] }}</strong>
            </div>
            <div class="cal-summary__item cal-summary__item--accent">
                <span>Proyectados</span>
                <strong>{{ $resumen['proyectados'] }}</strong>
            </div>
            <div class="cal-summary__item cal-summary__item--ok">
                <span>Reales</span>
                <strong>{{ $resumen['reales'] }}</strong>
            </div>
        </div>
        <div class="cal-summary cal-summary--money">
            <div class="cal-summary__item">
                <span>Ingresos del mes</span>
                <strong class="text-success">@cop($resumen['ingresos_centavos'])</strong>
            </div>
            <div class="cal-summary__item">
                <span>Salidas del mes</span>
                <strong>@cop($resumen['salidas_centavos'])</strong>
            </div>
        </div>
    </div>

    <div class="cal-layout">
        <section class="cal-month dash-card" aria-label="Grilla del mes">
            <div class="cal-month__weekdays">
                @foreach(['L','M','X','J','V','S','D'] as $wd)
                    <span>{{ $wd }}</span>
                @endforeach
            </div>
            <div class="cal-month__grid">
                @foreach($grilla['celdas'] as $celda)
                    @if($celda === null)
                        <div class="cal-day cal-day--empty" aria-hidden="true"></div>
                    @else
                        @php
                            $seleccionado = $celda['fecha'] === $diaSeleccionado;
                            $clases = collect([
                                'cal-day',
                                $celda['hoy'] ? 'is-today' : null,
                                $seleccionado ? 'is-selected' : null,
                                $celda['tiene_pago'] ? 'has-pago' : null,
                                $celda['tiene_ingreso'] ? 'has-ingreso' : null,
                                ($celda['tiene_vencido'] ?? false) ? 'has-vencido' : null,
                                count($celda['eventos']) > 0 ? 'has-events' : null,
                            ])->filter()->implode(' ');
                        @endphp
                        <a
                            class="{{ $clases }}"
                            href="{{ route('app.calendario', ['anio' => $fecha->year, 'mes' => $fecha->month, 'dia' => $celda['fecha']]) }}"
                            @if($seleccionado) aria-current="date" @endif
                            title="{{ collect($celda['eventos'])->pluck('descripcion')->join(' · ') ?: 'Sin eventos' }}"
                        >
                            <span class="cal-day__num">{{ $celda['dia'] }}</span>
                            @if(count($celda['eventos']) > 0)
                                <span class="cal-day__dots" aria-hidden="true">
                                    @if($celda['tiene_ingreso'])<i class="dot dot--green"></i>@endif
                                    @if($celda['tiene_pago'])<i class="dot dot--red"></i>@endif
                                    @if($celda['tiene_vencido'] ?? false)<i class="dot dot--amber"></i>@endif
                                </span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </div>
            <div class="cal-legend">
                <span><i class="dot dot--green"></i> Ingreso</span>
                <span><i class="dot dot--red"></i> Salida</span>
                <span><i class="dot dot--amber"></i> Vencido</span>
            </div>
        </section>

        <section class="cal-agenda dash-card" aria-label="Agenda del día">
            <div class="cal-agenda__head">
                <div>
                    <p class="form-section__eyebrow">Día</p>
                    <h2 class="cal-agenda__title">{{ $etiquetaDia }}</h2>
                </div>
                <span class="cal-agenda__count">{{ count($eventosDia) }}</span>
            </div>

            <div class="cal-agenda__list">
                @forelse($eventosDia as $evento)
                    @php
                        $iconoEvento = match ($evento['tipo']) {
                            'ingreso' => 'arrow-up',
                            'gasto' => 'arrow-down',
                            'pago', 'cuota', 'limite_tarjeta' => 'banknote',
                            'corte' => 'credit-card',
                            default => 'circle',
                        };
                        $estadoClase = match ($evento['estado']) {
                            'vencido' => 'is-overdue',
                            'proyectado' => 'is-projected',
                            default => 'is-real',
                        };
                    @endphp
                    <article class="cal-event {{ $estadoClase }}">
                        <div class="cal-event__icon">@include('layouts.partials.icon', ['name' => $iconoEvento, 'class' => 'ui-icon ui-icon--sm'])</div>
                        <div class="cal-event__body">
                            <div class="cal-event__row">
                                <h3>{{ $evento['descripcion'] }}</h3>
                                <strong class="{{ $evento['tipo'] === 'ingreso' ? 'text-success' : '' }}">
                                    @if($evento['monto_centavos'] > 0)@cop($evento['monto_centavos'])@else — @endif
                                </strong>
                            </div>
                            <div class="cal-event__meta">
                                <span>{{ ucfirst(str_replace('_', ' ', $evento['tipo'])) }}</span>
                                <span class="cal-event__state">{{ ucfirst($evento['estado']) }}</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="alert alert-light empty-state mb-0">No hay eventos en este día. Elige otra fecha en la grilla.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
