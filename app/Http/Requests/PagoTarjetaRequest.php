<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
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

    public function rules(): array
    {
        return [
            'tarjeta_credito_id' => [
                'required',
                'integer',
                Rule::exists('tarjetas_credito', 'id')->where('usuario_id', $this->user()->id),
            ],
            'cuota_tarjeta_id' => [
                'required',
                'integer',
                Rule::exists('cuotas_tarjeta', 'id')->where('usuario_id', $this->user()->id)->where('pagada', false),
            ],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true),
            ],
            'fecha' => ['required', 'date'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'cuenta_liquida_id.required' => 'Elige la cuenta con la que pagas la cuota.',
            'cuota_tarjeta_id.exists' => 'Esa cuota no existe, ya fue pagada o no te pertenece.',
        ];
    }
}
