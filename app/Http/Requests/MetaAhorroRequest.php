<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaAhorroRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'objetivo' => ['required', 'numeric', 'gt:0'],
            'monto_actual' => ['nullable', 'numeric', 'gte:0', 'lte:objetivo'],
            'fecha_objetivo' => ['nullable', 'date'],
            'aporte_mensual' => ['nullable', 'numeric', 'gte:0'],
            'cuenta_liquida_id' => ['nullable', 'integer', Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true)],
            'prioridad' => ['required', Rule::in(['alta', 'media', 'baja'])],
            'estado' => ['required', Rule::in(['activa', 'cumplida', 'pausada', 'cancelada'])],
        ];
    }
}
