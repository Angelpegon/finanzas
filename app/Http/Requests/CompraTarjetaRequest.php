<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraTarjetaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'compra';

    public function authorize(): bool
    {
        return true;
    }

    protected function camposMoneda(): array
    {
        return ['monto'];
    }

    public function rules(): array
    {
        return [
            'tarjeta_credito_id' => [
                'required',
                'integer',
                Rule::exists('tarjetas_credito', 'id')->where('usuario_id', $this->user()->id)->where('activa', true),
            ],
            'tipo' => ['required', Rule::in(['compra', 'avance'])],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'categoria_id' => [
                'required_if:tipo,compra',
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where('usuario_id', $this->user()->id)->where('tipo', 'gasto'),
            ],
            'cuenta_liquida_id' => [
                'required_if:tipo,avance',
                'nullable',
                'integer',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'cuotas' => ['required', 'integer', 'min:1', 'max:60'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'categoria_id.required_if' => 'Elige la categoría de gasto de la compra.',
            'cuenta_liquida_id.required_if' => 'Elige la cuenta donde entra el avance.',
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
