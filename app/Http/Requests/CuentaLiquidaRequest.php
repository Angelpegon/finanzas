<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
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
        return ['saldo_inicial'];
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:bancaria,ahorros,corriente,efectivo,billetera,otra'],
            'institucion' => ['nullable', 'string', 'max:120'],
            'numero_cuenta_enmascarado' => ['nullable', 'string', 'max:32', 'regex:/^[*0-9 -]+$/'],
            'saldo_inicial' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
