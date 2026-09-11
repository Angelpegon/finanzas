<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelarCuentaRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disposicion' => ['nullable', Rule::in(['transferir', 'baja'])],
            'cuenta_destino_id' => [
                'nullable',
                'integer',
                'required_if:disposicion,transferir',
                CuentasOperativas::reglaExistsActiva((int) $this->user()->id),
            ],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'disposicion' => 'destino del saldo',
            'cuenta_destino_id' => 'cuenta destino',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'cuenta_destino_id.required_if' => 'Elige a qué cuenta transferir el saldo.',
            'disposicion.in' => 'Indica si transfieres el saldo o lo das de baja.',
        ];
    }
}
