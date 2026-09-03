<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Support\AmortizacionFrancesa;
use App\Support\Tasa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PrestamoService
{
    public function __construct(private readonly ContabilizacionService $contabilizacion) {}

    public function crear(
        int $usuarioId,
        string $nombre,
        int $principalCentavos,
        float $eaPorcentaje,
        int $plazoMeses,
        string $fechaDesembolso,
        int $diaPago,
        int $cuentaLiquidaId,
        ?string $entidad = null,
        string $tipoObligacion = 'prestamo_bancario',
        string $tipoTasa = 'ea',
        string $periodicidad = 'mensual',
        ?string $fechaVencimiento = null
        ,string $metodoAmortizacion = 'frances',
        int $seguroCentavos = 0,
        int $otrosCargosCentavos = 0
    ): Prestamo {
        if ($principalCentavos <= 0 || $plazoMeses < 1 || $diaPago < 1 || $diaPago > 31) {
            throw new \InvalidArgumentException('Los datos del préstamo no son válidos.');
        }

        return DB::transaction(function () use (
            $usuarioId, $nombre, $principalCentavos, $eaPorcentaje, $plazoMeses,
            $fechaDesembolso, $diaPago, $cuentaLiquidaId, $entidad, $tipoObligacion,
            $tipoTasa, $periodicidad, $fechaVencimiento, $metodoAmortizacion, $seguroCentavos, $otrosCargosCentavos
        ): Prestamo {
            $liquida = CuentaLiquida::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->findOrFail($cuentaLiquidaId);
            $pasivo = CuentaContable::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'codigo' => '21'.str_pad((string) (1000 + $usuarioId), 6, '0', STR_PAD_LEFT),
                'nombre' => 'Préstamo - '.$nombre,
                'naturaleza' => 'pasivo',
            ]);
            $tasa = Tasa::mensualDesdeEA($eaPorcentaje);
            $calendario = AmortizacionFrancesa::calendarioMetodo(
                $principalCentavos, $tasa, $plazoMeses, Carbon::parse($fechaDesembolso)->startOfMonth(),
                $metodoAmortizacion, $seguroCentavos, $otrosCargosCentavos
            );
            $prestamo = Prestamo::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'cuenta_contable_id' => $pasivo->id,
                'cuenta_liquida_id' => $liquida->id, 'nombre' => $nombre,
                'principal_centavos' => $principalCentavos, 'ea_porcentaje' => $eaPorcentaje,
                'plazo_meses' => $plazoMeses, 'fecha_desembolso' => $fechaDesembolso,
                'dia_pago' => $diaPago, 'cuota_centavos' => $calendario[0]['cuota'],
                'entidad' => $entidad, 'tipo_obligacion' => $tipoObligacion,
                'tipo_tasa' => $tipoTasa, 'periodicidad' => $periodicidad,
                'metodo_amortizacion' => $metodoAmortizacion, 'seguro_centavos' => $seguroCentavos,
                'otros_cargos_centavos' => $otrosCargosCentavos,
                'fecha_vencimiento' => $fechaVencimiento ?: end($calendario)['fecha'],
            ]);
            foreach ($calendario as $fila) {
                CuotaPrestamo::withoutGlobalScopes()->create([
                    'usuario_id' => $usuarioId, 'prestamo_id' => $prestamo->id,
                    'numero' => $fila['numero'], 'fecha_vencimiento' => $fila['fecha'],
                    'capital_centavos' => $fila['capital'], 'interes_centavos' => $fila['interes'],
                    'saldo_capital_centavos' => $fila['saldo'], 'seguro_centavos' => $fila['seguro'],
                    'otros_cargos_centavos' => $fila['otros'], 'total_centavos' => $fila['total'],
                ]);
            }
            $this->contabilizacion->postear($usuarioId, $fechaDesembolso, 'Desembolso '.$nombre, Prestamo::class, $prestamo->id, [
                ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => $principalCentavos, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $pasivo->id, 'debe_centavos' => 0, 'haber_centavos' => $principalCentavos],
            ]);
            return $prestamo->load('cuotas');
        });
    }

    public function registrarPago(int $usuarioId, int $prestamoId, int $montoCentavos, string $fecha): Pago
    {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El pago debe ser positivo.');
        }

        return DB::transaction(function () use ($usuarioId, $prestamoId, $montoCentavos, $fecha): Pago {
            $prestamo = Prestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($prestamoId);
            $liquida = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($prestamo->cuenta_liquida_id);
            $cuota = CuotaPrestamo::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->where('prestamo_id', $prestamo->id)
                ->where('pagada', false)->orderBy('numero')->first();
            if (! $cuota) {
                throw new \InvalidArgumentException('El préstamo no tiene cuotas pendientes.');
            }
            $pago = Pago::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'tipo' => 'prestamo', 'prestamo_id' => $prestamo->id,
                'cuenta_liquida_id' => $liquida->id, 'fecha' => $fecha, 'monto_centavos' => $montoCentavos,
                'capital_centavos' => min($cuota->capital_centavos, $montoCentavos),
                'interes_centavos' => min($cuota->interes_centavos, max(0, $montoCentavos - $cuota->capital_centavos)),
                'descripcion' => 'Pago de cuota '.$cuota->numero,
            ]);
            $interes = (int) $pago->interes_centavos;
            $movimientos = [
                ['cuenta_contable_id' => $prestamo->cuenta_contable_id, 'debe_centavos' => $montoCentavos - $interes, 'haber_centavos' => 0],
                ['cuenta_contable_id' => CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('codigo', '5200')->firstOrFail()->id, 'debe_centavos' => $interes, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
            ];
            $this->contabilizacion->postear($usuarioId, $fecha, 'Pago de préstamo '.$prestamo->nombre, Pago::class, $pago->id, $movimientos);
            if ($montoCentavos >= $cuota->capital_centavos + $cuota->interes_centavos) {
                $cuota->update(['pagada' => true, 'pagada_en' => now()]);
            }
            return $pago;
        });
    }
}
