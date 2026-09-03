<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use Illuminate\Support\Facades\DB;

class TesoreriaService
{
    public function __construct(private readonly ContabilizacionService $contabilizacion) {}

    public function registrar(
        int $usuarioId,
        TipoHechoTesoreria $tipo,
        string $fecha,
        int $montoCentavos,
        ?int $cuentaLiquidaId = null,
        ?int $categoriaId = null,
        ?int $cuentaDestinoId = null,
        ?string $descripcion = null,
        ?string $tipoGasto = null
    ): HechoTesoreria {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El monto debe ser positivo.');
        }
        if (in_array($tipo, [TipoHechoTesoreria::Ingreso, TipoHechoTesoreria::Gasto], true)
            && ($cuentaLiquidaId === null || $categoriaId === null)) {
            throw new \InvalidArgumentException('Ingresos y gastos requieren cuenta y categoría.');
        }
        if ($tipo === TipoHechoTesoreria::Transferencia && ($cuentaLiquidaId === null || $cuentaDestinoId === null)) {
            throw new \InvalidArgumentException('Una transferencia requiere cuenta de origen y destino.');
        }

        return DB::transaction(function () use (
            $usuarioId, $tipo, $fecha, $montoCentavos, $cuentaLiquidaId,
            $categoriaId, $cuentaDestinoId, $descripcion, $tipoGasto
        ): HechoTesoreria {
            $cuenta = $cuentaLiquidaId
                ? CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->findOrFail($cuentaLiquidaId)
                : null;
            $categoria = $categoriaId
                ? Categoria::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($categoriaId)
                : null;
            if ($categoria && $tipo === TipoHechoTesoreria::Ingreso && $categoria->tipo !== 'ingreso') {
                throw new \InvalidArgumentException('La categoría no corresponde a un ingreso.');
            }
            if ($categoria && $tipo === TipoHechoTesoreria::Gasto && $categoria->tipo !== 'gasto') {
                throw new \InvalidArgumentException('La categoría no corresponde a un gasto.');
            }

            $movimientos = match ($tipo) {
                TipoHechoTesoreria::Ingreso => [
                    ['cuenta_contable_id' => $cuenta?->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $categoria?->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ],
                TipoHechoTesoreria::Gasto => [
                    ['cuenta_contable_id' => $categoria?->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $cuenta?->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ],
                TipoHechoTesoreria::Transferencia => $this->movimientosTransferencia($usuarioId, $cuenta, $cuentaDestinoId, $montoCentavos),
                TipoHechoTesoreria::Apertura => [
                    ['cuenta_contable_id' => $cuenta?->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $this->cuentaPatrimonio($usuarioId), 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ],
                default => throw new \InvalidArgumentException('Tipo de tesorería no soportado.'),
            };

            $hecho = HechoTesoreria::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'tipo' => $tipo, 'tipo_gasto' => $tipoGasto, 'cuenta_liquida_id' => $cuentaLiquidaId,
                'cuenta_destino_id' => $cuentaDestinoId, 'categoria_id' => $categoriaId,
                'fecha' => $fecha, 'monto_centavos' => $montoCentavos, 'descripcion' => $descripcion,
            ]);
            $this->contabilizacion->postear($usuarioId, $fecha, $descripcion ?? $tipo->value, HechoTesoreria::class, $hecho->id, $movimientos);

            return $hecho;
        });
    }

    private function movimientosTransferencia(int $usuarioId, ?CuentaLiquida $origen, ?int $destinoId, int $monto): array
    {
        $destino = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('activa', true)->findOrFail($destinoId);
        if ($origen?->id === $destino->id) {
            throw new \InvalidArgumentException('El origen y destino deben ser diferentes.');
        }
        return [
            ['cuenta_contable_id' => $destino->cuenta_contable_id, 'debe_centavos' => $monto, 'haber_centavos' => 0],
            ['cuenta_contable_id' => $origen?->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $monto],
        ];
    }

    private function cuentaPatrimonio(int $usuarioId): int
    {
        return (int) \App\Models\CuentaContable::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('codigo', '3100')->value('id');
    }
}
