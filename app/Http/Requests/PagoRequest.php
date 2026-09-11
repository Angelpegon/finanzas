<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagoRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'pago';

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
        $usuarioId = $this->user()->id;
        $tipo = $this->input('tipo');

        return [
            'tipo' => ['required', Rule::in(['deuda_personal', 'otra_obligacion', 'prestamo', 'tarjeta'])],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                CuentasOperativas::reglaExistsActiva($usuarioId),
            ],
            'prestamo_id' => [
                Rule::requiredIf(fn () => $tipo === 'prestamo'),
                'nullable',
                'integer',
                Rule::exists('prestamos', 'id')
                    ->where('usuario_id', $usuarioId)
                    ->where(function ($q): void {
                        $q->whereIn('estado', ['activa', 'vigente']);
                    }),
            ],
            'tarjeta_credito_id' => [
                Rule::requiredIf(fn () => $tipo === 'tarjeta'),
                'nullable',
                'integer',
                Rule::exists('tarjetas_credito', 'id')
                    ->where('usuario_id', $usuarioId)
                    ->where('activa', true),
            ],
            'categoria_id' => [
                Rule::requiredIf(fn () => in_array($tipo, ['deuda_personal', 'otra_obligacion'], true)),
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where('usuario_id', $usuarioId)->where('tipo', 'gasto'),
            ],
            'destino' => [
                Rule::requiredIf(fn () => in_array($tipo, ['deuda_personal', 'otra_obligacion'], true)),
                'nullable',
                'string',
                'max:160',
            ],
            'referencia' => [
                Rule::requiredIf(fn () => in_array($tipo, ['deuda_personal', 'otra_obligacion'], true)),
                'nullable',
                'string',
                'max:100',
                Rule::unique('pagos', 'referencia')->where('usuario_id', $usuarioId),
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta utilizada',
            'categoria_id' => 'categoría de gasto',
            'prestamo_id' => 'obligación',
            'tarjeta_credito_id' => 'tarjeta',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
            'prestamo_id.required' => 'Elige la deuda u obligación a pagar.',
            'prestamo_id.exists' => 'Esa obligación no existe, ya está cancelada o no te pertenece.',
            'tarjeta_credito_id.required' => 'Elige la tarjeta a pagar.',
            'tarjeta_credito_id.exists' => 'Esa tarjeta no existe, no está activa o no te pertenece.',
            'destino.required' => 'Indica a quién o a qué cuenta externa pagas.',
        ];
    }
}
