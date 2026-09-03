<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PagoPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
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
            'monto' => ['required', 'numeric', 'min:1'],
            'fecha' => ['required', 'date'],
        ];
    }
}
