@extends('layouts.app', [
    'title' => 'Nueva obligación',
    'heading' => 'Registra lo que debes',
    'subtitle' => 'El saldo y las cuotas se calcularán desde el calendario.',
    'backUrl' => route('app.deudas.index'),
    'backLabel' => 'Volver a deudas',
])
@section('content')
<div class="capture-flow">
    @if($cuentas->isEmpty())
        <div class="alert alert-light empty-state">
            Necesitas una cuenta operativa antes de registrar un desembolso.
            <a href="{{ route('app.cuentas.create') }}">Crear cuenta</a>.
        </div>
    @else
    <p class="capture-hint"><strong>Desembolso real:</strong> al guardar, el monto entra a tu cuenta y nace el cronograma de cuotas. Las tarjetas se gestionan en <a href="{{ route('app.tarjetas.create') }}">Tarjetas</a>.</p>
    @include('layouts.partials.form-errors')
    <form method="POST" action="{{ route('app.deudas.store') }}" class="dash-card form-panel" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

        <div class="money-hero">
            <label class="form-label" for="monto_inicial">Monto inicial</label>
            <input id="monto_inicial" name="monto_inicial" value="{{ old('monto_inicial') }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg @error('monto_inicial') is-invalid @enderror" placeholder="0" required>
            @include('layouts.partials.field-error', ['name' => 'monto_inicial'])
        </div>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Identidad</p>
                <h2 class="form-section__title">Qué es y con quién</h2>
            </div>
            <div>
                <label class="form-label" for="nombre">Nombre</label>
                <input id="nombre" name="nombre" value="{{ old('nombre') }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" placeholder="Ej. Crédito de vehículo" required>
                @include('layouts.partials.field-error', ['name' => 'nombre'])
            </div>
            <div>
                <label class="form-label" for="entidad">Entidad</label>
                <input id="entidad" name="entidad" value="{{ old('entidad') }}" class="form-control form-control-lg @error('entidad') is-invalid @enderror" placeholder="Banco, persona o comercio">
                @include('layouts.partials.field-error', ['name' => 'entidad'])
            </div>
            <div>
                <label class="form-label" for="tipo_obligacion">Tipo</label>
                <select id="tipo_obligacion" name="tipo_obligacion" class="form-select form-select-lg @error('tipo_obligacion') is-invalid @enderror" required>
                    @foreach(['prestamo_bancario'=>'Préstamo bancario','credito'=>'Crédito','prestamo_personal'=>'Préstamo personal','compra_financiada'=>'Compra financiada','deuda_informal'=>'Deuda informal','otra_obligacion'=>'Otra obligación'] as $v=>$t)
                        <option value="{{ $v }}" @selected(old('tipo_obligacion', 'prestamo_bancario') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'tipo_obligacion'])
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Condiciones</p>
                <h2 class="form-section__title">Tasa y amortización</h2>
            </div>
            <div class="row g-3">
                <div class="col-7">
                    <label class="form-label" for="tasa_interes">Tasa de interés (%)</label>
                    <input id="tasa_interes" name="tasa_interes" value="{{ old('tasa_interes', 0) }}" type="number" min="0" step="0.01" class="form-control form-control-lg @error('tasa_interes') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'tasa_interes'])
                </div>
                <div class="col-5">
                    <label class="form-label" for="tipo_tasa">Tipo</label>
                    <select id="tipo_tasa" name="tipo_tasa" class="form-select form-select-lg @error('tipo_tasa') is-invalid @enderror" required>
                        @foreach(['ea'=>'EA','mensual'=>'Mensual','nominal'=>'Nominal'] as $v=>$t)
                            <option value="{{ $v }}" @selected(old('tipo_tasa', 'ea') === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'tipo_tasa'])
                </div>
            </div>
            <div>
                <label class="form-label" for="metodo_amortizacion">Método de amortización</label>
                <select id="metodo_amortizacion" name="metodo_amortizacion" class="form-select form-select-lg @error('metodo_amortizacion') is-invalid @enderror" required>
                    @foreach(['frances'=>'Francés · cuota estable','lineal'=>'Lineal · capital estable','solo_interes'=>'Solo interés · capital al final'] as $v=>$t)
                        <option value="{{ $v }}" @selected(old('metodo_amortizacion', 'frances') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'metodo_amortizacion'])
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="seguro">Seguro por cuota</label>
                    <input id="seguro" name="seguro" value="{{ old('seguro', 0) }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg @error('seguro') is-invalid @enderror">
                    @include('layouts.partials.field-error', ['name' => 'seguro'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="otros_cargos">Otros cargos</label>
                    <input id="otros_cargos" name="otros_cargos" value="{{ old('otros_cargos', 0) }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg @error('otros_cargos') is-invalid @enderror">
                    @include('layouts.partials.field-error', ['name' => 'otros_cargos'])
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Calendario</p>
                <h2 class="form-section__title">Plazo y desembolso</h2>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="fecha_inicio">Fecha inicio</label>
                    <input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ old('fecha_inicio', now()->toDateString()) }}" class="form-control form-control-lg @error('fecha_inicio') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'fecha_inicio'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="fecha_vencimiento">Vencimiento <span class="text-secondary">(opcional)</span></label>
                    <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" value="{{ old('fecha_vencimiento') }}" class="form-control form-control-lg @error('fecha_vencimiento') is-invalid @enderror">
                    @include('layouts.partials.field-error', ['name' => 'fecha_vencimiento'])
                </div>
            </div>
            <div class="row g-3">
                <div class="col-4">
                    <label class="form-label" for="dia_pago">Día de pago</label>
                    <input id="dia_pago" name="dia_pago" value="{{ old('dia_pago', now()->day) }}" type="number" min="1" max="31" class="form-control form-control-lg @error('dia_pago') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'dia_pago'])
                </div>
                <div class="col-4">
                    <label class="form-label" for="periodicidad">Periodicidad</label>
                    <select id="periodicidad" name="periodicidad" class="form-select form-select-lg @error('periodicidad') is-invalid @enderror" required>
                        @foreach(['mensual'=>'Mensual','quincenal'=>'Quincenal','semanal'=>'Semanal','anual'=>'Anual'] as $v=>$t)
                            <option value="{{ $v }}" @selected(old('periodicidad', 'mensual') === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                    @include('layouts.partials.field-error', ['name' => 'periodicidad'])
                </div>
                <div class="col-4">
                    <label class="form-label" for="numero_cuotas">Cuotas</label>
                    <input id="numero_cuotas" name="numero_cuotas" value="{{ old('numero_cuotas', 12) }}" type="number" min="1" class="form-control form-control-lg @error('numero_cuotas') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'numero_cuotas'])
                </div>
            </div>
            <div>
                <label class="form-label" for="cuenta_liquida_id">Cuenta para recibir el desembolso</label>
                <select id="cuenta_liquida_id" name="cuenta_liquida_id" class="form-select form-select-lg @error('cuenta_liquida_id') is-invalid @enderror" required>
                    <option value="">Selecciona una cuenta</option>
                    @foreach($cuentas as $cuenta)
                        <option value="{{ $cuenta->id }}" @selected(old('cuenta_liquida_id') == $cuenta->id)>{{ $cuenta->nombre }}</option>
                    @endforeach
                </select>
                @include('layouts.partials.field-error', ['name' => 'cuenta_liquida_id'])
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit">Guardar obligación</button>
        </div>
    </form>
    @endif
</div>
@endsection
