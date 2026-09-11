<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CompraTarjeta;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\CuotaTarjeta;
use App\Models\Pago;
use App\Models\TarjetaCredito;
use App\Support\CuentasOperativas;
use App\Support\Tasa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TarjetaService
{
    public function __construct(private readonly ContabilizacionService $contabilizacion) {}

    public function crear(
        int $usuarioId,
        string $nombre,
        int $cupoCentavos,
        int $diaCorte,
        int $diaPago,
        float $tasaComprasMensual,
        float $tasaAvancesMensual,
        ?string $entidad = null
    ): TarjetaCredito {
        if ($cupoCentavos <= 0 || $diaCorte < 1 || $diaCorte > 31 || $diaPago < 1 || $diaPago > 31) {
            throw new \InvalidArgumentException('Los datos de la tarjeta no son válidos.');
        }
        if ($tasaComprasMensual < 0 || $tasaAvancesMensual < 0) {
            throw new \InvalidArgumentException('Las tasas no pueden ser negativas.');
        }

        return DB::transaction(function () use (
            $usuarioId, $nombre, $cupoCentavos, $diaCorte, $diaPago,
            $tasaComprasMensual, $tasaAvancesMensual, $entidad
        ): TarjetaCredito {
            $cuenta = CuentaContable::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'codigo' => $this->siguienteCodigoPasivo($usuarioId),
                'nombre' => 'Tarjeta - '.$nombre,
                'naturaleza' => 'pasivo',
            ]);

            return TarjetaCredito::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'cuenta_contable_id' => $cuenta->id,
                'nombre' => $nombre,
                'cupo_centavos' => $cupoCentavos,
                'dia_corte' => $diaCorte,
                'dia_pago' => $diaPago,
                'tasa_compras_mensual' => $tasaComprasMensual,
                'tasa_avances_mensual' => $tasaAvancesMensual,
                'ea_porcentaje' => Tasa::eaDesdeMensual($tasaComprasMensual),
                'entidad' => $entidad,
                'activa' => true,
            ]);
        });
    }

    /**
     * @param  'compra'|'avance'  $tipo
     */
    public function registrarCompra(
        int $usuarioId,
        int $tarjetaId,
        int $montoCentavos,
        int $cuotas,
        string $fecha,
        string $tipo = 'compra',
        ?int $categoriaId = null,
        ?int $cuentaLiquidaId = null,
        ?string $descripcion = null
    ): CompraTarjeta {
        if ($montoCentavos <= 0 || $cuotas < 1) {
            throw new \InvalidArgumentException('Monto y cuotas deben ser positivos.');
        }
        if (! in_array($tipo, ['compra', 'avance'], true)) {
            throw new \InvalidArgumentException('El tipo debe ser compra o avance.');
        }

        return DB::transaction(function () use (
            $usuarioId, $tarjetaId, $montoCentavos, $cuotas, $fecha,
            $tipo, $categoriaId, $cuentaLiquidaId, $descripcion
        ): CompraTarjeta {
            $tarjeta = TarjetaCredito::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($tarjetaId);

            if (! $tarjeta->activa) {
                throw new \InvalidArgumentException('La tarjeta no está activa.');
            }

            $saldoPasivo = $this->saldoPasivoCentavos($tarjeta);
            $disponible = max(0, (int) $tarjeta->cupo_centavos - $saldoPasivo);
            if ($montoCentavos > $disponible) {
                throw new \InvalidArgumentException('La operación supera el cupo disponible de la tarjeta.');
            }

            // Corriente (compra a 1 cuota): 0% programado — el interés nace solo si se rota el saldo (revolving, fuera de fase).
            // Compra a 2+ cuotas: tasa de compras. Avance: siempre tasa de avances (también a 1 cuota).
            $tasaMensual = match (true) {
                $tipo === 'avance' => (float) $tarjeta->tasa_avances_mensual,
                $cuotas >= 2 => (float) $tarjeta->tasa_compras_mensual,
                default => 0.0,
            };

            $movimientos = [];
            $etiqueta = $tipo === 'avance' ? 'Avance con tarjeta' : 'Compra con tarjeta';

            if ($tipo === 'avance') {
                if ($cuentaLiquidaId === null) {
                    throw new \InvalidArgumentException('El avance requiere una cuenta destino operativa.');
                }
                $liquida = $this->cuentaOperativaBloqueada($usuarioId, $cuentaLiquidaId);
                $movimientos = [
                    ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $tarjeta->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ];
            } else {
                if ($categoriaId === null) {
                    throw new \InvalidArgumentException('La compra requiere una categoría de gasto.');
                }
                $categoria = Categoria::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)
                    ->where('tipo', 'gasto')
                    ->findOrFail($categoriaId);
                $movimientos = [
                    ['cuenta_contable_id' => $categoria->cuenta_contable_id, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                    ['cuenta_contable_id' => $tarjeta->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
                ];
            }

            $compra = CompraTarjeta::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'tarjeta_credito_id' => $tarjeta->id,
                'tipo' => $tipo,
                'categoria_id' => $tipo === 'compra' ? $categoriaId : null,
                'cuenta_liquida_id' => $tipo === 'avance' ? $cuentaLiquidaId : null,
                'fecha' => $fecha,
                'monto_centavos' => $montoCentavos,
                'cuotas' => $cuotas,
                'tasa_interes_porcentaje' => $tasaMensual,
                'descripcion' => $descripcion,
                'anulada' => false,
            ]);

            $capital = intdiv($montoCentavos, $cuotas);
            $saldoCapital = $montoCentavos;
            for ($i = 1; $i <= $cuotas; $i++) {
                $interes = (int) round($saldoCapital * ($tasaMensual / 100));
                $capitalCuota = $i === $cuotas ? $montoCentavos - ($capital * ($cuotas - 1)) : $capital;
                CuotaTarjeta::withoutGlobalScopes()->create([
                    'usuario_id' => $usuarioId,
                    'compra_tarjeta_id' => $compra->id,
                    'tarjeta_credito_id' => $tarjeta->id,
                    'numero' => $i,
                    'fecha_vencimiento' => $this->fechaCuota($tarjeta, Carbon::parse($fecha), $i)->toDateString(),
                    'capital_centavos' => $capitalCuota,
                    'interes_centavos' => $interes,
                ]);
                $saldoCapital -= $capitalCuota;
            }

            $this->contabilizacion->postear(
                $usuarioId,
                $fecha,
                $descripcion ?? $etiqueta,
                CompraTarjeta::class,
                $compra->id,
                $movimientos
            );

            return $compra->load('cuotasProgramadas');
        });
    }

    /**
     * Paga la cuota indicada (o la próxima pendiente si $cuotaId es null).
     * Si $montoCentavos es null, cobra exactamente el total de esa cuota.
     * Un monto mayor es abono extraordinario: reduce capital de cuotas futuras
     * desde el final (acorta plazo); el interés programado de las cuotas
     * eliminadas no se reconoce.
     */
    public function registrarPago(
        int $usuarioId,
        int $tarjetaId,
        int $cuentaLiquidaId,
        ?int $cuotaId,
        string $fecha,
        ?int $montoCentavos = null
    ): Pago {
        return DB::transaction(function () use ($usuarioId, $tarjetaId, $cuentaLiquidaId, $cuotaId, $fecha, $montoCentavos): Pago {
            $tarjeta = TarjetaCredito::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($tarjetaId);

            if (! $tarjeta->activa) {
                throw new \InvalidArgumentException('La tarjeta no está activa.');
            }

            $liquida = $this->cuentaOperativaBloqueada($usuarioId, $cuentaLiquidaId);
            $cuotaBase = CuotaTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $tarjeta->id)
                ->where('pagada', false)
                ->lockForUpdate();

            if ($cuotaId !== null) {
                $cuota = (clone $cuotaBase)->whereKey($cuotaId)->first();
            } else {
                $cuota = (clone $cuotaBase)
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('numero')
                    ->orderBy('id')
                    ->first();
            }

            if (! $cuota) {
                throw new \InvalidArgumentException(
                    $cuotaId !== null
                        ? 'Esa cuota no existe, ya fue pagada o no pertenece a la tarjeta.'
                        : 'La tarjeta no tiene cuotas pendientes.'
                );
            }

            $compra = CompraTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereKey($cuota->compra_tarjeta_id)
                ->firstOrFail();
            if ($compra->anulada) {
                throw new \InvalidArgumentException('No se puede pagar una cuota de una compra anulada.');
            }

            $totalCuota = (int) $cuota->capital_centavos + (int) $cuota->interes_centavos;
            $pagar = $montoCentavos ?? $totalCuota;
            if ($pagar < $totalCuota) {
                throw new \InvalidArgumentException('El pago debe cubrir al menos la cuota completa. Los abonos extra se aplican sobre ese mínimo.');
            }

            $capitalCuota = (int) $cuota->capital_centavos;
            $interes = (int) $cuota->interes_centavos;
            $capitalPendiente = (int) CuotaTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $tarjeta->id)
                ->where('pagada', false)
                ->sum('capital_centavos');
            $extraMaximo = max(0, $capitalPendiente - $capitalCuota);
            $extra = $pagar - $totalCuota;
            if ($extra > $extraMaximo) {
                throw new \InvalidArgumentException('El abono extra supera el capital pendiente de la tarjeta.');
            }

            if ($liquida->saldoCentavos() < $pagar) {
                throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
            }

            $snapshot = null;
            if ($extra > 0) {
                $snapshot = $this->snapshotCuotasPendientes($usuarioId, (int) $tarjeta->id, (int) $cuota->id);
            }

            $pago = Pago::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'tipo' => 'tarjeta',
                'tarjeta_credito_id' => $tarjeta->id,
                'cuota_tarjeta_id' => $cuota->id,
                'cuenta_liquida_id' => $liquida->id,
                'fecha' => $fecha,
                'monto_centavos' => $pagar,
                'capital_centavos' => $capitalCuota + $extra,
                'interes_centavos' => $interes,
                'extraordinario' => $extra > 0,
                'cronograma_snapshot' => $snapshot,
                'descripcion' => $extra > 0
                    ? 'Pago de cuota '.$cuota->numero.' + abono extraordinario'
                    : 'Pago de cuota '.$cuota->numero,
            ]);
            $cuota->forceFill(['pagada' => true, 'pagada_en' => now()])->save();

            if ($extra > 0) {
                $this->aplicarAbonoExtraCapital($usuarioId, (int) $tarjeta->id, (int) $cuota->id, $extra);
            }

            $movimientos = [
                ['cuenta_contable_id' => $tarjeta->cuenta_contable_id, 'debe_centavos' => $capitalCuota + $extra, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $pagar],
            ];
            if ($interes > 0) {
                $gastoId = CuentaContable::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)->where('codigo', '5200')->firstOrFail()->id;
                $movimientos[] = ['cuenta_contable_id' => $gastoId, 'debe_centavos' => $interes, 'haber_centavos' => 0];
            }
            $this->contabilizacion->postear(
                $usuarioId,
                $fecha,
                'Pago de tarjeta '.$tarjeta->nombre,
                Pago::class,
                $pago->id,
                $movimientos
            );

            return $pago;
        });
    }

    /**
     * Corrige el último pago de la tarjeta (reverso + reapertura de cuota).
     * Si hubo abono extra, restaura el cronograma desde el snapshot.
     */
    public function corregirPago(
        int $usuarioId,
        int $pagoId,
        string $fecha,
        string $motivo = 'Corrección de pago de tarjeta'
    ): void {
        DB::transaction(function () use ($usuarioId, $pagoId, $fecha, $motivo): void {
            $pago = Pago::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tipo', 'tarjeta')
                ->lockForUpdate()
                ->findOrFail($pagoId);

            if ($pago->tarjeta_credito_id === null || $pago->cuota_tarjeta_id === null) {
                throw new \InvalidArgumentException('Este pago no está vinculado a una cuota de tarjeta.');
            }

            $ultimoId = (int) Pago::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $pago->tarjeta_credito_id)
                ->where('tipo', 'tarjeta')
                ->orderByDesc('id')
                ->value('id');
            if ($ultimoId !== (int) $pago->id) {
                throw new \InvalidArgumentException('Solo se puede corregir el último pago de la tarjeta.');
            }

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

            $cuota = CuotaTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereKey($pago->cuota_tarjeta_id)
                ->lockForUpdate()
                ->firstOrFail();
            $cuota->forceFill(['pagada' => false, 'pagada_en' => null])->save();

            if ($pago->extraordinario) {
                $this->restaurarCronogramaDesdeSnapshot(
                    $usuarioId,
                    (int) $pago->tarjeta_credito_id,
                    (int) $cuota->id,
                    $pago->cronograma_snapshot ?? []
                );
                $compras = collect($pago->cronograma_snapshot ?? [])
                    ->pluck('compra_tarjeta_id')
                    ->push((int) $cuota->compra_tarjeta_id)
                    ->unique()
                    ->filter();
                foreach ($compras as $compraId) {
                    $this->sincronizarConteoCuotasCompra($usuarioId, (int) $compraId);
                }
            }
        });
    }

    /**
     * Restaura solo las cuotas del snapshot (por id). No toca cuotas de compras
     * registradas después del pago (p. ej. una compra nueva en la misma tarjeta).
     *
     * @param  list<array{id?:int,compra_tarjeta_id:int,numero:int,fecha_vencimiento:?string,capital_centavos:int,interes_centavos:int}>  $snapshot
     */
    private function restaurarCronogramaDesdeSnapshot(
        int $usuarioId,
        int $tarjetaId,
        int $cuotaReabiertaId,
        array $snapshot
    ): void {
        foreach ($snapshot as $fila) {
            $id = isset($fila['id']) ? (int) $fila['id'] : 0;
            $datos = [
                'compra_tarjeta_id' => (int) $fila['compra_tarjeta_id'],
                'tarjeta_credito_id' => $tarjetaId,
                'numero' => (int) $fila['numero'],
                'fecha_vencimiento' => $fila['fecha_vencimiento'],
                'capital_centavos' => (int) $fila['capital_centavos'],
                'interes_centavos' => (int) $fila['interes_centavos'],
                'pagada' => false,
                'pagada_en' => null,
            ];

            $existente = null;
            if ($id > 0) {
                $existente = CuotaTarjeta::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)
                    ->where('tarjeta_credito_id', $tarjetaId)
                    ->whereKey($id)
                    ->where('id', '<>', $cuotaReabiertaId)
                    ->lockForUpdate()
                    ->first();
            }
            if (! $existente) {
                $existente = CuotaTarjeta::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)
                    ->where('tarjeta_credito_id', $tarjetaId)
                    ->where('compra_tarjeta_id', $datos['compra_tarjeta_id'])
                    ->where('numero', $datos['numero'])
                    ->where('pagada', false)
                    ->where('id', '<>', $cuotaReabiertaId)
                    ->lockForUpdate()
                    ->first();
            }

            if ($existente) {
                $existente->forceFill($datos)->save();
            } else {
                CuotaTarjeta::withoutGlobalScopes()->create(array_merge(
                    ['usuario_id' => $usuarioId],
                    $datos
                ));
            }
        }
    }

    /**
     * @return list<array{id:int,compra_tarjeta_id:int,numero:int,fecha_vencimiento:?string,capital_centavos:int,interes_centavos:int}>
     */
    private function snapshotCuotasPendientes(int $usuarioId, int $tarjetaId, int $excluirCuotaId): array
    {
        return CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tarjeta_credito_id', $tarjetaId)
            ->where('pagada', false)
            ->where('id', '<>', $excluirCuotaId)
            ->orderBy('fecha_vencimiento')
            ->orderBy('numero')
            ->orderBy('id')
            ->get(['id', 'compra_tarjeta_id', 'numero', 'fecha_vencimiento', 'capital_centavos', 'interes_centavos'])
            ->map(fn (CuotaTarjeta $c) => [
                'id' => (int) $c->id,
                'compra_tarjeta_id' => (int) $c->compra_tarjeta_id,
                'numero' => (int) $c->numero,
                'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
                'capital_centavos' => (int) $c->capital_centavos,
                'interes_centavos' => (int) $c->interes_centavos,
            ])
            ->values()
            ->all();
    }

    private function aplicarAbonoExtraCapital(int $usuarioId, int $tarjetaId, int $cuotaPagadaId, int $extraCentavos): void
    {
        $pendientes = CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tarjeta_credito_id', $tarjetaId)
            ->where('pagada', false)
            ->where('id', '<>', $cuotaPagadaId)
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('numero')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $restante = $extraCentavos;
        $comprasAfectadas = [];
        foreach ($pendientes as $cuota) {
            if ($restante <= 0) {
                break;
            }
            $capital = (int) $cuota->capital_centavos;
            $interes = (int) $cuota->interes_centavos;
            $comprasAfectadas[(int) $cuota->compra_tarjeta_id] = true;
            if ($restante >= $capital) {
                $restante -= $capital;
                $cuota->delete();

                continue;
            }
            $nuevoCapital = $capital - $restante;
            $nuevoInteres = $capital > 0 ? (int) round($interes * ($nuevoCapital / $capital)) : 0;
            $cuota->forceFill([
                'capital_centavos' => $nuevoCapital,
                'interes_centavos' => $nuevoInteres,
            ])->save();
            $restante = 0;
        }

        if ($restante > 0) {
            throw new \InvalidArgumentException('El abono extra supera el capital pendiente de la tarjeta.');
        }

        foreach (array_keys($comprasAfectadas) as $compraId) {
            $this->sincronizarConteoCuotasCompra($usuarioId, (int) $compraId);
        }
    }

    private function sincronizarConteoCuotasCompra(int $usuarioId, int $compraId): void
    {
        $count = (int) CuotaTarjeta::withoutGlobalScopes()
            ->where('compra_tarjeta_id', $compraId)
            ->count();
        CompraTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->whereKey($compraId)
            ->update(['cuotas' => max(1, $count)]);
    }

    /**
     * Anula una compra/avance sin cuotas pagadas (reverso + elimina cuotas).
     */
    public function corregirCompra(
        int $usuarioId,
        int $compraId,
        string $fecha,
        string $motivo = 'Corrección de compra con tarjeta'
    ): void {
        DB::transaction(function () use ($usuarioId, $compraId, $fecha, $motivo): void {
            $compra = CompraTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($compraId);

            if ($compra->anulada) {
                throw new \InvalidArgumentException('Esta operación ya fue anulada.');
            }

            $pagadas = CuotaTarjeta::withoutGlobalScopes()
                ->where('compra_tarjeta_id', $compra->id)
                ->where('pagada', true)
                ->exists();
            if ($pagadas) {
                throw new \InvalidArgumentException('No se puede anular: ya hay cuotas pagadas. Corrige primero esos pagos.');
            }

            $asiento = Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('origen_tipo', CompraTarjeta::class)
                ->where('origen_id', $compra->id)
                ->where('es_reverso', false)
                ->firstOrFail();

            if (Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('asiento_reversado_id', $asiento->id)
                ->exists()) {
                throw new \InvalidArgumentException('Esta operación ya fue corregida.');
            }

            $this->contabilizacion->revertir($usuarioId, (int) $asiento->id, $fecha, $motivo);

            CuotaTarjeta::withoutGlobalScopes()
                ->where('compra_tarjeta_id', $compra->id)
                ->delete();

            $compra->forceFill(['anulada' => true])->save();
        });
    }

    private function saldoPasivoCentavos(TarjetaCredito $tarjeta): int
    {
        $cuenta = CuentaContable::withoutGlobalScopes()->find($tarjeta->cuenta_contable_id);

        return $cuenta ? max(0, $cuenta->saldoCentavos()) : 0;
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
            throw new \InvalidArgumentException('La cuenta debe ser operativa y activa (no bolsillo de meta).');
        }

        return $liquida;
    }

    private function siguienteCodigoPasivo(int $usuarioId): string
    {
        $ultimo = CuentaContable::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('codigo', 'like', '22%')
            ->where('codigo', '<>', '2200')
            ->orderByDesc('codigo')
            ->value('codigo');
        $n = $ultimo ? ((int) substr((string) $ultimo, 2)) + 1 : 1001;

        return '22'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    private function fechaCuota(TarjetaCredito $tarjeta, Carbon $compra, int $numero): Carbon
    {
        $base = $compra->copy()->startOfDay();
        $diaPago = (int) $tarjeta->dia_pago;
        if ($base->day >= $diaPago) {
            $base->addMonth();
        }
        $base->day(min($diaPago, $base->daysInMonth));

        return $base->addMonths($numero - 1);
    }
}
