@extends('layouts.app', [
    'title' => 'Metas de ahorro',
    'heading' => 'Mis metas',
    'subtitle' => 'Bolsillo dedicado (nunca operativa). Aportes desde operativa; retira desde cada meta hacia una cuenta operativa.',
])
@section('content')
@php
    $errMeta = $errors->meta;
    $errAporte = $errors->aporte;
    $errRetiro = $errors->retiro;
    $errEditar = $errors->editar_meta;
    $oldMeta = $errMeta->any();
    $oldAporte = $errAporte->any();
    $oldRetiro = $errRetiro->any();
    $modoAporte = $oldAporte || request()->boolean('aporte');
    $metaRetiroOpen = $oldRetiro ? (int) old('meta_ahorro_id') : 0;
@endphp

@include('layouts.partials.form-errors', ['bag' => 'editar_meta'])
@include('layouts.partials.form-errors', ['bag' => 'retiro'])

<div class="capture-flow">
    <nav class="flow-tabs" aria-label="Acción de metas">
        <a href="{{ route('app.metas.index') }}" class="{{ ! $modoAporte ? 'is-active' : '' }}">Nueva meta</a>
        <a href="{{ route('app.metas.index', ['aporte' => 1]) }}" class="{{ $modoAporte ? 'is-active' : '' }}">Aportar</a>
    </nav>

    @if(! $modoAporte)
        <form method="POST" action="{{ route('app.metas.store') }}" class="dash-card form-panel" novalidate>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKeyMeta) }}">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Objetivo</p>
                <h2 class="form-section__title">Crear una meta</h2>
            </div>
            @include('layouts.partials.form-errors', ['bag' => 'meta'])

            <div class="money-hero">
                <label class="form-label" for="objetivo">Monto objetivo</label>
                <input id="objetivo" name="objetivo" data-miles inputmode="decimal" value="{{ $oldMeta ? old('objetivo') : '' }}" class="form-control form-control-lg @error('objetivo', 'meta') is-invalid @enderror" placeholder="0" required>
                @include('layouts.partials.field-error', ['name' => 'objetivo', 'bag' => 'meta'])
            </div>

            <section class="form-section">
                <div>
                    <label class="form-label" for="nombre">Nombre de la meta</label>
                    <input id="nombre" name="nombre" value="{{ $oldMeta ? old('nombre') : '' }}" class="form-control form-control-lg @error('nombre', 'meta') is-invalid @enderror" placeholder="Ej. Fondo de emergencia" required>
                    @include('layouts.partials.field-error', ['name' => 'nombre', 'bag' => 'meta'])
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="aporte_mensual">Ahorro mensual planificado</label>
                        <input id="aporte_mensual" name="aporte_mensual" data-miles inputmode="decimal" value="{{ $oldMeta ? old('aporte_mensual', 0) : 0 }}" class="form-control form-control-lg @error('aporte_mensual', 'meta') is-invalid @enderror">
                        @include('layouts.partials.field-error', ['name' => 'aporte_mensual', 'bag' => 'meta'])
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="fecha_objetivo">Fecha objetivo</label>
                        <input id="fecha_objetivo" name="fecha_objetivo" type="date" value="{{ $oldMeta ? old('fecha_objetivo') : '' }}" class="form-control form-control-lg @error('fecha_objetivo', 'meta') is-invalid @enderror">
                        @include('layouts.partials.field-error', ['name' => 'fecha_objetivo', 'bag' => 'meta'])
                    </div>
                </div>
                <div>
                    <label class="form-label" for="cuenta_liquida_id">Cuenta de referencia</label>
                    <select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'meta') is-invalid @enderror" required>
                        <option value="">Selecciona cuenta operativa</option>
                        @foreach($cuentasOperativas as $cuenta)
                            <option value="{{ $cuenta->id }}" @selected($oldMeta && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    <small class="text-secondary">Se crea un bolsillo dedicado; nunca se convierte en cuenta operativa.</small>
                    @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'meta'])
                </div>
                <div>
                    <label class="form-label" for="prioridad">Prioridad</label>
                    <select id="prioridad" name="prioridad" class="form-select form-select-lg @error('prioridad', 'meta') is-invalid @enderror" required>
                        @foreach(['alta'=>'Alta','media'=>'Media','baja'=>'Baja'] as $v=>$t)
                            <option value="{{ $v }}" @selected(($oldMeta ? old('prioridad', 'media') : 'media') === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'prioridad', 'bag' => 'meta'])
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit">Crear meta</button>
            </div>
        </form>
    @else
        @if($metas->isNotEmpty() && $cuentasOperativas->isNotEmpty())
        <form method="POST" action="{{ route('app.metas.aportes.store') }}" class="dash-card form-panel" novalidate>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKeyAporte) }}">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Movimiento</p>
                <h2 class="form-section__title">Registrar aporte</h2>
            </div>
            @include('layouts.partials.form-errors', ['bag' => 'aporte'])
            <p class="small text-secondary mb-3">Solo desde cuentas operativas. El dinero queda etiquetado en el bolsillo.</p>

            <div class="money-hero">
                <label class="form-label" for="monto_aporte">Monto</label>
                <input id="monto_aporte" name="monto" data-miles inputmode="decimal" value="{{ $oldAporte ? old('monto') : '' }}" class="form-control form-control-lg @error('monto', 'aporte') is-invalid @enderror" placeholder="0" required>
                @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'aporte'])
            </div>

            <section class="form-section">
                <div>
                    <label class="form-label" for="meta_ahorro_id">Meta</label>
                    <select id="meta_ahorro_id" name="meta_ahorro_id" class="form-select form-select-lg @error('meta_ahorro_id', 'aporte') is-invalid @enderror" required>
                        <option value="">Selecciona una meta</option>
                        @foreach($metas as $meta)
                            <option value="{{ $meta->id }}" @selected($oldAporte && old('meta_ahorro_id') == $meta->id)>{{ $meta->nombre }} · @cop($meta->monto_actual_centavos)</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'meta_ahorro_id', 'bag' => 'aporte'])
                </div>
                <div>
                    <label class="form-label" for="cuenta_origen_aporte">Sale de</label>
                    <select id="cuenta_origen_aporte" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'aporte') is-invalid @enderror" required>
                        <option value="">Cuenta operativa</option>
                        @foreach($cuentasOperativas as $cuenta)
                            <option value="{{ $cuenta->id }}" @selected($oldAporte && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'aporte'])
                </div>
                <div>
                    <label class="form-label" for="fecha_aporte">Fecha</label>
                    <input id="fecha_aporte" name="fecha" type="date" value="{{ $oldAporte ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control form-control-lg @error('fecha', 'aporte') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'aporte'])
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit">Contabilizar aporte</button>
            </div>
        </form>
        @elseif($metas->isNotEmpty())
            <div class="alert alert-light empty-state">
                Para aportar necesitas una cuenta operativa con saldo.
                <a href="{{ route('app.cuentas.create') }}">Crear cuenta</a>.
            </div>
        @else
            <div class="alert alert-light empty-state">Crea una meta primero para poder aportar.</div>
        @endif
    @endif

    <div class="list-block">
        <div class="list-block__head">
            <h2>Tus metas</h2>
            <span>{{ $metas->count() }}</span>
        </div>
        <div class="card-stack">
        @forelse($metas as $meta)
            @php
                $saldoBolsillo = (int) ($meta->cuentaLiquida?->saldoCentavos() ?? 0);
                $esteRetiro = $oldRetiro && (int) old('meta_ahorro_id') === (int) $meta->id;
                $esteEditar = $errEditar->any() && (int) old('_meta_edit') === (int) $meta->id;
                // Solo un panel abierto: error de edición gana sobre retiro / deep-link.
                $abrirEditar = $esteEditar;
                $abrirRetiro = ! $abrirEditar && ($esteRetiro || $metaRetiroOpen === (int) $meta->id);
                $puedeRetirar = $saldoBolsillo > 0 && $cuentasOperativas->isNotEmpty();
            @endphp
        <article class="account-card" @if($abrirRetiro || $abrirEditar) id="meta-accion-{{ $meta->id }}" @endif>
            <div class="account-card__main">
                <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'piggy-bank', 'class' => 'ui-icon ui-icon--sm'])</div>
                <div class="account-card__body w-100">
                    <div class="account-card__head">
                        <div class="account-card__title">
                            <h2>{{ $meta->nombre }}</h2>
                            <small>{{ ucfirst($meta->prioridad) }} · {{ ucfirst($meta->estado) }}@if($meta->cuentaLiquida) · {{ $meta->cuentaLiquida->nombre }}@endif</small>
                        </div>
                        <strong class="account-card__amount">{{ $meta->porcentaje_completado }}%</strong>
                    </div>
                    <div class="progress progress--thin mt-3"><div class="progress-bar" style="width: {{ min(100, $meta->porcentaje_completado) }}%"></div></div>
                    <div class="small text-secondary mt-2">Avance @cop($meta->monto_actual_centavos) de @cop($meta->objetivo_centavos) · En bolsillo: @cop($saldoBolsillo)</div>
                    <div class="small text-secondary">Plan/mes: @cop($meta->aporte_mensual_centavos) · Estimado: {{ $meta->fecha_estimada_cumplimiento ? \Carbon\Carbon::parse($meta->fecha_estimada_cumplimiento)->format('d/m/Y') : 'sin plan mensual' }}</div>

                    <div class="account-card__actions mt-3 d-flex flex-wrap gap-2">
                        <button type="button" class="card-btn card-btn--primary" @disabled(! $puedeRetirar)
                            @if($puedeRetirar) data-bs-toggle="collapse" data-bs-target="#retiro-meta-{{ $meta->id }}" aria-expanded="{{ $abrirRetiro ? 'true' : 'false' }}" aria-controls="retiro-meta-{{ $meta->id }}" @endif
                            title="{{ $puedeRetirar ? 'Retirar a cuenta operativa' : 'Sin saldo en el bolsillo' }}">
                            Retirar
                        </button>
                        <button type="button" class="card-btn card-btn--muted" data-bs-toggle="collapse" data-bs-target="#editar-meta-{{ $meta->id }}" aria-expanded="{{ $abrirEditar ? 'true' : 'false' }}" aria-controls="editar-meta-{{ $meta->id }}">
                            Editar
                        </button>
                    </div>

                    <div id="meta-paneles-{{ $meta->id }}">
                    @if($puedeRetirar)
                    <div class="collapse mt-3 {{ $abrirRetiro ? 'show' : '' }}" id="retiro-meta-{{ $meta->id }}" data-bs-parent="#meta-paneles-{{ $meta->id }}">
                        <form method="POST" action="{{ route('app.metas.retiros.store') }}" class="border rounded-3 p-3"
                              data-swal-confirm
                              data-swal-title="¿Retirar de {{ $meta->nombre }}?"
                              data-swal-text="El dinero sale del bolsillo hacia una cuenta operativa. Baja el avance de la meta. El bolsillo sigue existiendo y no se vuelve operativa."
                              data-swal-icon="warning" data-swal-confirm-text="Sí, retirar" novalidate>
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $esteRetiro ? old('idempotency_key', $idempotencyKeyRetiro) : $idempotencyKeyRetiro }}-{{ $meta->id }}">
                            <input type="hidden" name="meta_ahorro_id" value="{{ $meta->id }}">
                            <p class="small text-secondary mb-2">Disponible en bolsillo: <strong>@cop($saldoBolsillo)</strong></p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label visually-hidden" for="monto_retiro_{{ $meta->id }}">Monto</label>
                                    <input id="monto_retiro_{{ $meta->id }}" name="monto" data-miles inputmode="decimal"
                                           value="{{ $esteRetiro ? old('monto') : ($saldoBolsillo / 100) }}"
                                           class="form-control form-control-sm @if($esteRetiro) @error('monto', 'retiro') is-invalid @enderror @endif"
                                           placeholder="Monto" required>
                                    @if($esteRetiro)
                                        @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'retiro'])
                                    @endif
                                </div>
                                <div class="col-6">
                                    <label class="form-label visually-hidden" for="destino_retiro_{{ $meta->id }}">Destino</label>
                                    <select id="destino_retiro_{{ $meta->id }}" name="cuenta_destino_id"
                                            class="form-select form-select-sm @if($esteRetiro) @error('cuenta_destino_id', 'retiro') is-invalid @enderror @endif" required>
                                        <option value="">Cuenta destino</option>
                                        @foreach($cuentasOperativas as $cuenta)
                                            <option value="{{ $cuenta->id }}" @selected($esteRetiro && old('cuenta_destino_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                                        @endforeach
                                    </select>
                                    @if($esteRetiro)
                                        @include('layouts.partials.field-error', ['name' => 'cuenta_destino_id', 'bag' => 'retiro'])
                                    @endif
                                </div>
                                <div class="col-6">
                                    <input name="fecha" type="date" value="{{ $esteRetiro ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <button class="card-btn card-btn--warn w-100" type="submit">Confirmar retiro</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    @endif

                    <div class="collapse mt-3 {{ $abrirEditar ? 'show' : '' }}" id="editar-meta-{{ $meta->id }}" data-bs-parent="#meta-paneles-{{ $meta->id }}">
                        <form method="POST" action="{{ route('app.metas.update', $meta) }}" class="border rounded-3 p-3" novalidate>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKeyEditar }}-{{ $meta->id }}">
                            <input type="hidden" name="_meta_edit" value="{{ $meta->id }}">
                            <div class="row g-2">
                                <div class="col-12">
                                    <input name="nombre" value="{{ $esteEditar ? old('nombre', $meta->nombre) : $meta->nombre }}" class="form-control form-control-sm" placeholder="Nombre">
                                </div>
                                <div class="col-6">
                                    <input name="objetivo" data-miles inputmode="decimal" value="{{ $esteEditar ? old('objetivo') : ($meta->objetivo_centavos / 100) }}" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-6">
                                    <input name="fecha_objetivo" type="date" value="{{ $esteEditar ? old('fecha_objetivo', $meta->fecha_objetivo?->toDateString()) : $meta->fecha_objetivo?->toDateString() }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-6">
                                    <input name="aporte_mensual" data-miles inputmode="decimal" value="{{ $esteEditar ? old('aporte_mensual', $meta->aporte_mensual_centavos / 100) : ($meta->aporte_mensual_centavos / 100) }}" class="form-control form-control-sm" placeholder="Plan mensual">
                                </div>
                                <div class="col-6">
                                    <select name="prioridad" class="form-select form-select-sm">
                                        @foreach(['alta'=>'Alta','media'=>'Media','baja'=>'Baja'] as $v=>$t)
                                            <option value="{{ $v }}" @selected(($esteEditar ? old('prioridad', $meta->prioridad) : $meta->prioridad) === $v)>{{ $t }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button class="card-btn card-btn--primary" type="submit">Guardar cambios</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    </div>
                </div>
            </div>
        </article>
        @empty
            <div class="alert alert-light empty-state">Aún no tienes metas de ahorro.</div>
        @endforelse
        </div>
    </div>

    @if($movimientos->isNotEmpty())
    <div class="list-block">
        <div class="list-block__head">
            <h2>Aportes y retiros recientes</h2>
            <span>{{ $movimientos->count() }}</span>
        </div>
        <div class="card-stack">
            @foreach($movimientos as $mov)
                @php
                    $corregido = in_array((int) $mov->id, $idsRevertidos, true);
                    $esAporte = $mov->tipo === \App\Enums\TipoHechoTesoreria::AporteMeta;
                    $puedeCorregir = ! $corregido && in_array((int) $mov->id, $ultimoPorMeta, true);
                @endphp
                <article class="account-card">
                    <div class="account-card__main">
                        <div class="account-card__body">
                            <div class="account-card__head">
                                <div class="account-card__title">
                                    <h2>{{ $mov->metaAhorro?->nombre ?: 'Meta' }}</h2>
                                    <small>
                                        {{ $mov->fecha?->format('d/m/Y') }}
                                        · {{ $esAporte ? 'Aporte' : 'Retiro' }}
                                        @if($esAporte && $mov->cuentaLiquida) · desde {{ $mov->cuentaLiquida->nombre }}@endif
                                        @if(! $esAporte && $mov->cuentaDestino) · a {{ $mov->cuentaDestino->nombre }}@endif
                                        @if($corregido) · <span class="text-danger">Corregido</span>@endif
                                    </small>
                                </div>
                                <strong class="account-card__amount {{ $corregido ? 'text-secondary' : '' }}">
                                    @if($corregido)<s>@endif @cop($mov->monto_centavos) @if($corregido)</s>@endif
                                </strong>
                            </div>
                            @if($puedeCorregir)
                                <div class="account-card__actions mt-2">
                                    <form method="POST" action="{{ route('app.metas.movimientos.corregir', $mov) }}" class="d-inline"
                                          data-swal-confirm data-swal-title="¿Corregir este movimiento?"
                                          data-swal-text="Reverso contable y actualización del avance (solo el último de esa meta)."
                                          data-swal-icon="warning" data-swal-confirm-text="Corregir">
                                        @csrf
                                        <input type="hidden" name="motivo" value="Corrección #{{ $mov->id }}">
                                        <button class="card-btn card-btn--warn" type="submit">Corregir</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
