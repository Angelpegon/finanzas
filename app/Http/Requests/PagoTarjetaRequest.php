<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagoTarjetaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tarjeta_credito_id' => ['required', 'integer', Rule::exists('tarjetas_credito', 'id')->where('usuario_id', $this->user()->id)],
            'cuota_tarjeta_id' => ['required', 'integer', Rule::exists('cuotas_tarjeta', 'id')->where('usuario_id', $this->user()->id)->where('pagada', false)],
            'cuenta_liquida_id' => ['required', 'integer', Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true)],
            'fecha' => ['required', 'date'],
        ];
    }
}
