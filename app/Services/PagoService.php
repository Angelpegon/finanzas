<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\Pago;
use App\Support\CuentasOperativas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PagoService
{
    /** @var list<string> */
    public const TIPOS_GENERICOS = ['deuda_personal', 'otra_obligacion'];

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
        ?int $categoriaId = null
    ): Pago {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El monto del pago debe ser positivo.');
        }
        if (! in_array($tipo, self::TIPOS_GENERICOS, true)) {
            throw new \InvalidArgumentException('Este módulo solo registra pagos a terceros u obligaciones no rastreadas. Usa Gastos para consumo diario y Deudas/Tarjetas para cuotas.');
        }
        if ($categoriaId === null) {
            throw new \InvalidArgumentException('Este pago requiere una categoría de gasto.');
        }

        try {
            return DB::transaction(function () use (
                $usuarioId, $tipo, $montoCentavos, $cuentaLiquidaId, $fecha,
                $destino, $referencia, $observaciones, $categoriaId
            ): Pago {
                if (Pago::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('referencia', $referencia)->exists()) {
                    throw new \InvalidArgumentException('Ya existe un pago con esa referencia.');
                }

                $liquida = $this->cuentaOperativaBloqueada($usuarioId, $cuentaLiquidaId);
                if ($liquida->saldoCentavos() < $montoCentavos) {
                    throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
                }

                $categoria = Categoria::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)
                    ->where('tipo', 'gasto')
                    ->findOrFail($categoriaId);

                $pago = Pago::withoutGlobalScopes()->create([
                    'usuario_id' => $usuarioId,
                    'tipo' => $tipo,
                    'categoria_id' => $categoria->id,
                    'cuenta_liquida_id' => $liquida->id,
                    'fecha' => $fecha,
                    'monto_centavos' => $montoCentavos,
                    'destino' => $destino,
                    'referencia' => $referencia,
                    'observaciones' => $observaciones,
                    'descripcion' => $observaciones ?: 'Pago - '.$destino,
                    'capital_centavos' => 0,
                    'interes_centavos' => 0,
                ]);

                $this->contabilizacion->postear($usuarioId, $fecha, $pago->descripcion, Pago::class, $pago->id, [
                    ['cuenta_contable_id' => $categoria->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ]);

                return $pago;
            });
        } catch (QueryException $e) {
            if ($this->esViolacionUnicaReferencia($e)) {
                throw new \InvalidArgumentException('Ya existe un pago con esa referencia.');
            }
            throw $e;
        }
    }

    public function corregir(
        int $usuarioId,
        int $pagoId,
        string $fecha,
        string $motivo = 'Corrección de pago'
    ): void {
        DB::transaction(function () use ($usuarioId, $pagoId, $fecha, $motivo): void {
            $pago = Pago::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereIn('tipo', self::TIPOS_GENERICOS)
                ->lockForUpdate()
                ->findOrFail($pagoId);

            $asiento = Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('origen_tipo', Pago::class)
                ->where('origen_id', $pago->id)
                ->where('es_reverso', false)
                ->firstOrFail();

            if (Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('asiento_reversado_id', $asiento->id)
                ->exists()) {
                throw new \InvalidArgumentException('Este pago ya fue corregido.');
            }

            $this->contabilizacion->revertir($usuarioId, (int) $asiento->id, $fecha, $motivo);
        });
    }

    private function cuentaOperativaBloqueada(int $usuarioId, int $cuentaLiquidaId): CuentaLiquida
    {
        $bolsillos = CuentasOperativas::idsBolsillosActivos($usuarioId);
        $liquida = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->where('estado', 'activa')
            ->when($bolsillos !== [], fn ($q) => $q->whereNotIn('id', $bolsillos))
            ->lockForUpdate()
            ->find($cuentaLiquidaId);

        if (! $liquida) {
            throw new \InvalidArgumentException('La cuenta de pago debe ser operativa y activa (no bolsillo de meta).');
        }

        return $liquida;
    }

    private function esViolacionUnicaReferencia(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, 'pagos_usuario_id_referencia_unique')
            || str_contains($msg, 'UNIQUE constraint failed: pagos.usuario_id, pagos.referencia')
            || (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'referencia'));
    }
}
