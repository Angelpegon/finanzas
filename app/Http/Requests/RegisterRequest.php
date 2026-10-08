<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    use MensajesFormulario;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            // not_regex: la regla `email` de Laravel 10 acepta CRLF (advisory sin parche en 10.x).
            'email' => ['required', 'string', 'email', 'not_regex:/[\r\n]/', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'nombre.required' => 'Indica tu nombre.',
            'email.required' => 'Indica tu correo electrónico.',
            'email.not_regex' => 'El correo no puede contener saltos de línea.',
            'password.required' => 'Elige una contraseña.',
        ];
    }
}
