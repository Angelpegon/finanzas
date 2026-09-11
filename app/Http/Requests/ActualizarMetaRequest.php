<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarMetaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'editar_meta';

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
            'nombre' => ['nullable', 'string', 'max:120'],
            'objetivo' => ['required', 'numeric', 'gt:0'],
            'fecha_objetivo' => ['nullable', 'date'],
            'aporte_mensual' => ['nullable', 'numeric', 'gte:0'],
            'prioridad' => ['nullable', Rule::in(['alta', 'media', 'baja'])],
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
