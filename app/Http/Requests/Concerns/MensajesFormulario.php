<?php

namespace App\Http\Requests\Concerns;

trait MensajesFormulario
{
    /** @return list<string> */
    protected function camposMoneda(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach ($this->camposMoneda() as $campo) {
            if (! $this->exists($campo)) {
                continue;
            }
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $merge[$campo] = $this->normalizarMonto($valor);
            }
        }

        if ($this->exists('lineas') && is_array($this->input('lineas'))) {
            $merge['lineas'] = array_map(
                fn ($valor) => is_string($valor) ? $this->normalizarMonto($valor) : $valor,
                $this->input('lineas')
            );
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function normalizarMonto(string $valor): string
    {
        $v = trim(str_replace(['$', 'COP', ' '], '', $valor));
        if ($v === '' || $v === '-') {
            return $v;
        }

        // Ya normalizado por JS (p. ej. 1500.50) o entero simple.
        if (preg_match('/^-?\d+(\.\d{1,2})?$/', $v) === 1) {
            return $v;
        }

        // Formato colombiano: 1.500,50 o 1500,50
        if (str_contains($v, ',')) {
            return str_replace(',', '.', str_replace('.', '', $v));
        }

        // Solo miles con punto: 1.500
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v) === 1) {
            return str_replace('.', '', $v);
        }

        return $v;
    }

    public function attributes(): array
    {
        return array_merge([
            'nombre' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
            'remember' => 'mantener sesión',
            'monto' => 'monto',
            'monto_inicial' => 'monto inicial',
            'fecha' => 'fecha',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'fecha_objetivo' => 'fecha objetivo',
            'categoria_id' => 'categoría',
            'cuenta_liquida_id' => 'cuenta',
            'cuenta_destino_id' => 'cuenta destino',
            'descripcion' => 'descripción',
            'periodicidad' => 'periodicidad',
            'recurrente' => 'ingreso recurrente',
            'proyectado' => 'gasto proyectado',
            'tipo_gasto' => 'tipo de gasto',
            'tipo' => 'tipo',
            'tipo_obligacion' => 'tipo de obligación',
            'tipo_tasa' => 'tipo de tasa',
            'tasa' => 'tasa',
            'tasa_interes' => 'tasa de interés',
            'metodo_amortizacion' => 'método de amortización',
            'seguro' => 'seguro',
            'otros_cargos' => 'otros cargos',
            'cuota' => 'cuota',
            'cuotas' => 'número de cuotas',
            'numero_cuotas' => 'número de cuotas',
            'interes' => 'interés',
            'entidad' => 'entidad',
            'institucion' => 'institución',
            'numero_cuenta_enmascarado' => 'número enmascarado',
            'saldo_inicial' => 'saldo inicial',
            'cupo' => 'cupo',
            'dia_corte' => 'día de corte',
            'dia_pago' => 'día de pago',
            'objetivo' => 'objetivo',
            'aporte_mensual' => 'aporte mensual',
            'prioridad' => 'prioridad',
            'estado' => 'estado',
            'meta_ahorro_id' => 'meta de ahorro',
            'prestamo_id' => 'préstamo',
            'tarjeta_credito_id' => 'tarjeta',
            'cuota_tarjeta_id' => 'cuota de tarjeta',
            'destino' => 'destino',
            'referencia' => 'referencia',
            'observaciones' => 'observaciones',
            'anio' => 'año',
            'mes' => 'mes',
            'umbrales' => 'umbrales de alerta',
            'umbrales.*' => 'umbral de alerta',
            'lineas' => 'líneas de presupuesto',
            'lineas.*' => 'monto presupuestado',
            'categoria_ids' => 'categorías',
            'categoria_ids.*' => 'categoría',
        ], $this->atributosExtra());
    }

    /** @return array<string, string> */
    protected function atributosExtra(): array
    {
        return [];
    }

    public function messages(): array
    {
        return array_merge([
            'required' => 'Indica :attribute.',
            'required_if' => 'Indica :attribute cuando el tipo lo requiere.',
            'numeric' => ':attribute debe ser un número válido (usa punto para centavos, p. ej. 1500.50).',
            'integer' => ':attribute debe ser un número entero.',
            'string' => ':attribute debe ser texto.',
            'email' => 'Ingresa un correo electrónico válido.',
            'date' => ':attribute no es una fecha válida.',
            'boolean' => ':attribute no es válido.',
            'array' => ':attribute debe ser una lista.',
            'in' => 'La opción elegida para :attribute no es válida.',
            'gt' => ':attribute debe ser mayor que :value.',
            'gte' => ':attribute debe ser mayor o igual que :value.',
            'min.numeric' => ':attribute no puede ser menor que :min.',
            'min.string' => ':attribute debe tener al menos :min caracteres.',
            'min.array' => 'Selecciona al menos :min valor(es) en :attribute.',
            'max.numeric' => ':attribute no puede ser mayor que :max.',
            'max.string' => ':attribute no puede superar :max caracteres.',
            'max.array' => ':attribute no puede tener más de :max elementos.',
            'between.numeric' => ':attribute debe estar entre :min y :max.',
            'between.integer' => ':attribute debe estar entre :min y :max.',
            'exists' => 'El valor de :attribute no existe o no te pertenece.',
            'unique' => 'Ese :attribute ya está en uso.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'different' => ':attribute debe ser distinta de :other.',
            'after_or_equal' => ':attribute debe ser igual o posterior a :date.',
            'regex' => 'El formato de :attribute no es válido.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'monto_inicial.gt' => 'El monto inicial debe ser mayor que cero.',
            'cupo.gt' => 'El cupo debe ser mayor que cero.',
            'objetivo.gt' => 'El objetivo debe ser mayor que cero.',
            'referencia.unique' => 'Ya registraste un pago con esa referencia. Usa otra (factura, recibo, etc.).',
            'cuenta_destino_id.different' => 'La cuenta destino debe ser distinta a la de origen.',
            'categoria_id.required_if' => 'Elige una categoría de gasto para este tipo de pago.',
            'umbrales.required' => 'Marca al menos un umbral de alerta (70 %, 80 %, 90 % o 100 %).',
            'umbrales.min' => 'Marca al menos un umbral de alerta.',
            'lineas.required' => 'Indica al menos un monto presupuestado por categoría.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'email.unique' => 'Ya existe una cuenta con ese correo electrónico.',
            'numero_cuenta_enmascarado.regex' => 'El número enmascarado solo puede incluir dígitos, espacios, guiones y asteriscos.',
        ], $this->mensajesExtra());
    }

    /** @return array<string, string> */
    protected function mensajesExtra(): array
    {
        return [];
    }
}
