<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\CompraTarjeta;
use App\Models\CuotaTarjeta;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\Pago;
use App\Models\TarjetaCredito;
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
        float $tasaMensualPorcentaje,
        ?string $entidad = null
    ): TarjetaCredito {
        if ($cupoCentavos <= 0 || $diaCorte < 1 || $diaCorte > 31 || $diaPago < 1 || $diaPago > 31) {
            throw new \InvalidArgumentException('Los datos de la tarjeta no son válidos.');
        }
        return DB::transaction(function () use ($usuarioId, $nombre, $cupoCentavos, $diaCorte, $diaPago, $tasaMensualPorcentaje, $entidad): TarjetaCredito {
            $cuenta = CuentaContable::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'codigo' => '22'.str_pad((string) (1000 + $usuarioId + CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)->count()), 6, '0', STR_PAD_LEFT),
                'nombre' => 'Tarjeta - '.$nombre, 'naturaleza' => 'pasivo',
            ]);
            return TarjetaCredito::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'cuenta_contable_id' => $cuenta->id,
                'nombre' => $nombre, 'cupo_centavos' => $cupoCentavos,
                'dia_corte' => $diaCorte, 'dia_pago' => $diaPago,
                'ea_porcentaje' => \App\Support\Tasa::eaDesdeMensual($tasaMensualPorcentaje),
                'entidad' => $entidad,
            ]);
        });
    }

    public function registrarCompra(
        int $usuarioId,
        int $tarjetaId,
        int $montoCentavos,
        int $cuotas,
        string $fecha,
        ?int $categoriaId = null,
        ?string $descripcion = null,
        float $tasaInteresPorcentaje = 0
    ): CompraTarjeta {
        if ($montoCentavos <= 0 || $cuotas < 1) {
            throw new \InvalidArgumentException('Monto y cuotas deben ser positivos.');
        }
        return DB::transaction(function () use ($usuarioId, $tarjetaId, $montoCentavos, $cuotas, $fecha, $categoriaId, $descripcion, $tasaInteresPorcentaje): CompraTarjeta {
            $tarjeta = TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($tarjetaId);
            if ($montoCentavos > $tarjeta->cupo_disponible_centavos) {
                throw new \InvalidArgumentException('La compra supera el cupo disponible de la tarjeta.');
            }
            $categoria = $categoriaId
                ? Categoria::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($categoriaId)
                : CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('codigo', '5100')->firstOrFail();
            $categoriaCuentaId = $categoriaId ? $categoria->cuenta_contable_id : $categoria->id;
            $compra = CompraTarjeta::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'tarjeta_credito_id' => $tarjeta->id,
                'categoria_id' => $categoriaId, 'fecha' => $fecha,
                'monto_centavos' => $montoCentavos, 'cuotas' => $cuotas,
                'tasa_interes_porcentaje' => $tasaInteresPorcentaje, 'descripcion' => $descripcion,
            ]);
            $capital = intdiv($montoCentavos, $cuotas);
            $saldoCapital = $montoCentavos;
            for ($i = 1; $i <= $cuotas; $i++) {
                $interes = (int) round($saldoCapital * ($tasaInteresPorcentaje / 100));
                CuotaTarjeta::withoutGlobalScopes()->create([
                    'usuario_id' => $usuarioId, 'compra_tarjeta_id' => $compra->id,
                    'tarjeta_credito_id' => $tarjeta->id, 'numero' => $i,
                    'fecha_vencimiento' => $this->fechaCuota($tarjeta, Carbon::parse($fecha), $i)->toDateString(),
                    'capital_centavos' => $i === $cuotas ? $montoCentavos - ($capital * ($cuotas - 1)) : $capital,
                    'interes_centavos' => $interes,
                ]);
                $saldoCapital -= $i === $cuotas ? $montoCentavos - ($capital * ($cuotas - 1)) : $capital;
            }
            $this->contabilizacion->postear($usuarioId, $fecha, $descripcion ?? 'Compra con tarjeta', CompraTarjeta::class, $compra->id, [
                ['cuenta_contable_id' => $categoriaCuentaId, 'debe_centavos' => $montoCentavos, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $tarjeta->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
            ]);
            return $compra->load('cuotasProgramadas');
        });
    }

    public function registrarPago(int $usuarioId, int $tarjetaId, int $cuentaLiquidaId, int $cuotaId, string $fecha): Pago
    {
        return DB::transaction(function () use ($usuarioId, $tarjetaId, $cuentaLiquidaId, $cuotaId, $fecha): Pago {
            $tarjeta = TarjetaCredito::withoutGlobalScopes()->where('usuario_id', $usuarioId)->lockForUpdate()->findOrFail($tarjetaId);
            $liquida = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->lockForUpdate()->findOrFail($cuentaLiquidaId);
            $cuota = CuotaTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->where('tarjeta_credito_id', $tarjeta->id)
                ->whereKey($cuotaId)->where('pagada', false)->lockForUpdate()->firstOrFail();
            $montoCentavos = (int) $cuota->capital_centavos + (int) $cuota->interes_centavos;
            if ($liquida->saldoCentavos() < $montoCentavos) {
                throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
            }
            $pago = Pago::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'tipo' => 'tarjeta', 'tarjeta_credito_id' => $tarjeta->id,
                'cuenta_liquida_id' => $liquida->id, 'fecha' => $fecha, 'monto_centavos' => $montoCentavos,
                'capital_centavos' => $cuota->capital_centavos,
                'interes_centavos' => $cuota->interes_centavos,
                'descripcion' => 'Pago de cuota '.$cuota->numero,
            ]);
            $cuota->forceFill(['pagada' => true, 'pagada_en' => now()])->save();
            $capital = (int) $cuota->capital_centavos;
            $interes = (int) $cuota->interes_centavos;
            $movimientos = [
                ['cuenta_contable_id' => $tarjeta->cuenta_contable_id, 'debe_centavos' => $capital, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
            ];
            if ($interes > 0) {
                $gastoId = CuentaContable::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)->where('codigo', '5200')->firstOrFail()->id;
                $movimientos[] = ['cuenta_contable_id' => $gastoId, 'debe_centavos' => $interes, 'haber_centavos' => 0];
            }
            $this->contabilizacion->postear($usuarioId, $fecha, 'Pago de tarjeta '.$tarjeta->nombre, Pago::class, $pago->id, $movimientos);
            return $pago;
        });
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
