@extends('layouts.app', [
    'title' => 'Editar tarjeta',
    'heading' => 'Editar '.$tarjeta->nombre,
    'subtitle' => 'El cupo no puede bajar del saldo. Cambiar corte o pago mueve la fecha límite del extracto abierto y las cuotas aún no facturadas.',
    'backUrl' => route('app.tarjetas.index'),
    'backLabel' => 'Volver a tarjetas',
])
@section('content')
<div class="capture-flow">
    <p class="capture-hint">
        Saldo actual <strong>@cop($tarjeta->saldo_actual_centavos)</strong>.
        Las tasas nuevas aplican a movimientos futuros. Cambiar el día de pago recalcula vencimientos no facturados y la fecha límite del extracto abierto; no altera extractos ya cerrados ni cuotas ya cargadas a un ciclo.
    </p>
    @include('layouts.partials.form-errors')
    <form method="POST" action="{{ route('app.tarjetas.update', $tarjeta) }}" class="dash-card form-panel" novalidate>
        @csrf
        @method('PUT')

        <div class="money-hero">
            <label class="form-label" for="cupo">Cupo total</label>
            <input id="cupo" name="cupo" value="{{ old('cupo', $tarjeta->cupo_centavos / 100) }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg @error('cupo') is-invalid @enderror" placeholder="0" required>
            @include('layouts.partials.field-error', ['name' => 'cupo'])
        </div>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Tarjeta</p>
                <h2 class="form-section__title">Banco y nombre</h2>
            </div>
            <div>
                <label class="form-label" for="entidad">Banco o entidad</label>
                <input id="entidad" name="entidad" value="{{ old('entidad', $tarjeta->entidad) }}" class="form-control form-control-lg @error('entidad') is-invalid @enderror" required>
                @include('layouts.partials.field-error', ['name' => 'entidad'])
            </div>
            <div>
                <label class="form-label" for="nombre">Nombre de la tarjeta</label>
                <input id="nombre" name="nombre" value="{{ old('nombre', $tarjeta->nombre) }}" class="form-control form-control-lg @error('nombre') is-invalid @enderror" required>
                @include('layouts.partials.field-error', ['name' => 'nombre'])
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Tasas</p>
                <h2 class="form-section__title">Mensuales efectivas</h2>
            </div>
            <p class="small text-secondary mb-2">Solo aplican a compras y avances nuevos, y al interés/mora que cause el próximo corte.</p>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="tasa_compras_mensual">Compras (%)</label>
                    <input id="tasa_compras_mensual" name="tasa_compras_mensual" value="{{ old('tasa_compras_mensual', $tarjeta->tasa_compras_mensual) }}" type="number" min="0" step="0.01" class="form-control form-control-lg @error('tasa_compras_mensual') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'tasa_compras_mensual'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="tasa_avances_mensual">Avances (%)</label>
                    <input id="tasa_avances_mensual" name="tasa_avances_mensual" value="{{ old('tasa_avances_mensual', $tarjeta->tasa_avances_mensual) }}" type="number" min="0" step="0.01" class="form-control form-control-lg @error('tasa_avances_mensual') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'tasa_avances_mensual'])
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-6">
                    <label class="form-label" for="tasa_mora_mensual">Mora (%)</label>
                    <input id="tasa_mora_mensual" name="tasa_mora_mensual" value="{{ old('tasa_mora_mensual', $tarjeta->tasa_mora_mensual) }}" type="number" min="0" step="0.01" class="form-control form-control-lg @error('tasa_mora_mensual') is-invalid @enderror">
                    @include('layouts.partials.field-error', ['name' => 'tasa_mora_mensual'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="porcentaje_abono_capital_minimo">Abono a capital en el mínimo (%)</label>
                    <input id="porcentaje_abono_capital_minimo" name="porcentaje_abono_capital_minimo" value="{{ old('porcentaje_abono_capital_minimo', $tarjeta->porcentaje_abono_capital_minimo) }}" type="number" min="0" max="100" step="0.01" class="form-control form-control-lg @error('porcentaje_abono_capital_minimo') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'porcentaje_abono_capital_minimo'])
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label" for="cuota_manejo">Cuota de manejo</label>
                <input id="cuota_manejo" name="cuota_manejo" value="{{ old('cuota_manejo', $tarjeta->cuota_manejo_centavos / 100) }}" type="text" data-miles inputmode="decimal" class="form-control form-control-lg @error('cuota_manejo') is-invalid @enderror">
                @include('layouts.partials.field-error', ['name' => 'cuota_manejo'])
            </div>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <p class="form-section__eyebrow">Ciclo</p>
                <h2 class="form-section__title">Fechas clave</h2>
            </div>
            <p class="small text-secondary mb-2">El extracto abierto conserva su fecha de corte y montos; solo se actualiza la fecha límite de pago. Las cuotas aún no facturadas se recalculan.</p>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" for="dia_corte">Día de corte</label>
                    <input id="dia_corte" name="dia_corte" value="{{ old('dia_corte', $tarjeta->dia_corte) }}" type="number" min="1" max="31" class="form-control form-control-lg @error('dia_corte') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'dia_corte'])
                </div>
                <div class="col-6">
                    <label class="form-label" for="dia_pago">Día límite de pago</label>
                    <input id="dia_pago" name="dia_pago" value="{{ old('dia_pago', $tarjeta->dia_pago) }}" type="number" min="1" max="31" class="form-control form-control-lg @error('dia_pago') is-invalid @enderror" required>
                    @include('layouts.partials.field-error', ['name' => 'dia_pago'])
                </div>
            </div>
        </section>

        <div class="form-actions">
            <button class="btn btn-primary btn-lg w-100" type="submit" data-loading-label="Guardando…">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
