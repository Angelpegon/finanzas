<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AporteMetaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'aporte';

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
            'cuenta_liquida_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true),
            ],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta de origen',
        ];
    }
}
