<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
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
                Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true),
            ],
            'prioridad' => ['required', Rule::in(['alta', 'media', 'baja'])],
            'estado' => ['required', Rule::in(['activa', 'cumplida', 'pausada', 'cancelada'])],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta de referencia',
        ];
    }
}
