<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraTarjetaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tarjeta_credito_id' => ['required', 'integer', Rule::exists('tarjetas_credito', 'id')->where('usuario_id', $this->user()->id)->where('activa', true)],
            'descripcion' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('usuario_id', $this->user()->id)->where('tipo', 'gasto')],
            'cuotas' => ['required', 'integer', 'min:1', 'max:60'],
            'interes' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
