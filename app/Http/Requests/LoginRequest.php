<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'email.required' => 'Indica tu correo electrónico.',
            'password.required' => 'Indica tu contraseña.',
        ];
    }
}
