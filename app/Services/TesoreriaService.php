<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Support\CuentasOperativas;
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
        ?string $tipoGasto = null,
        ?int $metaAhorroId = null
    ): HechoTesoreria {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El monto debe ser positivo.');
        }
        if (in_array($tipo, [TipoHechoTesoreria::Ingreso, TipoHechoTesoreria::Gasto], true)
            && ($cuentaLiquidaId === null || $categoriaId === null)) {
            throw new \InvalidArgumentException('Ingresos y gastos requieren cuenta y categoría.');
        }
        if (in_array($tipo, [
            TipoHechoTesoreria::Transferencia,
            TipoHechoTesoreria::AporteMeta,
            TipoHechoTesoreria::RetiroMeta,
        ], true) && ($cuentaLiquidaId === null || $cuentaDestinoId === null)) {
            throw new \InvalidArgumentException('Transferencias, aportes y retiros de meta requieren origen y destino.');
        }
        if (in_array($tipo, [TipoHechoTesoreria::AporteMeta, TipoHechoTesoreria::RetiroMeta], true)
            && $metaAhorroId === null) {
            throw new \InvalidArgumentException('El movimiento de meta requiere una meta de ahorro.');
        }

        return DB::transaction(function () use (
            $usuarioId, $tipo, $fecha, $montoCentavos, $cuentaLiquidaId,
            $categoriaId, $cuentaDestinoId, $descripcion, $tipoGasto, $metaAhorroId
        ): HechoTesoreria {
            $cuenta = $cuentaLiquidaId
                ? CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->lockForUpdate()->findOrFail($cuentaLiquidaId)
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

            $bolsillos = CuentasOperativas::idsBolsillosMetas($usuarioId);
            $origenEsBolsillo = $cuenta && in_array((int) $cuenta->id, $bolsillos, true);
            $destinoEsBolsillo = $cuentaDestinoId !== null && in_array((int) $cuentaDestinoId, $bolsillos, true);

            if ($tipo === TipoHechoTesoreria::Transferencia) {
                if ($origenEsBolsillo || $destinoEsBolsillo) {
                    throw new \InvalidArgumentException('No uses un bolsillo de meta en transferencias; usa aportes o retiros de meta.');
                }
            } elseif ($tipo === TipoHechoTesoreria::AporteMeta) {
                if ($origenEsBolsillo) {
                    throw new \InvalidArgumentException('El aporte debe salir de una cuenta operativa, no de un bolsillo de meta.');
                }
                if (! $destinoEsBolsillo) {
                    throw new \InvalidArgumentException('El aporte debe ir al bolsillo de la meta.');
                }
            } elseif ($tipo === TipoHechoTesoreria::RetiroMeta) {
                if (! $origenEsBolsillo) {
                    throw new \InvalidArgumentException('El retiro debe salir del bolsillo de la meta.');
                }
                if ($destinoEsBolsillo) {
                    throw new \InvalidArgumentException('El retiro debe ir a una cuenta operativa, no a otro bolsillo.');
                }
            } elseif ($cuenta && $origenEsBolsillo) {
                throw new \InvalidArgumentException('El bolsillo de una meta solo se mueve con aportes o retiros de meta.');
            }

            if ($tipo === TipoHechoTesoreria::Gasto && $cuenta && $cuenta->saldoCentavos() < $montoCentavos) {
                throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
            }
            if ($tipo === TipoHechoTesoreria::Cierre) {
                if ($cuentaLiquidaId === null) {
                    throw new \InvalidArgumentException('El cierre requiere una cuenta de origen.');
                }
                if ($cuenta && $cuenta->saldoCentavos() < $montoCentavos) {
                    throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de origen.');
                }
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
                TipoHechoTesoreria::Transferencia,
                TipoHechoTesoreria::AporteMeta,
                TipoHechoTesoreria::RetiroMeta => $this->movimientosTransferencia($usuarioId, $cuenta, $cuentaDestinoId, $montoCentavos),
                TipoHechoTesoreria::Apertura => [
                    ['cuenta_contable_id' => $cuenta?->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $this->cuentaPatrimonio($usuarioId), 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ],
                TipoHechoTesoreria::Cierre => [
                    ['cuenta_contable_id' => $this->cuentaPatrimonio($usuarioId), 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $cuenta?->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ],
            };

            $hecho = HechoTesoreria::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'tipo_gasto' => $tipoGasto,
                'cuenta_liquida_id' => $cuentaLiquidaId,
                'cuenta_destino_id' => $cuentaDestinoId,
                'categoria_id' => $categoriaId,
                'meta_ahorro_id' => $metaAhorroId,
                'fecha' => $fecha,
                'monto_centavos' => $montoCentavos,
                'descripcion' => $descripcion,
            ]);
            $this->contabilizacion->postear(
                $usuarioId,
                $fecha,
                $descripcion ?? $tipo->value,
                HechoTesoreria::class,
                $hecho->id,
                $movimientos
            );

            return $hecho;
        });
    }

    /**
     * Corrige una transferencia entre cuentas propias (reverso contable).
     * No aplica a aportes ni retiros de meta (usa MetaAhorroService::corregirMovimiento).
     */
    public function corregirTransferencia(
        int $usuarioId,
        int $hechoId,
        string $fecha,
        string $motivo = 'Corrección de transferencia'
    ): void {
        DB::transaction(function () use ($usuarioId, $hechoId, $fecha, $motivo): void {
            $hecho = HechoTesoreria::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tipo', TipoHechoTesoreria::Transferencia)
                ->lockForUpdate()
                ->findOrFail($hechoId);

            $asiento = Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('origen_tipo', HechoTesoreria::class)
                ->where('origen_id', $hecho->id)
                ->where('es_reverso', false)
                ->firstOrFail();

            if (Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('asiento_reversado_id', $asiento->id)
                ->exists()) {
                throw new \InvalidArgumentException('Esta transferencia ya fue corregida.');
            }

            $this->contabilizacion->revertir($usuarioId, (int) $asiento->id, $fecha, $motivo);
        });
    }

    private function movimientosTransferencia(int $usuarioId, ?CuentaLiquida $origen, ?int $destinoId, int $monto): array
    {
        $destino = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('activa', true)->lockForUpdate()->findOrFail($destinoId);
        if ($origen === null) {
            throw new \InvalidArgumentException('La cuenta de origen es obligatoria.');
        }
        if ($origen->id === $destino->id) {
            throw new \InvalidArgumentException('El origen y destino deben ser diferentes.');
        }
        if ($origen->saldoCentavos() < $monto) {
            throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de origen.');
        }

        return [
            ['cuenta_contable_id' => $destino->cuenta_contable_id, 'debe_centavos' => $monto, 'haber_centavos' => 0],
            ['cuenta_contable_id' => $origen->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $monto],
        ];
    }

    private function cuentaPatrimonio(int $usuarioId): int
    {
        $id = \App\Models\CuentaContable::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('codigo', '3100')->value('id');
        if (! $id) {
            throw new \InvalidArgumentException('El usuario no tiene cuenta de patrimonio.');
        }

        return (int) $id;
    }
}
