<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaAhorroRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'meta';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function camposMoneda(): array
    {
        return ['objetivo', 'aporte_mensual'];
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'objetivo' => ['required', 'numeric', 'gt:0'],
            'fecha_objetivo' => ['nullable', 'date'],
            'aporte_mensual' => ['nullable', 'numeric', 'gte:0'],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'prioridad' => ['required', Rule::in(['alta', 'media', 'baja'])],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta de referencia',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
