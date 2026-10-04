<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CicloFacturacion;
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
        ?string $entidad = null,
        float $porcentajeAbonoCapitalMinimo = 5.0,
        int $cuotaManejoCentavos = 0,
        float $tasaMoraMensual = 0.0
    ): TarjetaCredito {
        if ($cupoCentavos <= 0 || $diaCorte < 1 || $diaCorte > 31 || $diaPago < 1 || $diaPago > 31) {
            throw new \InvalidArgumentException('Los datos de la tarjeta no son válidos.');
        }
        if ($tasaComprasMensual < 0 || $tasaAvancesMensual < 0 || $tasaMoraMensual < 0) {
            throw new \InvalidArgumentException('Las tasas no pueden ser negativas.');
        }
        if ($porcentajeAbonoCapitalMinimo < 0 || $porcentajeAbonoCapitalMinimo > 100 || $cuotaManejoCentavos < 0) {
            throw new \InvalidArgumentException('El mínimo de capital o la cuota de manejo no son válidos.');
        }

        return DB::transaction(function () use (
            $usuarioId, $nombre, $cupoCentavos, $diaCorte, $diaPago,
            $tasaComprasMensual, $tasaAvancesMensual, $entidad,
            $porcentajeAbonoCapitalMinimo, $cuotaManejoCentavos, $tasaMoraMensual
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
                'porcentaje_abono_capital_minimo' => $porcentajeAbonoCapitalMinimo,
                'cuota_manejo_centavos' => $cuotaManejoCentavos,
                'tasa_mora_mensual' => $tasaMoraMensual,
                'activa' => true,
            ]);
        });
    }

    /**
     * Actualiza datos de la tarjeta. Cupo no puede bajar del pasivo actual.
     * Si cambian día de corte o de pago: se recalculan vencimientos de cuotas
     * aún no facturadas y la fecha límite del extracto abierto. Extractos
     * ya cerrados y cuotas ya cargadas a un ciclo no se tocan. Las tasas
     * nuevas solo aplican a compras nuevas y a causaciones de cortes futuros.
     */
    public function actualizar(
        int $usuarioId,
        int $tarjetaId,
        string $nombre,
        int $cupoCentavos,
        int $diaCorte,
        int $diaPago,
        float $tasaComprasMensual,
        float $tasaAvancesMensual,
        ?string $entidad = null,
        float $porcentajeAbonoCapitalMinimo = 5.0,
        int $cuotaManejoCentavos = 0,
        float $tasaMoraMensual = 0.0
    ): TarjetaCredito {
        if ($cupoCentavos <= 0 || $diaCorte < 1 || $diaCorte > 31 || $diaPago < 1 || $diaPago > 31) {
            throw new \InvalidArgumentException('Los datos de la tarjeta no son válidos.');
        }
        if ($tasaComprasMensual < 0 || $tasaAvancesMensual < 0 || $tasaMoraMensual < 0) {
            throw new \InvalidArgumentException('Las tasas no pueden ser negativas.');
        }
        if ($porcentajeAbonoCapitalMinimo < 0 || $porcentajeAbonoCapitalMinimo > 100 || $cuotaManejoCentavos < 0) {
            throw new \InvalidArgumentException('El mínimo de capital o la cuota de manejo no son válidos.');
        }

        return DB::transaction(function () use (
            $usuarioId, $tarjetaId, $nombre, $cupoCentavos, $diaCorte, $diaPago,
            $tasaComprasMensual, $tasaAvancesMensual, $entidad,
            $porcentajeAbonoCapitalMinimo, $cuotaManejoCentavos, $tasaMoraMensual
        ): TarjetaCredito {
            $tarjeta = TarjetaCredito::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($tarjetaId);

            if (! $tarjeta->activa) {
                throw new \InvalidArgumentException('La tarjeta no está activa.');
            }

            $pasivo = $this->saldoPasivoCentavos($tarjeta);
            if ($cupoCentavos < $pasivo) {
                throw new \InvalidArgumentException('El cupo no puede ser menor que el saldo actual de la tarjeta.');
            }

            $cambioCiclo = (int) $tarjeta->dia_corte !== $diaCorte
                || (int) $tarjeta->dia_pago !== $diaPago;

            $tarjeta->forceFill([
                'nombre' => $nombre,
                'cupo_centavos' => $cupoCentavos,
                'dia_corte' => $diaCorte,
                'dia_pago' => $diaPago,
                'tasa_compras_mensual' => $tasaComprasMensual,
                'tasa_avances_mensual' => $tasaAvancesMensual,
                'ea_porcentaje' => Tasa::eaDesdeMensual($tasaComprasMensual),
                'entidad' => $entidad,
                'porcentaje_abono_capital_minimo' => $porcentajeAbonoCapitalMinimo,
                'cuota_manejo_centavos' => $cuotaManejoCentavos,
                'tasa_mora_mensual' => $tasaMoraMensual,
            ])->save();

            CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereKey((int) $tarjeta->cuenta_contable_id)
                ->update(['nombre' => 'Tarjeta - '.$nombre]);

            if ($cambioCiclo) {
                $this->reprogramarFechasDeCiclo($tarjeta->fresh());
            }

            return $tarjeta->fresh();
        });
    }

    /**
     * Como en el banco al cambiar fecha de pago/corte: mueve la fecha límite
     * del extracto abierto (sin tocar su corte ni montos) y recalcula
     * vencimientos de cuotas no facturadas.
     */
    private function reprogramarFechasDeCiclo(TarjetaCredito $tarjeta): void
    {
        $extracto = app(ExtractoTarjetaService::class);

        $abierto = CicloFacturacion::withoutGlobalScopes()
            ->where('usuario_id', $tarjeta->usuario_id)
            ->where('tarjeta_credito_id', $tarjeta->id)
            ->where('estado', 'abierto')
            ->lockForUpdate()
            ->first();

        if ($abierto) {
            $nuevaFechaPago = $extracto->fechaLimite(
                $tarjeta,
                Carbon::parse($abierto->fecha_corte)->startOfDay()
            );
            $abierto->forceFill([
                'fecha_pago' => $nuevaFechaPago->toDateString(),
            ])->save();
        }

        $cuotas = CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $tarjeta->usuario_id)
            ->where('tarjeta_credito_id', $tarjeta->id)
            ->whereNull('ciclo_facturacion_id')
            ->where('pagada', false)
            ->with('compra')
            ->lockForUpdate()
            ->get();

        foreach ($cuotas as $cuota) {
            $compra = $cuota->compra;
            if (! $compra instanceof CompraTarjeta || $compra->anulada) {
                continue;
            }
            $nueva = $extracto->vencimientoCuota(
                $tarjeta,
                Carbon::parse($compra->fecha)->startOfDay(),
                (int) $cuota->numero
            );
            $cuota->forceFill([
                'fecha_vencimiento' => $nueva->toDateString(),
            ])->save();
        }
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

            // Corriente y avance a 1 cuota: interés 0 en el cronograma.
            // La corriente rota si no se paga el total. El avance causa
            // interés de avances al corte, desde el día del retiro.
            // Compra o avance a 2+ cuotas: interés contractual de la cuota.
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
                $interes = ($cuotas <= 1)
                    ? 0
                    : (int) round($saldoCapital * ($tasaMensual / 100));
                $capitalCuota = $i === $cuotas ? $montoCentavos - ($capital * ($cuotas - 1)) : $capital;
                CuotaTarjeta::withoutGlobalScopes()->create([
                    'usuario_id' => $usuarioId,
                    'compra_tarjeta_id' => $compra->id,
                    'tarjeta_credito_id' => $tarjeta->id,
                    'numero' => $i,
                    'fecha_vencimiento' => app(ExtractoTarjetaService::class)
                        ->vencimientoCuota($tarjeta, Carbon::parse($fecha), $i)
                        ->toDateString(),
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
     * Paga el extracto abierto. Sin monto, cubre el mínimo restante
     * (o el total, si el mínimo ya quedó cubierto).
     * El cuarto argumento se conserva por llamadas viejas y no elige la cuota.
     */
    public function registrarPago(
        int $usuarioId,
        int $tarjetaId,
        int $cuentaLiquidaId,
        ?int $cuotaId,
        string $fecha,
        ?int $montoCentavos = null
    ): Pago {
        return app(ExtractoTarjetaService::class)->registrarPago(
            $usuarioId,
            $tarjetaId,
            $cuentaLiquidaId,
            $cuotaId,
            $fecha,
            $montoCentavos
        );
    }

    /**
     * Corrige el último pago del extracto (reverso). No reabre un pago
     * si ya se cerró un corte posterior.
     */
    public function corregirPago(
        int $usuarioId,
        int $pagoId,
        string $fecha,
        string $motivo = 'Corrección de pago de tarjeta'
    ): void {
        app(ExtractoTarjetaService::class)->corregirPago($usuarioId, $pagoId, $fecha, $motivo);
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

            $bloqueada = CuotaTarjeta::withoutGlobalScopes()
                ->where('compra_tarjeta_id', $compra->id)
                ->where(function ($q): void {
                    $q->where('pagada', true)->orWhereNotNull('ciclo_facturacion_id');
                })
                ->exists();
            if ($bloqueada) {
                throw new \InvalidArgumentException('No se puede anular: la compra ya entró a un extracto o tiene cuotas pagadas.');
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

}
