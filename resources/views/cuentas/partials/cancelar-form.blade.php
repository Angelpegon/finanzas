@php
    $saldo = (int) ($saldo ?? $cuenta->saldo_actual_centavos);
@endphp
<form method="POST" action="{{ route('app.cuentas.cancelar', $cuenta) }}" class="cancel-account" novalidate
    data-swal-confirm
    data-swal-title="¿Cancelar esta cuenta?"
    data-swal-text="{{ $saldo > 0 ? 'Se aplicará la opción elegida sobre el saldo y la cuenta desaparecerá de la app.' : 'La cuenta desaparecerá de la app. El historial contable no se borra.' }}"
    data-swal-icon="warning"
    data-swal-confirm-text="Cancelar cuenta">
    @csrf
    <p class="small text-secondary mb-2">
        Cancelar es permanente en la UI: la cuenta deja de aparecer. El libro append-only se conserva.
    </p>
    @if ($saldo > 0)
        <p class="small mb-2">Saldo actual: <strong>@cop($saldo)</strong>. Elige qué hacer con ese dinero:</p>
        <div class="vstack gap-2 mb-2">
            <label class="form-check">
                <input class="form-check-input" type="radio" name="disposicion" value="transferir" @checked(old('disposicion', 'transferir') === 'transferir') required>
                <span class="form-check-label">Transferir a otra cuenta</span>
            </label>
            @if ($destinos->isNotEmpty())
                <select name="cuenta_destino_id" class="form-select form-select-sm">
                    <option value="">Cuenta destino</option>
                    @foreach ($destinos as $destino)
                        <option value="{{ $destino->id }}" @selected(old('cuenta_destino_id') == $destino->id)>{{ $destino->nombre }}</option>
                    @endforeach
                </select>
            @else
                <p class="small text-danger mb-0">Necesitas otra cuenta activa para transferir. Crea una o elige baja.</p>
            @endif
            <label class="form-check">
                <input class="form-check-input" type="radio" name="disposicion" value="baja" @checked(old('disposicion') === 'baja')>
                <span class="form-check-label">Dar de baja el saldo (sale del disponible)</span>
            </label>
        </div>
    @endif
    <button class="card-btn card-btn--danger" type="submit">
        @include('layouts.partials.icon', ['name' => 'trash', 'class' => 'ui-icon ui-icon--xs'])
        Confirmar cancelación
    </button>
</form>
