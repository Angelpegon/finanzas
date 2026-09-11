<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PagoPrestamoRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'pago_prestamo';

    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function camposMoneda(): array
    {
        return ['monto'];
    }

    public function rules(): array
    {
        $usuarioId = Auth::id();

        return [
            'prestamo_id' => [
                'required',
                'integer',
                Rule::exists('prestamos', 'id')->where(fn ($q) => $q->where('usuario_id', $usuarioId)),
            ],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'cuenta_liquida_id' => [
                'nullable',
                'integer',
                CuentasOperativas::reglaExistsActiva((int) $usuarioId),
            ],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'idempotency_key.required' => 'Recarga la página e intenta el pago de nuevo.',
        ];
    }
}
