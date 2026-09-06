<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
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
        return [
            'tipo' => ['required', Rule::in(['gasto', 'servicio', 'deuda_personal', 'otra_obligacion'])],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'cuenta_liquida_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_liquidas', 'id')->where('usuario_id', $this->user()->id)->where('activa', true),
            ],
            'categoria_id' => [
                'required_if:tipo,gasto,servicio,deuda_personal,otra_obligacion',
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where('usuario_id', $this->user()->id)->where('tipo', 'gasto'),
            ],
            'prestamo_id' => [
                'nullable',
                'integer',
                Rule::exists('prestamos', 'id')->where('usuario_id', $this->user()->id),
            ],
            'tarjeta_credito_id' => [
                'nullable',
                'integer',
                Rule::exists('tarjetas_credito', 'id')->where('usuario_id', $this->user()->id),
            ],
            'destino' => ['required', 'string', 'max:160'],
            'referencia' => [
                'required',
                'string',
                'max:100',
                Rule::unique('pagos', 'referencia')->where('usuario_id', $this->user()->id),
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta utilizada',
            'categoria_id' => 'categoría de gasto',
        ];
    }
}
