<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GastoRequest extends FormRequest
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
        $recurrente = $this->boolean('recurrente');

        return [
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'categoria_id' => [
                'required',
                'integer',
                Rule::exists('categorias', 'id')->where('usuario_id', $this->user()->id)->where('tipo', 'gasto'),
            ],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'tipo_gasto' => ['required', 'in:fijo,variable,extraordinario'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'recurrente' => ['nullable', 'boolean'],
            'ejecutado' => ['nullable', 'boolean'],
            'periodicidad' => [
                Rule::requiredIf($recurrente),
                'nullable',
                Rule::in(['diario', 'semanal', 'quincenal', 'mensual', 'anual']),
            ],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'periodicidad.required' => 'Elige cada cuánto se repite el gasto.',
            'periodicidad.in' => 'La periodicidad recurrente no puede ser única.',
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
