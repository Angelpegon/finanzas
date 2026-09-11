<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoriaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'categoria';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $esUpdate = $this->route('categoria') !== null;

        return [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => [$esUpdate ? 'nullable' : 'required', Rule::in(['gasto', 'ingreso'])],
            'idempotency_key' => [$esUpdate ? 'nullable' : 'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
