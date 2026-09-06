<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;

class TarjetaRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    protected function camposMoneda(): array
    {
        return ['cupo'];
    }

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
