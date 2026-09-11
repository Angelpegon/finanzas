<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;

class CuentaLiquidaRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    protected function camposMoneda(): array
    {
        return $this->isMethod('post') ? ['saldo_inicial'] : [];
    }

    public function rules(): array
    {
        $tipos = array_keys(CuentasOperativas::etiquetasTipo());

        $rules = [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:'.implode(',', $tipos)],
            'institucion' => ['nullable', 'string', 'max:120'],
            'numero_cuenta_enmascarado' => ['nullable', 'string', 'max:32', 'regex:/^[*0-9 -]+$/'],
        ];

        if ($this->isMethod('post')) {
            $rules['saldo_inicial'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }
}
