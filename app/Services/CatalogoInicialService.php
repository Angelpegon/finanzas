<?php

namespace App\Services;

use App\Enums\NaturalezaCuenta;
use App\Models\Categoria;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CatalogoInicialService
{
    public function sembrar(User $usuario): void
    {
        DB::transaction(function () use ($usuario): void {
            $cuentas = [
                '1100' => [NaturalezaCuenta::Activo, 'Bancos y efectivo'],
                '2100' => [NaturalezaCuenta::Pasivo, 'Deudas'],
                '3100' => [NaturalezaCuenta::Patrimonio, 'Patrimonio inicial'],
                '4100' => [NaturalezaCuenta::Ingreso, 'Ingresos'],
                '5100' => [NaturalezaCuenta::Gasto, 'Gastos'],
                '5200' => [NaturalezaCuenta::Gasto, 'Intereses'],
            ];

            $creadas = [];
            foreach ($cuentas as $codigo => [$naturaleza, $nombre]) {
                $creadas[$codigo] = CuentaContable::withoutGlobalScopes()->create([
                    'usuario_id' => $usuario->id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'naturaleza' => $naturaleza,
                ]);
            }

            CuentaLiquida::withoutGlobalScopes()->create([
                'usuario_id' => $usuario->id,
                'cuenta_contable_id' => $creadas['1100']->id,
                'nombre' => 'Efectivo',
                'tipo' => 'efectivo',
            ]);

            foreach ([
                ['Salario', 'ingreso', '4100'], ['Freelance', 'ingreso', '4100'],
                ['Negocio', 'ingreso', '4100'], ['Comisión', 'ingreso', '4100'],
                ['Bonificación', 'ingreso', '4100'], ['Inversión', 'ingreso', '4100'],
                ['Otros ingresos', 'ingreso', '4100'],
                ['Alimentación', 'gasto', '5100'], ['Transporte', 'gasto', '5100'],
                ['Vivienda', 'gasto', '5100'], ['Servicios', 'gasto', '5100'],
                ['Salud', 'gasto', '5100'], ['Educación', 'gasto', '5100'],
                ['Entretenimiento', 'gasto', '5100'], ['Suscripciones', 'gasto', '5100'],
                ['Compras', 'gasto', '5100'], ['Otros', 'gasto', '5100'],
            ] as [$nombre, $tipo, $codigo]) {
                Categoria::withoutGlobalScopes()->create([
                    'usuario_id' => $usuario->id,
                    'cuenta_contable_id' => $creadas[$codigo]->id,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                ]);
            }
        });
    }
}
