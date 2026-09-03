<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TarjetaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entidad' => ['required', 'string', 'max:120'],
            'nombre' => ['required', 'string', 'max:120'],
            'cupo' => ['required', 'numeric', 'gt:0'],
            'tasa' => ['required', 'numeric', 'min:0'],
            'dia_corte' => ['required', 'integer', 'between:1,31'],
            'dia_pago' => ['required', 'integer', 'between:1,31'],
        ];
    }
}
