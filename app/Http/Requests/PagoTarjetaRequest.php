<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagoTarjetaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'pago_tarjeta';

    public function authorize(): bool
    {
        return true;
    }

    protected function camposMoneda(): array
    {
        return ['monto'];
    }

    public function rules(): array
    {
        return [
            'tarjeta_credito_id' => [
                'required',
                'integer',
                Rule::exists('tarjetas_credito', 'id')->where('usuario_id', $this->user()->id)->where('activa', true),
            ],
            'cuota_tarjeta_id' => [
                'required',
                'integer',
                Rule::exists('cuotas_tarjeta', 'id')
                    ->where('usuario_id', $this->user()->id)
                    ->where('pagada', 0),
            ],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'monto' => ['nullable', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta utilizada',
            'monto' => 'monto a pagar',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'cuenta_liquida_id.required' => 'Elige la cuenta con la que pagas la cuota.',
            'cuota_tarjeta_id.exists' => 'Esa cuota no existe, ya fue pagada o no te pertenece.',
            'idempotency_key.required' => 'Recarga la página e intenta el pago de nuevo.',
            'monto.gt' => 'El monto debe ser mayor que cero. Déjalo en el mínimo de la cuota o súmalo como abono extra.',
        ];
    }
}
