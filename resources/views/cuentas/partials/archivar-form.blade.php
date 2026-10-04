@php
    $saldo = (int) ($saldo ?? $cuenta->saldo_actual_centavos);
@endphp
@php
    $esteArchivar = old('_panel_cuenta') === 'archivar-'.$cuenta->id;
@endphp
<form method="POST" action="{{ route('app.cuentas.destroy', $cuenta) }}" class="cancel-account" novalidate
    data-swal-confirm
    data-swal-title="¿Archivar esta cuenta?"
    data-swal-text="{{ $saldo > 0 ? 'Se transferirá el saldo a la cuenta elegida y la cuenta quedará archivada.' : 'Deja de verse en tesorería activa. Puedes restaurarla después.' }}"
    data-swal-icon="warning"
    data-swal-confirm-text="Archivar">
    @csrf
    @method('DELETE')
    <input type="hidden" name="_panel_cuenta" value="archivar-{{ $cuenta->id }}">
    @if($esteArchivar)
        @include('layouts.partials.form-errors')
    @endif
    <p class="small text-secondary mb-2">
        Archivar oculta la cuenta de la tesorería. El historial del libro se conserva.
    </p>
    @if ($saldo > 0)
        <p class="small mb-2">Saldo actual: <strong>@cop($saldo)</strong>. Debes transferirlo antes de archivar:</p>
        @if ($destinos->isNotEmpty())
            <label class="form-label" for="cuenta_destino_archivar_{{ $cuenta->id }}">Cuenta destino</label>
            <select id="cuenta_destino_archivar_{{ $cuenta->id }}" name="cuenta_destino_id" class="form-select form-select-sm mb-2 {{ $esteArchivar && $errors->has('cuenta_destino_id') ? 'is-invalid' : '' }}" required>
                <option value="">Elige cuenta</option>
                @foreach ($destinos as $destino)
                    <option value="{{ $destino->id }}" @selected($esteArchivar && old('cuenta_destino_id') == $destino->id)>{{ $destino->nombre }}</option>
                @endforeach
            </select>
            @if($esteArchivar)
                @include('layouts.partials.field-error', ['name' => 'cuenta_destino_id'])
            @endif
        @else
            <p class="small text-danger mb-2">Necesitas otra cuenta activa para transferir el saldo.</p>
        @endif
    @endif
    <button class="card-btn card-btn--warn" type="submit" @disabled($saldo > 0 && $destinos->isEmpty())>
        @include('layouts.partials.icon', ['name' => 'archive', 'class' => 'ui-icon ui-icon--xs'])
        Confirmar archivo
    </button>
</form>
