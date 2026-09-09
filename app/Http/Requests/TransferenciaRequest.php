<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;

class TransferenciaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'transferencia';

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
        $operativa = CuentasOperativas::reglaExistsActiva($this->user()->id);

        return [
            'cuenta_liquida_id' => ['required', 'integer', $operativa],
            'cuenta_destino_id' => [
                'required',
                'integer',
                'different:cuenta_liquida_id',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
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

    protected function mensajesExtra(): array
    {
        return [
            'cuenta_liquida_id.exists' => 'La cuenta de origen no es válida o es un bolsillo de meta.',
            'cuenta_destino_id.exists' => 'La cuenta destino no es válida o es un bolsillo de meta.',
        ];
    }
}
