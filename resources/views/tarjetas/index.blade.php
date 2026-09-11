@extends('layouts.app', [
    'title' => 'Tarjetas',
    'heading' => 'Mis tarjetas',
    'subtitle' => 'Compra, avance y pago (mínimo = cuota; exceso = abono a capital).',
])
@section('content')
@php
    $errCompra = $errors->compra;
    $errPagoTarjeta = $errors->pago_tarjeta;
    $oldCompra = $errCompra->any();
    $tipoMov = $oldCompra ? old('tipo', 'compra') : 'compra';
@endphp

@include('layouts.partials.form-errors', ['bag' => 'pago_tarjeta'])

<div class="page-toolbar">
    <a class="btn btn-primary btn-lg" href="{{ route('app.tarjetas.create') }}">
        @include('layouts.partials.icon', ['name' => 'plus', 'class' => 'ui-icon ui-icon--sm'])
        Nueva tarjeta
    </a>
</div>

<div class="capture-flow capture-flow--wide">
    <div class="list-block list-block--flush">
        <div class="list-block__head">
            <h2>Tarjetas activas</h2>
            <span>{{ $tarjetas->count() }}</span>
        </div>
        <div class="card-stack">
        @forelse($tarjetas as $tarjeta)
        <article class="account-card">
            <div class="account-card__main">
                <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'credit-card', 'class' => 'ui-icon ui-icon--sm'])</div>
                <div class="account-card__body">
                    <div class="account-card__head">
                        <div class="account-card__title">
                            <h2>{{ $tarjeta->nombre }}</h2>
                            <small>{{ $tarjeta->entidad ?: 'Tarjeta de crédito' }}</small>
                        </div>
                        <strong class="account-card__amount">@cop($tarjeta->saldo_actual_centavos)</strong>
                    </div>
                    <div class="debt-meta">
                        <span>Disponible @cop($tarjeta->cupo_disponible_centavos)</span>
                        <span>Cupo @cop($tarjeta->cupo_centavos)</span>
                    </div>
                    <div class="small text-secondary mt-2">
                        Corte día {{ $tarjeta->dia_corte }} · Pago día {{ $tarjeta->dia_pago }}
                        · Compras {{ number_format((float) $tarjeta->tasa_compras_mensual, 2, ',', '.') }}%
                        · Avances {{ number_format((float) $tarjeta->tasa_avances_mensual, 2, ',', '.') }}%
                    </div>
                    <div class="small text-secondary mt-1">Pago mínimo: <strong>@cop($tarjeta->pago_minimo_centavos)</strong> · Pago total: <strong>@cop($tarjeta->pago_total_centavos)</strong></div>
                </div>
            </div>
            <div class="account-card__footer">
                <details @if($errPagoTarjeta->any() && (int) old('tarjeta_credito_id') === (int) $tarjeta->id) open @endif>
                    <summary class="small">Ver compras y cuotas</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm small mb-0">
                            <thead><tr><th>Movimiento</th><th>Fecha</th><th>Monto</th><th>Cuotas</th></tr></thead>
                            <tbody>
                            @foreach($tarjeta->compras->where('anulada', false) as $compra)
                                <tr>
                                    <td>
                                        {{ $compra->descripcion }}
                                        <span class="text-secondary">({{ $compra->tipo === 'avance' ? 'avance' : 'compra' }} · {{ number_format((float) $compra->tasa_interes_porcentaje, 2, ',', '.') }}%)</span>
                                        @if($compra->cuotasProgramadas->where('pagada', true)->isEmpty())
                                            <form method="POST" action="{{ route('app.tarjetas.compras.corregir', $compra) }}" class="d-inline ms-1"
                                                data-swal-confirm
                                                data-swal-title="¿Anular esta operación?"
                                                data-swal-text="Se registra un reverso en el libro y se eliminan las cuotas pendientes."
                                                data-swal-icon="warning"
                                                data-swal-confirm-text="Anular">
                                                @csrf
                                                <input type="hidden" name="motivo" value="Anulación #{{ $compra->id }}">
                                                <button class="card-btn card-btn--warn" type="submit">Anular</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td>{{ $compra->fecha->format('d/m/Y') }}</td>
                                    <td>@cop($compra->monto_centavos)</td>
                                    <td>{{ $compra->cuotasProgramadas->count() }} ({{ $compra->cuotasProgramadas->where('pagada', false)->count() }} pendientes)</td>
                                </tr>
                                @foreach($compra->cuotasProgramadas as $cuota)
                                <tr class="text-secondary">
                                    <td>↳ Cuota {{ $cuota->numero }}</td>
                                    <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                                    <td>@cop($cuota->capital_centavos + $cuota->interes_centavos)</td>
                                    <td>
                                        {{ $cuota->pagada ? 'Pagada' : 'Pendiente' }}
                                        @unless($cuota->pagada)
                                        @php
                                            $estePagoCuota = $errPagoTarjeta->any() && (int) old('cuota_tarjeta_id') === (int) $cuota->id;
                                            $minimoCuota = (int) $cuota->capital_centavos + (int) $cuota->interes_centavos;
                                            $montoPago = $estePagoCuota ? old('monto', $minimoCuota / 100) : ($minimoCuota / 100);
                                        @endphp
                                        <form method="POST" action="{{ route('app.tarjetas.pagos.store') }}" class="d-inline-flex flex-wrap align-items-center gap-1 ms-2" novalidate>
                                            @csrf
                                            <input type="hidden" name="idempotency_key" value="{{ $estePagoCuota ? old('idempotency_key', $idempotencyKeysPago[$tarjeta->id] ?? '') : ($idempotencyKeysPago[$tarjeta->id] ?? '') }}">
                                            <input type="hidden" name="tarjeta_credito_id" value="{{ $tarjeta->id }}">
                                            <input type="hidden" name="cuota_tarjeta_id" value="{{ $cuota->id }}">
                                            <input type="hidden" name="fecha" value="{{ now()->toDateString() }}">
                                            <input name="monto" data-miles inputmode="decimal" value="{{ $montoPago }}"
                                                class="form-control form-control-sm d-inline-block @if($estePagoCuota) @error('monto', 'pago_tarjeta') is-invalid @enderror @endif"
                                                style="width: 7.5rem" title="Mínimo = cuota; exceso = abono a capital" required>
                                            <select name="cuenta_liquida_id" class="form-select form-select-sm d-inline-block w-auto @if($estePagoCuota) @error('cuenta_liquida_id', 'pago_tarjeta') is-invalid @enderror @endif" required>
                                                <option value="">Cuenta</option>
                                                @foreach($cuentas as $cuenta)
                                                    <option value="{{ $cuenta->id }}" @selected($estePagoCuota && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                                                @endforeach
                                            </select>
                                            <button class="card-btn card-btn--primary" type="submit">
                                                @include('layouts.partials.icon', ['name' => 'money', 'class' => 'ui-icon ui-icon--xs'])
                                                Pagar
                                            </button>
                                            @if($estePagoCuota)
                                                @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'pago_tarjeta'])
                                                @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'pago_tarjeta'])
                                            @endif
                                        </form>
                                        @endunless
                                    </td>
                                </tr>
                                @endforeach
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </article>
        @empty
            <div class="alert alert-light empty-state">Aún no tienes tarjetas registradas. <a href="{{ route('app.tarjetas.create') }}">Crear una</a>.</div>
        @endforelse
        </div>
    </div>

    @if($tarjetas->isNotEmpty())
    <form method="POST" action="{{ route('app.tarjetas.compras.store') }}" class="dash-card form-panel" id="form-movimiento-tarjeta" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKeyCompra) }}">
        <div class="form-section__head">
            <p class="form-section__eyebrow">Movimiento</p>
            <h2 class="form-section__title">Registrar compra o avance</h2>
        </div>
        @include('layouts.partials.form-errors', ['bag' => 'compra'])
        <p class="small text-secondary mb-3">Compra a 1 cuota = corriente sin interés programado. A 2+ cuotas se usa la tasa de compras de la tarjeta; un avance siempre usa la de avances.</p>

        <div class="money-hero">
            <label class="form-label" for="monto_compra">Monto</label>
            <input id="monto_compra" name="monto" data-miles inputmode="decimal" value="{{ $oldCompra ? old('monto') : '' }}" class="form-control form-control-lg @error('monto', 'compra') is-invalid @enderror" placeholder="0" required>
            @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'compra'])
        </div>

        <section class="form-section">
            <div>
                <label class="form-label" for="tipo_movimiento">Tipo</label>
                <select id="tipo_movimiento" name="tipo" class="form-select form-select-lg @error('tipo', 'compra') is-invalid @enderror" required>
                    <option value="compra" @selected($tipoMov === 'compra')>Compra</option>
                    <option value="avance" @selected($tipoMov === 'avance')>Avance en efectivo</option>
                </select>
                @include('layouts.partials.field-error', ['name' => 'tipo', 'bag' => 'compra'])
            </div>
            <div>
                <label class="form-label" for="tarjeta_credito_id">Tarjeta</label>
                <select id="tarjeta_credito_id" name="tarjeta_credito_id" class="form-select form-select-lg @error('tarjeta_credito_id', 'compra') is-invalid @enderror" required>
                    <option value="">Selecciona tarjeta</option>
                    @foreach($tarjetas as $tarjeta)
                        <option value="{{ $tarjeta->id }}"
                            data-tasa-compras="{{ number_format((float) $tarjeta->tasa_compras_mensual, 2, '.', '') }}"
                            data-tasa-avances="{{ number_format((float) $tarjeta->tasa_avances_mensual, 2, '.', '') }}"
                            @selected($oldCompra && old('tarjeta_credito_id') == $tarjeta->id)>{{ $tarjeta->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'tarjeta_credito_id', 'bag' => 'compra'])
                <p class="small text-secondary mt-1" id="hint-tasa-tarjeta" hidden></p>
            </div>
            <div>
                <label class="form-label" for="descripcion_compra">Descripción</label>
                <input id="descripcion_compra" name="descripcion" value="{{ $oldCompra ? old('descripcion') : '' }}" class="form-control form-control-lg @error('descripcion', 'compra') is-invalid @enderror" placeholder="Qué compraste o el motivo del avance" required>
                @include('layouts.partials.field-error', ['name' => 'descripcion', 'bag' => 'compra'])
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="fecha_compra">Fecha</label>
                    <input id="fecha_compra" name="fecha" type="date" value="{{ $oldCompra ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control form-control-lg @error('fecha', 'compra') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'compra'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="cuotas">Cuotas</label>
                    <input id="cuotas" name="cuotas" type="number" min="1" max="60" value="{{ $oldCompra ? old('cuotas', 1) : 1 }}" class="form-control form-control-lg @error('cuotas', 'compra') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'cuotas', 'bag' => 'compra'])
                </div>
            </div>
            <div id="campo-categoria" @if($tipoMov === 'avance') hidden @endif>
                <label class="form-label" for="categoria_compra">Categoría</label>
                <select id="categoria_compra" name="categoria_id" class="form-select form-select-lg @error('categoria_id', 'compra') is-invalid @enderror" @if($tipoMov !== 'avance') required @endif>
                    <option value="">Categoría</option>
                    @foreach($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected($oldCompra && old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'categoria_id', 'bag' => 'compra'])
            </div>
            <div id="campo-cuenta-avance" @if($tipoMov !== 'avance') hidden @endif>
                <label class="form-label" for="cuenta_avance">Cuenta destino del avance</label>
                <select id="cuenta_avance" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'compra') is-invalid @enderror" @if($tipoMov === 'avance') required @endif>
                    <option value="">Cuenta operativa</option>
                    @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected($oldCompra && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'compra'])
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar movimiento</button>
        </div>
    </form>
    @endif

    @if(($pagosRecientes ?? collect())->isNotEmpty())
    <div class="list-block mt-4">
        <div class="list-block__head">
            <h2>Pagos recientes</h2>
            <span>{{ $pagosRecientes->count() }}</span>
        </div>
        <div class="card-stack">
            @foreach($pagosRecientes as $pago)
                @php
                    $corregido = in_array((int) $pago->id, $idsRevertidos, true);
                    $esUltimo = in_array((int) $pago->id, $ultimoPagoPorTarjeta, true);
                @endphp
                <article class="account-card">
                    <div class="account-card__main">
                        <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'money', 'class' => 'ui-icon ui-icon--sm'])</div>
                        <div class="account-card__body">
                            <div class="account-card__head">
                                <div class="account-card__title">
                                    <h2>{{ $pago->tarjetaCredito?->nombre ?: 'Tarjeta' }}</h2>
                                    <small>
                                        {{ $pago->fecha?->format('d/m/Y') }}
                                        @if($pago->cuentaLiquida) · {{ $pago->cuentaLiquida->nombre }}@endif
                                        @if($corregido) · <span class="text-danger">Corregido</span>@endif
                                    </small>
                                </div>
                                <strong class="account-card__amount {{ $corregido ? 'text-secondary' : '' }}">
                                    @if($corregido)<s>@endif @cop($pago->monto_centavos) @if($corregido)</s>@endif
                                </strong>
                            </div>
                            @if(! $corregido && $esUltimo)
                                <div class="account-card__actions mt-2">
                                    <form method="POST" action="{{ route('app.tarjetas.pagos.corregir', $pago) }}" class="d-inline"
                                        data-swal-confirm
                                        data-swal-title="¿Corregir este pago?"
                                        data-swal-text="Se registra un reverso en el libro y se reabre la cuota."
                                        data-swal-icon="warning"
                                        data-swal-confirm-text="Corregir">
                                        @csrf
                                        <input type="hidden" name="motivo" value="Corrección de pago #{{ $pago->id }}">
                                        <button class="card-btn card-btn--warn" type="submit">
                                            @include('layouts.partials.icon', ['name' => 'ban', 'class' => 'ui-icon ui-icon--xs'])
                                            Corregir
                                        </button>
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

@push('scripts')
<script>
(function () {
    const tipo = document.getElementById('tipo_movimiento');
    const catBox = document.getElementById('campo-categoria');
    const catSelect = document.getElementById('categoria_compra');
    const avBox = document.getElementById('campo-cuenta-avance');
    const avSelect = document.getElementById('cuenta_avance');
    const tarjeta = document.getElementById('tarjeta_credito_id');
    const cuotas = document.getElementById('cuotas');
    const hint = document.getElementById('hint-tasa-tarjeta');
    if (!tipo || !catBox || !avBox) return;

    function syncTipo() {
        const esAvance = tipo.value === 'avance';
        catBox.hidden = esAvance;
        avBox.hidden = !esAvance;
        if (catSelect) {
            catSelect.required = !esAvance;
            if (esAvance) catSelect.value = '';
        }
        if (avSelect) {
            avSelect.required = esAvance;
            if (!esAvance) avSelect.value = '';
        }
        syncTasa();
    }

    function syncTasa() {
        if (!tarjeta || !hint) return;
        const opt = tarjeta.options[tarjeta.selectedIndex];
        if (!opt || !opt.value) {
            hint.hidden = true;
            return;
        }
        const nCuotas = Math.max(1, parseInt(cuotas && cuotas.value ? cuotas.value : '1', 10) || 1);
        if (tipo.value === 'compra' && nCuotas < 2) {
            hint.textContent = 'Corriente (1 cuota): sin interés programado. Si diferís el pago, el revolving aún no se modela.';
            hint.hidden = false;
            return;
        }
        const key = tipo.value === 'avance' ? 'tasaAvances' : 'tasaCompras';
        const tasa = opt.dataset[key] || '0';
        const etiqueta = tipo.value === 'avance' ? 'avances' : 'compras';
        hint.textContent = 'Tasa que se aplicará: ' + tasa.replace('.', ',') + '% mensual (' + etiqueta + ').';
        hint.hidden = false;
    }

    tipo.addEventListener('change', syncTipo);
    tarjeta && tarjeta.addEventListener('change', syncTasa);
    cuotas && cuotas.addEventListener('input', syncTasa);
    cuotas && cuotas.addEventListener('change', syncTasa);
    syncTipo();
})();
</script>
@endpush
