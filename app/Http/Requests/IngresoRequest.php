<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngresoRequest extends FormRequest
{
    use MensajesFormulario;

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
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'categoria_id' => [
                'required',
                'integer',
                Rule::exists('categorias', 'id')->where('usuario_id', $this->user()->id)->where('tipo', 'ingreso'),
            ],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                \App\Support\CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'periodicidad' => ['required', 'in:unico,diario,semanal,quincenal,mensual,anual'],
            'recurrente' => ['nullable', 'boolean'],
        ];
    }
}
