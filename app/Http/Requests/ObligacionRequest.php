<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObligacionRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    protected function camposMoneda(): array
    {
        return ['monto_inicial', 'seguro', 'otros_cargos'];
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'entidad' => ['nullable', 'string', 'max:120'],
            'tipo_obligacion' => [
                'required',
                Rule::in([
                    'prestamo_bancario',
                    'credito',
                    'prestamo_personal',
                    'compra_financiada',
                    'deuda_informal',
                    'otra_obligacion',
                ]),
            ],
            'monto_inicial' => ['required', 'numeric', 'gt:0'],
            'tasa_interes' => ['required', 'numeric', 'min:0'],
            'tipo_tasa' => ['required', Rule::in(['ea', 'mensual', 'nominal'])],
            'metodo_amortizacion' => ['required', Rule::in(['frances', 'lineal', 'solo_interes'])],
            'seguro' => ['nullable', 'numeric', 'min:0'],
            'otros_cargos' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'periodicidad' => ['required', Rule::in(['semanal', 'quincenal', 'mensual', 'anual'])],
            'numero_cuotas' => ['required', 'integer', 'min:1', 'max:600'],
            'dia_pago' => ['required', 'integer', 'min:1', 'max:31'],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                CuentasOperativas::reglaExistsActiva($this->user()->id),
            ],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta para el desembolso',
            'dia_pago' => 'día de pago',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
