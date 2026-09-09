@extends('layouts.app', [
    'title' => 'Transferencias y pagos',
    'heading' => 'Movimientos',
    'subtitle' => 'Cada operación postea el libro. Las deudas se pagan en sus módulos.',
])
@section('content')
@php
    $errXfer = $errors->transferencia;
    $errPago = $errors->pago;
    $oldXfer = $errXfer->any();
    $oldPago = $errPago->any();
    $modoPago = $oldPago || request()->boolean('pago');
@endphp

<div class="capture-flow">
    <nav class="flow-tabs" aria-label="Tipo de movimiento">
        <a href="{{ route('app.pagos.index') }}" class="{{ ! $modoPago ? 'is-active' : '' }}">Transferir</a>
        <a href="{{ route('app.pagos.index', ['pago' => 1]) }}" class="{{ $modoPago ? 'is-active' : '' }}">Registrar pago</a>
    </nav>

    @if(! $modoPago)
        @if($cuentas->count() >= 2)
        <form method="POST" action="{{ route('app.transferencias.store') }}" class="dash-card form-panel" novalidate>
            @csrf
            <div class="form-section__head">
                <p class="form-section__eyebrow">Entre cuentas</p>
                <h2 class="form-section__title">Transferir liquidez</h2>
            </div>
            @include('layouts.partials.form-errors', ['bag' => 'transferencia'])

            <div class="money-hero">
                <label class="form-label" for="monto_transferencia">Monto</label>
                <input id="monto_transferencia" name="monto" data-miles inputmode="decimal" value="{{ $oldXfer ? old('monto') : '' }}" class="form-control form-control-lg @error('monto', 'transferencia') is-invalid @enderror" placeholder="0" required>
                @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'transferencia'])
            </div>

            <section class="form-section">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="cuenta_liquida_id">Origen</label>
                        <select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'transferencia') is-invalid @enderror" required>
                            <option value="">Cuenta</option>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}" @selected($oldXfer && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                        @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'transferencia'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cuenta_destino_id">Destino</label>
                        <select id="cuenta_destino_id" name="cuenta_destino_id" class="form-select form-select-lg @error('cuenta_destino_id', 'transferencia') is-invalid @enderror" required>
                            <option value="">Cuenta</option>
                            @foreach($cuentas as $cuenta)
                                <option value="{{ $cuenta->id }}" @selected($oldXfer && old('cuenta_destino_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                            @endforeach
                        </select>
                        @include('layouts.partials.field-error', ['name' => 'cuenta_destino_id', 'bag' => 'transferencia'])
                    </div>
                </div>
                <div>
                    <label class="form-label" for="fecha_transferencia">Fecha</label>
                    <input id="fecha_transferencia" name="fecha" type="date" value="{{ $oldXfer ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control form-control-lg @error('fecha', 'transferencia') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'transferencia'])
                </div>
                <div>
                    <label class="form-label" for="descripcion_transferencia">Descripción <span class="text-secondary">(opcional)</span></label>
                    <input id="descripcion_transferencia" name="descripcion" value="{{ $oldXfer ? old('descripcion') : '' }}" class="form-control form-control-lg @error('descripcion', 'transferencia') is-invalid @enderror" placeholder="Motivo del traslado">
                    @include('layouts.partials.field-error', ['name' => 'descripcion', 'bag' => 'transferencia'])
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit">Contabilizar transferencia</button>
            </div>
        </form>
        @else
            <div class="alert alert-light empty-state">Crea al menos dos cuentas líquidas para transferir entre ellas. <a href="{{ route('app.cuentas.create') }}">Nueva cuenta</a></div>
        @endif
    @else
        <form method="POST" action="{{ route('app.pagos.store') }}" class="dash-card form-panel" novalidate>
            @csrf
            <div class="form-section__head">
                <p class="form-section__eyebrow">Salida</p>
                <h2 class="form-section__title">Registrar un pago</h2>
            </div>
            @include('layouts.partials.form-errors', ['bag' => 'pago'])

            <div class="money-hero">
                <label class="form-label" for="monto_pago">Monto</label>
                <input id="monto_pago" name="monto" data-miles inputmode="decimal" value="{{ $oldPago ? old('monto') : '' }}" class="form-control form-control-lg @error('monto', 'pago') is-invalid @enderror" placeholder="0" required>
                @include('layouts.partials.field-error', ['name' => 'monto', 'bag' => 'pago'])
            </div>

            <section class="form-section">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select form-select-lg @error('tipo', 'pago') is-invalid @enderror" required>
                            @foreach(['gasto'=>'Gasto','servicio'=>'Servicio','deuda_personal'=>'Deuda personal','otra_obligacion'=>'Otra obligación'] as $v=>$t)
                                <option value="{{ $v }}" @selected(($oldPago ? old('tipo') : 'gasto') === $v)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @include('layouts.partials.field-error', ['name' => 'tipo', 'bag' => 'pago'])
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="fecha_pago">Fecha</label>
                        <input id="fecha_pago" name="fecha" type="date" value="{{ $oldPago ? old('fecha', now()->toDateString()) : now()->toDateString() }}" class="form-control form-control-lg @error('fecha', 'pago') is-invalid @enderror" required>
                        @include('layouts.partials.field-error', ['name' => 'fecha', 'bag' => 'pago'])
                    </div>
                </div>
                <div>
                    <label class="form-label" for="cuenta_pago">Cuenta utilizada</label>
                    <select id="cuenta_pago" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id', 'pago') is-invalid @enderror" required>
                        <option value="">Selecciona una cuenta</option>
                        @foreach($cuentas as $cuenta)
                            <option value="{{ $cuenta->id }}" @selected($oldPago && old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id', 'bag' => 'pago'])
                </div>
                <div>
                    <label class="form-label" for="categoria_id">Categoría de gasto</label>
                    <select id="categoria_id" name="categoria_id" class="form-select form-select-lg @error('categoria_id', 'pago') is-invalid @enderror">
                        <option value="">Obligatoria para gasto o servicio</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria->id }}" @selected($oldPago && old('categoria_id') == $categoria->id)>{{ $categoria->nombre }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'categoria_id', 'bag' => 'pago'])
                </div>
                <div>
                    <label class="form-label" for="destino">Destino</label>
                    <input id="destino" name="destino" value="{{ $oldPago ? old('destino') : '' }}" class="form-control form-control-lg @error('destino', 'pago') is-invalid @enderror" placeholder="Proveedor, banco o persona" required>
                    @include('layouts.partials.field-error', ['name' => 'destino', 'bag' => 'pago'])
                </div>
                <div>
                    <label class="form-label" for="referencia">Referencia única</label>
                    <input id="referencia" name="referencia" value="{{ $oldPago ? old('referencia') : '' }}" class="form-control form-control-lg @error('referencia', 'pago') is-invalid @enderror" placeholder="Factura o recibo" required>
                    @include('layouts.partials.field-error', ['name' => 'referencia', 'bag' => 'pago'])
                </div>
                <div>
                    <label class="form-label" for="observaciones">Observaciones <span class="text-secondary">(opcional)</span></label>
                    <textarea id="observaciones" name="observaciones" class="form-control @error('observaciones', 'pago') is-invalid @enderror" rows="2" placeholder="Detalle adicional">{{ $oldPago ? old('observaciones') : '' }}</textarea>
                    @include('layouts.partials.field-error', ['name' => 'observaciones', 'bag' => 'pago'])
                </div>
            </section>

            <div class="form-actions">
                <button class="btn btn-primary btn-lg w-100" type="submit">Guardar pago</button>
            </div>
        </form>
    @endif

    <div class="list-block">
        <div class="list-block__head">
            <h2>Transferencias recientes</h2>
            <span>{{ $transferencias->count() }}</span>
        </div>
        <div class="card-stack">
        @forelse($transferencias as $t)
        <article class="account-card">
            <div class="account-card__main">
                <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'exchange', 'class' => 'ui-icon ui-icon--sm'])</div>
                <div class="account-card__body">
                    <div class="account-card__head">
                        <div class="account-card__title">
                            <h2>{{ $t->descripcion ?: ucfirst(str_replace('_',' ', $t->tipo->value)) }}</h2>
                            <small>{{ $t->fecha->format('d/m/Y') }} · {{ $t->cuentaLiquida?->nombre }}</small>
                        </div>
                        <strong class="account-card__amount">@cop($t->monto_centavos)</strong>
                    </div>
                </div>
            </div>
        </article>
        @empty
            <div class="alert alert-light empty-state">Aún no hay transferencias.</div>
        @endforelse
        </div>
    </div>

    <div class="list-block">
        <div class="list-block__head">
            <h2>Historial de pagos</h2>
            <span>{{ $pagos->count() }}</span>
        </div>
        <div class="card-stack">
        @forelse($pagos as $pago)
        <article class="account-card">
            <div class="account-card__main">
                <div class="account-card__icon">@include('layouts.partials.icon', ['name' => 'arrow-down', 'class' => 'ui-icon ui-icon--sm'])</div>
                <div class="account-card__body">
                    <div class="account-card__head">
                        <div class="account-card__title">
                            <h2>{{ $pago->destino }}</h2>
                            <small>{{ ucfirst($pago->tipo) }} · {{ $pago->fecha->format('d/m/Y') }}</small>
                        </div>
                        <strong class="account-card__amount">@cop($pago->monto_centavos)</strong>
                    </div>
                    <div class="small text-secondary mt-1">Ref. {{ $pago->referencia }} · {{ $pago->cuentaLiquida?->nombre }}</div>
                </div>
            </div>
        </article>
        @empty
            <div class="alert alert-light empty-state">Aún no hay pagos registrados.</div>
        @endforelse
        </div>
    </div>
</div>
@endsection
