<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetiroMetaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'retiro';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function camposMoneda(): array
    {
        return ['monto'];
    }

    public function rules(): array
    {
        return [
            'meta_ahorro_id' => [
                'required',
                'integer',
                Rule::exists('metas_ahorro', 'id')->where('usuario_id', $this->user()->id),
            ],
            'cuenta_destino_id' => [
                'required',
                'integer',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_destino_id' => 'cuenta destino',
            'meta_ahorro_id' => 'meta',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
            'cuenta_destino_id.required' => 'Elige la cuenta operativa donde vuelve el dinero.',
        ];
    }
}
