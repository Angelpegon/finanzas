<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObligacionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'entidad' => ['nullable', 'string', 'max:120'],
            'tipo_obligacion' => ['required', Rule::in(['prestamo_bancario', 'credito', 'tarjeta_credito', 'prestamo_personal', 'compra_financiada', 'deuda_informal', 'otra_obligacion'])],
            'monto_inicial' => ['required', 'numeric', 'gt:0'],
            'tasa_interes' => ['required', 'numeric', 'min:0'],
            'tipo_tasa' => ['required', Rule::in(['ea', 'mensual', 'nominal'])],
            'metodo_amortizacion' => ['required', Rule::in(['frances', 'lineal', 'solo_interes'])],
            'seguro' => ['nullable', 'numeric', 'min:0'],
            'otros_cargos' => ['nullable', 'numeric', 'min:0'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'cuota' => ['required', 'numeric', 'gt:0'],
            'periodicidad' => ['required', Rule::in(['semanal', 'quincenal', 'mensual', 'anual'])],
            'numero_cuotas' => ['required', 'integer', 'min:1', 'max:600'],
            'cuenta_liquida_id' => ['required', 'integer', Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true)],
        ];
    }
}
