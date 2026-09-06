<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use Illuminate\Support\Facades\DB;

class PagoService
{
    public function __construct(private readonly ContabilizacionService $contabilizacion) {}

    public function registrar(
        int $usuarioId,
        string $tipo,
        int $montoCentavos,
        int $cuentaLiquidaId,
        string $fecha,
        string $destino,
        string $referencia,
        ?string $observaciones = null,
        ?int $categoriaId = null,
        ?int $prestamoId = null,
        ?int $tarjetaId = null
    ): Pago {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El monto del pago debe ser positivo.');
        }

        return DB::transaction(function () use ($usuarioId, $tipo, $montoCentavos, $cuentaLiquidaId, $fecha, $destino, $referencia, $observaciones, $categoriaId, $prestamoId, $tarjetaId): Pago {
            if (in_array($tipo, ['credito', 'prestamo', 'tarjeta'], true)) {
                throw new \InvalidArgumentException('Los pagos de créditos, préstamos y tarjetas deben registrarse desde su módulo especializado para no duplicar la salida.');
            }
            if (Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('referencia', $referencia)->exists()) {
                throw new \InvalidArgumentException('Ya existe un pago con esa referencia.');
            }
            $liquida = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->lockForUpdate()->findOrFail($cuentaLiquidaId);
            if ($liquida->saldoCentavos() < $montoCentavos) {
                throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
            }
            if (in_array($tipo, ['gasto', 'servicio', 'deuda_personal', 'otra_obligacion'], true) && $categoriaId === null) {
                throw new \InvalidArgumentException('Este pago requiere una categoría de gasto.');
            }
            $cuentaDestino = $this->cuentaDestino($usuarioId, $tipo, $categoriaId, $prestamoId, $tarjetaId);
            $pago = Pago::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'tipo' => $tipo, 'prestamo_id' => $prestamoId,
                'tarjeta_credito_id' => $tarjetaId, 'categoria_id' => $categoriaId,
                'cuenta_liquida_id' => $liquida->id, 'fecha' => $fecha, 'monto_centavos' => $montoCentavos,
                'destino' => $destino, 'referencia' => $referencia, 'observaciones' => $observaciones,
                'descripcion' => $observaciones ?: 'Pago - '.$destino,
                'capital_centavos' => in_array($tipo, ['gasto', 'servicio'], true) ? 0 : $montoCentavos,
            ]);
            $this->contabilizacion->postear($usuarioId, $fecha, $pago->descripcion, Pago::class, $pago->id, [
                ['cuenta_contable_id' => $cuentaDestino, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
            ]);

            return $pago;
        });
    }

    private function cuentaDestino(int $usuarioId, string $tipo, ?int $categoriaId, ?int $prestamoId, ?int $tarjetaId): int
    {
        if (in_array($tipo, ['prestamo', 'credito'], true)) {
            return (int) Prestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($prestamoId)->cuenta_contable_id;
        }
        if ($tipo === 'tarjeta') {
            return (int) TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($tarjetaId)->cuenta_contable_id;
        }

        return (int) Categoria::withoutGlobalScopes()->where('usuario_id', $usuarioId)
            ->where('tipo', 'gasto')->findOrFail($categoriaId)->cuenta_contable_id;
    }
}
