<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchivarCuentaRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuenta_destino_id' => [
                'nullable',
                'integer',
                CuentasOperativas::reglaExistsActiva((int) $this->user()->id),
            ],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_destino_id' => 'cuenta destino',
        ];
    }
}
