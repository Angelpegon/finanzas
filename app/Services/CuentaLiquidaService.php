<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Support\Dinero;
use Illuminate\Support\Facades\DB;

class CuentaLiquidaService
{
    public function __construct(private readonly TesoreriaService $tesoreria) {}

    public function crear(int $usuarioId, array $datos): CuentaLiquida
    {
        $saldoInicial = Dinero::pesosACentavos($datos['saldo_inicial'] ?? 0);
        return DB::transaction(function () use ($usuarioId, $datos, $saldoInicial): CuentaLiquida {
            $codigo = '11'.str_pad((string) (CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->count() + 1), 6, '0', STR_PAD_LEFT);
            $contable = CuentaContable::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'codigo' => $codigo,
                'nombre' => $datos['nombre'],
                'naturaleza' => 'activo',
            ]);
            $cuenta = CuentaLiquida::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'cuenta_contable_id' => $contable->id,
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'institucion' => $datos['institucion'] ?? null,
                'numero_cuenta_enmascarado' => $datos['numero_cuenta_enmascarado'] ?? null,
                'saldo_inicial_centavos' => $saldoInicial,
                'moneda' => 'COP',
                'estado' => 'activa',
                'activa' => true,
            ]);
            if ($saldoInicial > 0) {
                $this->tesoreria->registrar(
                    $usuarioId, TipoHechoTesoreria::Apertura, now()->toDateString(),
                    $saldoInicial, $cuenta->id, null, null, 'Saldo inicial de '.$cuenta->nombre
                );
            }
            return $cuenta->fresh('cuentaContable');
        });
    }

    public function archivar(int $usuarioId, CuentaLiquida $cuenta): void
    {
        if ($cuenta->usuario_id !== $usuarioId) {
            abort(404);
        }
        $cuenta->update(['activa' => false, 'estado' => 'inactiva']);
    }
}
