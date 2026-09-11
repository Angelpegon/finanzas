@extends('layouts.app', [
    'title' => 'Movimientos',
    'heading' => 'Movimientos',
    'subtitle' => 'Entre tus cuentas, o pagos a terceros y deudas rastreadas.',
])
@section('content')
    @php
        $errXfer = $errors->transferencia;
        $errPago = $errors->pago;
        $oldXfer = $errXfer->any();
        $oldPago = $errPago->any();
        $modoPago = $oldPago || request()->boolean('pago');
        $tipoPago = $oldPago ? old('tipo', 'deuda_personal') : 'deuda_personal';
        $esPrestamo = $tipoPago === 'prestamo';
        $esTarjeta = $tipoPago === 'tarjeta';
        $esObligacion = $esPrestamo || $esTarjeta;
    @endphp

    @include('layouts.partials.form-errors')

    <div class="capture-flow">
        <nav class="flow-tabs" aria-label="Tipo de movimiento">
            <a href="{{ route('app.pagos.index') }}" class="{{ !$modoPago ? 'is-active' : '' }}">Entre mis cuentas</a>
            <a href="{{ route('app.pagos.index', ['pago' => 1]) }}" class="{{ $modoPago ? 'is-active' : '' }}">Pagar a terceros / deudas</a>
        </nav>

        @if (!$modoPago)
            @php
                $destinoXfer = $oldXfer ? old('cuenta_destino_id') : '';
                $esDestinoOtra = $destinoXfer === 'otra';
            @endphp
            @if ($cuentas->isNotEmpty())
                <form method="POST" action="{{ route('app.transferencias.store') }}" class="dash-card form-panel"
                    id="form-transferencia" novalidate>
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKeyXfer) }}">
                    <div class="form-section__head">
                        <p class="form-section__eyebrow">Salida de liquidez</p>
                        <h2 class="form-section__title">Mover o enviar dinero</h2>
                    </div>
                    <p class="small text-secondary mb-3">Entre tus cuentas no es gasto. Si eliges <strong>Otra</strong> en
                        destino, el dinero sale de tu patrimonio y se registra como gasto.</p>
                    @include('layouts.partials.form-errors', ['bag' => 'transferencia'])

                    <div class="money-hero">
                        <label class="form-label" for="monto_transferencia">Monto</label>
                        <input id="monto_transferencia" name="monto" data-miles inputmode="decimal"
                            value="{{ $oldXfer ? old('monto') : '' }}"
                            class="form-control form-control-lg @error('monto', 'transferencia') is-invalid @enderror"
                            placeholder="0" required>
                        @include('layouts.partials.field-error', [
                            'name' => 'monto',
                            'bag' => 'transferencia',
                        ])
                    </div>

                    <section class="form-section">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="cuenta_liquida_id">Origen</label>
                                <select id="cuenta_liquida_id" name="cuenta_liquida_id"
                                    class="form-select form-select-lg @error('cuenta_liquida_id', 'transferencia') is-invalid @enderror"
                                    required>
                                    <option value="">Cuenta</option>
                                    @foreach ($cuentas as $cuenta)
                                        <option value="{{ $cuenta->id }}" @selected($oldXfer && old('cuenta_liquida_id') == $cuenta->id)>
                                            {{ $cuenta->nombre }}</option>
                                    @endforeach
                                </select>
                                @include('layouts.partials.field-error', [
                                    'name' => 'cuenta_liquida_id',
                                    'bag' => 'transferencia',
                                ])
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cuenta_destino_id">Destino</label>
                                <select id="cuenta_destino_id" name="cuenta_destino_id"
                                    class="form-select form-select-lg @error('cuenta_destino_id', 'transferencia') is-invalid @enderror"
                                    required>
                                    <option value="">Cuenta</option>
                                    @foreach ($cuentas as $cuenta)
                                        <option value="{{ $cuenta->id }}" @selected($oldXfer && (string) old('cuenta_destino_id') === (string) $cuenta->id)>
                                            {{ $cuenta->nombre }}</option>
                                    @endforeach
                                    <option value="otra" @selected($esDestinoOtra)>Otra (cuenta externa)</option>
                                </select>
                                @include('layouts.partials.field-error', [
                                    'name' => 'cuenta_destino_id',
                                    'bag' => 'transferencia',
                                ])
                            </div>
                        </div>

                        <div id="campos-destino-otra" @unless ($esDestinoOtra) hidden @endunless>
                            <div class="mt-3">
                                <label class="form-label" for="destino_externo">Destinatario / cuenta externa</label>
                                <input id="destino_externo" name="destino" value="{{ $oldXfer ? old('destino') : '' }}"
                                    class="form-control form-control-lg @error('destino', 'transferencia') is-invalid @enderror"
                                    placeholder="Ej. Juan · Nequi, arriendo, banco X"
                                    @if ($esDestinoOtra) required @endif>
                                @include('layouts.partials.field-error', [
                                    'name' => 'destino',
                                    'bag' => 'transferencia',
                                ])
                            </div>
                            <div class="mt-3">
                                <label class="form-label" for="categoria_xfer">Categoría de gasto</label>
                                <select id="categoria_xfer" name="categoria_id"
                                    class="form-select form-select-lg @error('categoria_id', 'transferencia') is-invalid @enderror"
                                    @if ($esDestinoOtra) required @endif>
                                    <option value="">Categoría</option>
                                    @foreach ($categorias as $categoria)
                                        <option value="{{ $categoria->id }}" @selected($oldXfer && old('categoria_id') == $categoria->id)>
                                            {{ $categoria->nombre }}</option>
                                    @endforeach
                                </select>
                                @include('layouts.partials.field-error', [
                                    'name' => 'categoria_id',
                                    'bag' => 'transferencia',
                                ])
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="fecha_transferencia">Fecha</label>
                            <input id="fecha_transferencia" name="fecha" type="date"
                                value="{{ $oldXfer ? old('fecha', now()->toDateString()) : now()->toDateString() }}"
                                class="form-control form-control-lg @error('fecha', 'transferencia') is-invalid @enderror"
                                required>
                            @include('layouts.partials.field-error', [
                                'name' => 'fecha',
                                'bag' => 'transferencia',
                            ])
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="descripcion_transferencia">Descripción <span
                                    class="text-secondary">(opcional)</span></label>
                            <input id="descripcion_transferencia" name="descripcion"
                                value="{{ $oldXfer ? old('descripcion') : '' }}"
                                class="form-control form-control-lg @error('descripcion', 'transferencia') is-invalid @enderror"
                                placeholder="Motivo">
                            @include('layouts.partials.field-error', [
                                'name' => 'descripcion',
                                'bag' => 'transferencia',
                            ])
                        </div>
                    </section>

                    <div class="form-actions">
                        <button class="btn btn-primary btn-lg w-100" type="submit"
                            id="btn-transferencia">Contabilizar</button>
                    </div>
                </form>
            @else
                <div class="alert alert-light empty-state">Necesitas una cuenta operativa. <a
                        href="{{ route('app.cuentas.create') }}">Nueva cuenta</a></div>
            @endif
        @else
            @if ($cuentas->isEmpty())
                <div class="alert alert-light empty-state">Necesitas una cuenta operativa para pagar. <a
                        href="{{ route('app.cuentas.create') }}">Nueva cuenta</a></div>
            @else
                <form method="POST" action="{{ route('app.pagos.store') }}" class="dash-card form-panel"
                    id="form-pago-movimientos" novalidate>
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKeyPago) }}">
                    <div class="form-section__head">
                        <p class="form-section__eyebrow">Salida</p>
                        <h2 class="form-section__title">Pagar a un tercero o una deuda</h2>
                    </div>
                    <p class="small text-secondary mb-3">Los gastos del día a día van en <a
                            href="{{ route('app.gastos.create') }}">Gastos</a>. Las tarjetas se pagan aquí o en <a
                            href="{{ route('app.tarjetas.index') }}">Tarjetas</a>: mínimo = próxima cuota; el exceso es abono a capital.</p>
                    @include('layouts.partials.form-errors', ['bag' => 'pago'])

                    <div class="money-hero">
                        <label class="form-label" for="monto_pago">Monto</label>
                        <input id="monto_pago" name="monto" data-miles inputmode="decimal"
                            value="{{ $oldPago ? old('monto') : '' }}"
                            class="form-control form-control-lg @error('monto', 'pago') is-invalid @enderror"
                            placeholder="0" required>
                        @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'pago'])
                    </div>

                    <section class="form-section">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label" for="tipo">Qué pagas</label>
                                <select id="tipo" name="tipo"
                                    class="form-select form-select-lg @error('tipo', 'pago') is-invalid @enderror"
                                    required>
                                    <option value="deuda_personal" @selected($tipoPago === 'deuda_personal')>A persona / cuenta externa
                                    </option>
                                    <option value="otra_obligacion" @selected($tipoPago === 'otra_obligacion')>Otra obligación (sin
                                        cronograma)</option>
                                    <option value="prestamo" @selected($tipoPago === 'prestamo')>Deuda rastreada (préstamo)
                                    </option>
                                    <option value="tarjeta" @selected($tipoPago === 'tarjeta')>Tarjeta de crédito
                                    </option>
                                </select>
                                @include('layouts.partials.field-error', [
                                    'name' => 'tipo',
                                    'bag' => 'pago',
                                ])
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="fecha_pago">Fecha</label>
                                <input id="fecha_pago" name="fecha" type="date"
                                    value="{{ $oldPago ? old('fecha', now()->toDateString()) : now()->toDateString() }}"
                                    class="form-control form-control-lg @error('fecha', 'pago') is-invalid @enderror"
                                    required>
                                @include('layouts.partials.field-error', [
                                    'name' => 'fecha',
                                    'bag' => 'pago',
                                ])
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="cuenta_pago">Cuenta con la que pagas</label>
                            <select id="cuenta_pago" name="cuenta_liquida_id"
                                class="form-select form-select-lg @error('cuenta_liquida_id', 'pago') is-invalid @enderror"
                                required>
                                <option value="">Selecciona una cuenta</option>
                                @foreach ($cuentas as $cuenta)
                                    <option value="{{ $cuenta->id }}" @selected($oldPago && old('cuenta_liquida_id') == $cuenta->id)>
                                        {{ $cuenta->nombre }}</option>
                                @endforeach
                            </select>
                            @include('layouts.partials.field-error', [
                                'name' => 'cuenta_liquida_id',
                                'bag' => 'pago',
                            ])
                        </div>

                        <div id="campo-prestamo" @unless ($esPrestamo) hidden @endunless>
                            <label class="form-label" for="prestamo_id">Obligación</label>
                            <select id="prestamo_id" name="prestamo_id"
                                class="form-select form-select-lg @error('prestamo_id', 'pago') is-invalid @enderror"
                                @if ($esPrestamo) required @endif>
                                <option value="">Elige la deuda</option>
                                @forelse($prestamos as $prestamo)
                                    @php
                                        $proxima = ($prestamo->cuotas ?? collect())->first();
                                        $minimo = $proxima
                                            ? (int) ($proxima->total_centavos ?:
                                            $proxima->capital_centavos +
                                                $proxima->interes_centavos +
                                                $proxima->seguro_centavos +
                                                $proxima->otros_cargos_centavos)
                                            : 0;
                                    @endphp
                                    <option value="{{ $prestamo->id }}" data-minimo="{{ $minimo / 100 }}"
                                        @selected($oldPago && old('prestamo_id') == $prestamo->id)>
                                        {{ $prestamo->nombre }}@if ($prestamo->entidad)
                                            · {{ $prestamo->entidad }}
                                        @endif
                                        @if ($proxima)
                                            — cuota {{ $proxima->numero }} desde @cop($minimo)
                                        @endif
                                    </option>
                                @empty
                                    <option value="" disabled>No hay deudas activas. Créalas en Deudas.</option>
                                @endforelse
                            </select>
                            @include('layouts.partials.field-error', [
                                'name' => 'prestamo_id',
                                'bag' => 'pago',
                            ])
                            <p class="small text-secondary mt-1">El pago aplica a la siguiente cuota pendiente (mínimo =
                                total de esa cuota). Abono extra reduce plazo.</p>
                        </div>

                        <div id="campo-tarjeta" @unless ($esTarjeta) hidden @endunless>
                            <label class="form-label" for="tarjeta_credito_id">Tarjeta</label>
                            <select id="tarjeta_credito_id" name="tarjeta_credito_id"
                                class="form-select form-select-lg @error('tarjeta_credito_id', 'pago') is-invalid @enderror"
                                @if ($esTarjeta) required @endif>
                                <option value="">Elige la tarjeta</option>
                                @forelse($tarjetas as $tarjeta)
                                    <option value="{{ $tarjeta->id }}" data-minimo="{{ $tarjeta->minimo_centavos / 100 }}"
                                        @selected($oldPago && old('tarjeta_credito_id') == $tarjeta->id)>
                                        {{ $tarjeta->nombre }}@if ($tarjeta->entidad)
                                            · {{ $tarjeta->entidad }}
                                        @endif
                                        @if ($tarjeta->proxima_numero)
                                            — cuota {{ $tarjeta->proxima_numero }} desde @cop($tarjeta->minimo_centavos)
                                        @endif
                                    </option>
                                @empty
                                    <option value="" disabled>No hay tarjetas con cuotas pendientes. Gestiona compras en Tarjetas.</option>
                                @endforelse
                            </select>
                            @include('layouts.partials.field-error', [
                                'name' => 'tarjeta_credito_id',
                                'bag' => 'pago',
                            ])
                            <p class="small text-secondary mt-1">Aplica a la próxima cuota pendiente. El exceso es abono a capital (acorta plazo; condona interés de cuotas que desaparecen).</p>
                        </div>

                        <div id="campos-tercero" @if ($esObligacion) hidden @endif>
                            <div>
                                <label class="form-label" for="categoria_id">Categoría</label>
                                <select id="categoria_id" name="categoria_id"
                                    class="form-select form-select-lg @error('categoria_id', 'pago') is-invalid @enderror"
                                    @unless ($esObligacion) required @endunless>
                                    <option value="">Categoría de gasto</option>
                                    @foreach ($categorias as $categoria)
                                        <option value="{{ $categoria->id }}" @selected($oldPago && old('categoria_id') == $categoria->id)>
                                            {{ $categoria->nombre }}</option>
                                    @endforeach
                                </select>
                                @include('layouts.partials.field-error', [
                                    'name' => 'categoria_id',
                                    'bag' => 'pago',
                                ])
                            </div>
                            <div class="mt-3">
                                <label class="form-label" for="destino">Destinatario / cuenta externa</label>
                                <input id="destino" name="destino" value="{{ $oldPago ? old('destino') : '' }}"
                                    class="form-control form-control-lg @error('destino', 'pago') is-invalid @enderror"
                                    placeholder="Ej. Juan Pérez · Nequi, arriendo, banco X"
                                    @unless ($esObligacion) required @endunless>
                                @include('layouts.partials.field-error', [
                                    'name' => 'destino',
                                    'bag' => 'pago',
                                ])
                            </div>
                            <div class="mt-3">
                                <label class="form-label" for="referencia">Referencia única</label>
                                <input id="referencia" name="referencia" value="{{ $oldPago ? old('referencia') : '' }}"
                                    class="form-control form-control-lg @error('referencia', 'pago') is-invalid @enderror"
                                    placeholder="N.º de transferencia o factura"
                                    @unless ($esObligacion) required @endunless>
                                @include('layouts.partials.field-error', [
                                    'name' => 'referencia',
                                    'bag' => 'pago',
                                ])
                            </div>
                            <div class="mt-3">
                                <label class="form-label" for="observaciones">Observaciones <span
                                        class="text-secondary">(opcional)</span></label>
                                <textarea id="observaciones" name="observaciones"
                                    class="form-control @error('observaciones', 'pago') is-invalid @enderror" rows="2"
                                    placeholder="Detalle adicional">{{ $oldPago ? old('observaciones') : '' }}</textarea>
                                @include('layouts.partials.field-error', [
                                    'name' => 'observaciones',
                                    'bag' => 'pago',
                                ])
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button class="btn btn-primary btn-lg w-100" type="submit">Registrar pago</button>
                    </div>
                </form>
            @endif
        @endif

        <div class="list-block">
            <div class="list-block__head">
                <h2>Traslados entre tus cuentas</h2>
                <span>{{ $transferencias->total() }}</span>
            </div>
            <div class="card-stack">
                @forelse($transferencias as $t)
                    @php $corregida = in_array((int) $t->id, $idsXferRevertidas, true); @endphp
                    <article class="account-card">
                        <div class="account-card__main">
                            <div class="account-card__icon">@include('layouts.partials.icon', [
                                'name' => 'exchange',
                                'class' => 'ui-icon ui-icon--sm',
                            ])</div>
                            <div class="account-card__body">
                                <div class="account-card__head">
                                    <div class="account-card__title">
                                        <h2>{{ $t->descripcion ?: 'Traslado' }}</h2>
                                        <small>
                                            {{ $t->fecha->format('d/m/Y') }}
                                            · {{ $t->cuentaLiquida?->nombre ?: '?' }} →
                                            {{ $t->cuentaDestino?->nombre ?: '?' }}
                                            @if ($corregida)
                                                · <span class="text-danger">Corregida</span>
                                            @endif
                                        </small>
                                    </div>
                                    <strong class="account-card__amount {{ $corregida ? 'text-secondary' : '' }}">
                                        @if ($corregida)
                                            <s>
                                                @endif @cop($t->monto_centavos) @if ($corregida)
                                            </s>
                                        @endif
                                    </strong>
                                </div>
                                @if (!$corregida)
                                    <div class="account-card__actions mt-2">
                                        <form method="POST" action="{{ route('app.transferencias.corregir', $t) }}"
                                            class="d-inline" data-swal-confirm data-swal-title="¿Corregir este traslado?"
                                            data-swal-text="Se registra un reverso en el libro. Los saldos vuelven al estado previo."
                                            data-swal-icon="warning" data-swal-confirm-text="Corregir">
                                            @csrf
                                            <input type="hidden" name="motivo"
                                                value="Corrección de transferencia #{{ $t->id }}">
                                            <button class="card-btn card-btn--warn" type="submit">
                                                @include('layouts.partials.icon', [
                                                    'name' => 'ban',
                                                    'class' => 'ui-icon ui-icon--xs',
                                                ])
                                                Corregir
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="alert alert-light empty-state">Aún no hay traslados entre tus cuentas.</div>
                @endforelse
            </div>
            @if ($transferencias->hasPages())
                <div class="mt-3">{{ $transferencias->withQueryString()->links() }}</div>
            @endif
        </div>

        <div class="list-block">
            <div class="list-block__head">
                <h2>Pagos a terceros y deudas</h2>
                <span>{{ $pagos->total() }}</span>
            </div>
            <div class="card-stack">
                @forelse($pagos as $pago)
                    @php
                        $corregido = in_array((int) $pago->id, $idsPagosRevertidos, true);
                        $esPrestamoPago = $pago->tipo === 'prestamo';
                        $esTarjetaPago = $pago->tipo === 'tarjeta';
                        $esObligacionPago = $esPrestamoPago || $esTarjetaPago;
                        $puedeCorregir =
                            !$corregido &&
                            (
                                (!$esPrestamoPago && !$esTarjetaPago)
                                || ($esPrestamoPago && in_array((int) $pago->id, $ultimoPagoPorPrestamo, true))
                                || ($esTarjetaPago && in_array((int) $pago->id, $ultimoPagoPorTarjeta, true))
                            );
                        $titulo = $esPrestamoPago
                            ? ($pago->prestamo?->nombre ?: 'Préstamo')
                            : ($esTarjetaPago
                                ? ($pago->tarjetaCredito?->nombre ?: 'Tarjeta')
                                : ($pago->destino ?: 'Pago'));
                        $etiquetaTipo = match (true) {
                            $esPrestamoPago => 'Deuda rastreada',
                            $esTarjetaPago => 'Tarjeta de crédito',
                            default => ucfirst(str_replace('_', ' ', $pago->tipo)),
                        };
                    @endphp
                    <article class="account-card">
                        <div class="account-card__main">
                            <div class="account-card__icon">@include('layouts.partials.icon', [
                                'name' => 'arrow-down',
                                'class' => 'ui-icon ui-icon--sm',
                            ])</div>
                            <div class="account-card__body">
                                <div class="account-card__head">
                                    <div class="account-card__title">
                                        <h2>{{ $titulo }}</h2>
                                        <small>
                                            {{ $pago->fecha->format('d/m/Y') }}
                                            ·
                                            {{ $etiquetaTipo }}
                                            @if ($pago->cuentaLiquida)
                                                · {{ $pago->cuentaLiquida->nombre }}
                                            @endif
                                            @if ($pago->extraordinario)
                                                · Abono extra
                                            @endif
                                            @if ($corregido)
                                                · <span class="text-danger">Corregido</span>
                                            @endif
                                        </small>
                                    </div>
                                    <strong class="account-card__amount {{ $corregido ? 'text-secondary' : '' }}">
                                        @if ($corregido)
                                            <s>
                                                @endif @cop($pago->monto_centavos) @if ($corregido)
                                            </s>
                                        @endif
                                    </strong>
                                </div>
                                @unless ($esObligacionPago)
                                    <div class="small text-secondary mt-1">Ref. {{ $pago->referencia }}@if ($pago->categoria)
                                            · {{ $pago->categoria->nombre }}
                                        @endif
                                    </div>
                                @endunless
                                @if ($puedeCorregir)
                                    <div class="account-card__actions mt-2">
                                        <form method="POST" action="{{ route('app.pagos.corregir', $pago) }}"
                                            class="d-inline" data-swal-confirm data-swal-title="¿Corregir este pago?"
                                            data-swal-text="{{ $esObligacionPago ? 'Reverso contable y reapertura de cuota (solo el último pago de esa obligación).' : 'Se registra un reverso en el libro.' }}"
                                            data-swal-icon="warning" data-swal-confirm-text="Corregir">
                                            @csrf
                                            <input type="hidden" name="motivo"
                                                value="Corrección de pago #{{ $pago->id }}">
                                            <button class="card-btn card-btn--warn" type="submit">
                                                @include('layouts.partials.icon', [
                                                    'name' => 'ban',
                                                    'class' => 'ui-icon ui-icon--xs',
                                                ])
                                                Corregir
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="alert alert-light empty-state">Aún no hay pagos a terceros ni abonos a deudas desde aquí.
                    </div>
                @endforelse
            </div>
            @if ($pagos->hasPages())
                <div class="mt-3">{{ $pagos->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const dest = document.getElementById('cuenta_destino_id');
            const otraBox = document.getElementById('campos-destino-otra');
            const destExt = document.getElementById('destino_externo');
            const catXfer = document.getElementById('categoria_xfer');
            const btnXfer = document.getElementById('btn-transferencia');
            if (dest && otraBox) {
                function syncOtra() {
                    const esOtra = dest.value === 'otra';
                    otraBox.hidden = !esOtra;
                    if (destExt) {
                        destExt.required = esOtra;
                        if (!esOtra) destExt.value = '';
                    }
                    if (catXfer) {
                        catXfer.required = esOtra;
                        if (!esOtra) catXfer.value = '';
                    }
                    if (btnXfer) {
                        btnXfer.textContent = esOtra ? 'Registrar como gasto' : 'Contabilizar traslado';
                    }
                }
                dest.addEventListener('change', syncOtra);
                syncOtra();
            }

            const tipo = document.getElementById('tipo');
            const prestamoBox = document.getElementById('campo-prestamo');
            const tarjetaBox = document.getElementById('campo-tarjeta');
            const terceroBox = document.getElementById('campos-tercero');
            const prestamoSelect = document.getElementById('prestamo_id');
            const tarjetaSelect = document.getElementById('tarjeta_credito_id');
            const montoPago = document.getElementById('monto_pago');
            const cat = document.getElementById('categoria_id');
            const destino = document.getElementById('destino');
            const ref = document.getElementById('referencia');
            if (!tipo || !prestamoBox || !tarjetaBox || !terceroBox) return;

            function aplicarMinimo(select) {
                if (!montoPago || !select || !select.value) return;
                const opt = select.options[select.selectedIndex];
                const minimo = opt && opt.dataset ? opt.dataset.minimo : '';
                if (minimo !== undefined && minimo !== '') {
                    montoPago.value = minimo;
                    montoPago.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            function sync() {
                const esPrestamo = tipo.value === 'prestamo';
                const esTarjeta = tipo.value === 'tarjeta';
                const esObligacion = esPrestamo || esTarjeta;
                prestamoBox.hidden = !esPrestamo;
                tarjetaBox.hidden = !esTarjeta;
                terceroBox.hidden = esObligacion;
                if (prestamoSelect) {
                    prestamoSelect.required = esPrestamo;
                    if (!esPrestamo) prestamoSelect.value = '';
                }
                if (tarjetaSelect) {
                    tarjetaSelect.required = esTarjeta;
                    if (!esTarjeta) tarjetaSelect.value = '';
                }
                [cat, destino, ref].forEach(function(el) {
                    if (!el) return;
                    el.required = !esObligacion;
                    if (esObligacion) el.value = '';
                });
                if (esPrestamo) aplicarMinimo(prestamoSelect);
                if (esTarjeta) aplicarMinimo(tarjetaSelect);
            }

            if (prestamoSelect) prestamoSelect.addEventListener('change', function() { aplicarMinimo(prestamoSelect); });
            if (tarjetaSelect) tarjetaSelect.addEventListener('change', function() { aplicarMinimo(tarjetaSelect); });
            tipo.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
