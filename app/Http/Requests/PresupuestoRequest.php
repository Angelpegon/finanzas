<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresupuestoRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'anio' => ['required', 'integer', 'between:2020,2100'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'umbrales' => ['required', 'array', 'min:1'],
            'umbrales.*' => ['integer', 'between:1,100'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*' => ['nullable', 'numeric', 'min:0'],
            'categoria_ids' => ['required', 'array', 'min:1'],
            'categoria_ids.*' => [
                'integer',
                Rule::exists('categorias', 'id')->where('usuario_id', $this->user()->id)->where('tipo', 'gasto'),
            ],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
