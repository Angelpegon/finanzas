<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MensajesFormulario;
use App\Support\CuentasOperativas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferenciaRequest extends FormRequest
{
    use MensajesFormulario;

    protected $errorBag = 'transferencia';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function camposMoneda(): array
    {
        return ['monto'];
    }

    public function esDestinoExterno(): bool
    {
        return (string) $this->input('cuenta_destino_id') === 'otra';
    }

    public function rules(): array
    {
        $usuarioId = $this->user()->id;
        $operativa = CuentasOperativas::reglaExistsActiva($usuarioId);
        $base = [
            'cuenta_liquida_id' => ['required', 'integer', $operativa],
            'monto' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];

        if ($this->esDestinoExterno()) {
            return array_merge($base, [
                'cuenta_destino_id' => ['required', 'in:otra'],
                'categoria_id' => [
                    'required',
                    'integer',
                    Rule::exists('categorias', 'id')->where('usuario_id', $usuarioId)->where('tipo', 'gasto'),
                ],
                'destino' => ['required', 'string', 'max:160'],
            ]);
        }

        return array_merge($base, [
            'cuenta_destino_id' => [
                'required',
                'integer',
                'different:cuenta_liquida_id',
                CuentasOperativas::reglaExistsActiva($usuarioId),
            ],
        ]);
    }

    protected function atributosExtra(): array
    {
        return [
            'cuenta_liquida_id' => 'cuenta de origen',
            'cuenta_destino_id' => 'cuenta destino',
            'categoria_id' => 'categoría',
            'destino' => 'destinatario',
        ];
    }

    protected function mensajesExtra(): array
    {
        return [
            'cuenta_liquida_id.exists' => 'La cuenta de origen no es válida o es un bolsillo de meta.',
            'cuenta_destino_id.exists' => 'La cuenta destino no es válida o es un bolsillo de meta.',
            'cuenta_destino_id.in' => 'Elige una cuenta propia u «Otra».',
            'destino.required' => 'Indica a quién o a qué cuenta externa envías.',
            'categoria_id.required' => 'El envío a otra cuenta se registra como gasto: elige la categoría.',
            'idempotency_key.required' => 'Recarga el formulario e intenta de nuevo.',
        ];
    }
}
