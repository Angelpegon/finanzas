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
                ->where('usuario_id', $usuarioId)->where('activa', true)->lockForUpdate()->findOrFail($cuentaLiquidaId);
            $pasivo = CuentaContable::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'codigo' => $this->siguienteCodigoPasivo($usuarioId),
                'nombre' => 'Préstamo - '.$nombre,
                'naturaleza' => 'pasivo',
            ]);
            $tasa = Tasa::tasaPeriodo($eaPorcentaje, $tipoTasa, $periodicidad);
            $primeraFecha = $this->primeraFechaPago(Carbon::parse($fechaDesembolso), $diaPago, $periodicidad);
            $calendario = AmortizacionFrancesa::calendarioMetodo(
                $principalCentavos, $tasa, $plazoMeses, $primeraFecha,
                $metodoAmortizacion, $seguroCentavos, $otrosCargosCentavos, $periodicidad
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
            $prestamo = Prestamo::withoutGlobalScopes()->where('usuario_id', $usuarioId)->lockForUpdate()->findOrFail($prestamoId);
            $liquida = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('activa', true)->lockForUpdate()->findOrFail($prestamo->cuenta_liquida_id);
            $cuota = CuotaPrestamo::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->where('prestamo_id', $prestamo->id)
                ->where('pagada', false)->orderBy('numero')->lockForUpdate()->first();
            if (! $cuota) {
                throw new \InvalidArgumentException('El préstamo no tiene cuotas pendientes.');
            }
            $totalCuota = (int) ($cuota->total_centavos ?: ($cuota->capital_centavos + $cuota->interes_centavos + $cuota->seguro_centavos + $cuota->otros_cargos_centavos));
            if ($montoCentavos < $totalCuota) {
                throw new \InvalidArgumentException('El pago debe cubrir al menos la cuota completa. Los abonos extra se aplican sobre ese mínimo.');
            }
            $capital = (int) $cuota->capital_centavos;
            $interes = (int) $cuota->interes_centavos;
            $cargos = (int) $cuota->seguro_centavos + (int) $cuota->otros_cargos_centavos;
            $capitalPendiente = (int) CuotaPrestamo::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->where('prestamo_id', $prestamo->id)
                ->where('pagada', false)->sum('capital_centavos');
            $extraMaximo = max(0, $capitalPendiente - $capital);
            $extraSolicitado = $montoCentavos - $totalCuota;
            if ($extraSolicitado > $extraMaximo) {
                throw new \InvalidArgumentException('El abono extra supera el capital pendiente.');
            }
            $extra = $extraSolicitado;
            if ($liquida->saldoCentavos() < $montoCentavos) {
                throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
            }
            $pago = Pago::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId, 'tipo' => 'prestamo', 'prestamo_id' => $prestamo->id,
                'cuenta_liquida_id' => $liquida->id, 'fecha' => $fecha, 'monto_centavos' => $montoCentavos,
                'capital_centavos' => $capital + $extra,
                'interes_centavos' => $interes,
                'descripcion' => $extra > 0
                    ? 'Pago de cuota '.$cuota->numero.' + abono extraordinario'
                    : 'Pago de cuota '.$cuota->numero,
            ]);
            $gastoInteresId = CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('codigo', '5200')->firstOrFail()->id;
            $gastoOperativoId = CuentaContable::withoutGlobalScopes()->where('usuario_id', $usuarioId)->where('codigo', '5100')->firstOrFail()->id;
            $movimientos = [
                ['cuenta_contable_id' => $prestamo->cuenta_contable_id, 'debe_centavos' => $capital + $extra, 'haber_centavos' => 0],
                ['cuenta_contable_id' => $liquida->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => $montoCentavos],
            ];
            if ($interes > 0) {
                $movimientos[] = ['cuenta_contable_id' => $gastoInteresId, 'debe_centavos' => $interes, 'haber_centavos' => 0];
            }
            if ($cargos > 0) {
                $movimientos[] = ['cuenta_contable_id' => $gastoOperativoId, 'debe_centavos' => $cargos, 'haber_centavos' => 0];
            }
            $this->contabilizacion->postear($usuarioId, $fecha, 'Pago de préstamo '.$prestamo->nombre, Pago::class, $pago->id, $movimientos);
            $cuota->update(['pagada' => true, 'pagada_en' => now()]);
            if ($extra > 0) {
                $this->recalcularTrasAbonoExtra($prestamo, $extra, Carbon::parse($fecha));
            }

            return $pago;
        });
    }

    /**
     * Regla documentada: abono extraordinario reduce el plazo y mantiene la cuota.
     */
    private function recalcularTrasAbonoExtra(Prestamo $prestamo, int $extraCentavos, Carbon $fechaPago): void
    {
        $pendientes = CuotaPrestamo::withoutGlobalScopes()
            ->where('usuario_id', $prestamo->usuario_id)
            ->where('prestamo_id', $prestamo->id)
            ->where('pagada', false)
            ->orderBy('numero')
            ->get();
        if ($pendientes->isEmpty()) {
            $prestamo->update(['estado' => 'cancelada']);

            return;
        }

        $saldoRestante = (int) $pendientes->sum('capital_centavos') - $extraCentavos;
        CuotaPrestamo::withoutGlobalScopes()
            ->whereIn('id', $pendientes->pluck('id'))
            ->delete();

        if ($saldoRestante <= 0) {
            $prestamo->update(['estado' => 'cancelada', 'fecha_vencimiento' => $fechaPago->toDateString()]);

            return;
        }

        $tasa = Tasa::tasaPeriodo((float) $prestamo->ea_porcentaje, (string) ($prestamo->tipo_tasa ?: 'ea'), (string) ($prestamo->periodicidad ?: 'mensual'));
        $periodicidad = (string) ($prestamo->periodicidad ?: 'mensual');
        $metodo = $prestamo->metodo_amortizacion ?: 'frances';
        $cuotaFija = (int) $prestamo->cuota_centavos;
        if ($metodo === 'frances') {
            $plazos = AmortizacionFrancesa::plazosConCuotaFija($saldoRestante, $tasa, $cuotaFija);
        } else {
            $plazos = max(1, $pendientes->count());
        }
        $primera = $this->primeraFechaPago($fechaPago, (int) $prestamo->dia_pago, $periodicidad);
        $calendario = AmortizacionFrancesa::calendarioMetodo(
            $saldoRestante,
            $tasa,
            $plazos,
            $primera,
            $metodo,
            (int) $prestamo->seguro_centavos,
            (int) $prestamo->otros_cargos_centavos,
            $periodicidad
        );
        $numeroBase = (int) CuotaPrestamo::withoutGlobalScopes()
            ->where('prestamo_id', $prestamo->id)->max('numero');
        foreach ($calendario as $fila) {
            CuotaPrestamo::withoutGlobalScopes()->create([
                'usuario_id' => $prestamo->usuario_id,
                'prestamo_id' => $prestamo->id,
                'numero' => $numeroBase + $fila['numero'],
                'fecha_vencimiento' => $fila['fecha'],
                'capital_centavos' => $fila['capital'],
                'interes_centavos' => $fila['interes'],
                'saldo_capital_centavos' => $fila['saldo'],
                'seguro_centavos' => $fila['seguro'],
                'otros_cargos_centavos' => $fila['otros'],
                'total_centavos' => $fila['total'],
            ]);
        }
        $prestamo->update([
            'plazo_meses' => $numeroBase + count($calendario),
            'fecha_vencimiento' => end($calendario)['fecha'],
        ]);
    }

    private function siguienteCodigoPasivo(int $usuarioId): string
    {
        $ultimo = CuentaContable::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('codigo', 'like', '21%')
            ->where('codigo', '<>', '2100')
            ->orderByDesc('codigo')
            ->value('codigo');
        $n = $ultimo ? ((int) substr((string) $ultimo, 2)) + 1 : 1001;

        return '21'.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    private function primeraFechaPago(Carbon $desembolso, int $diaPago, string $periodicidad = 'mensual'): Carbon
    {
        return match ($periodicidad) {
            'semanal' => $desembolso->copy()->addWeek(),
            'quincenal' => $desembolso->copy()->addDays(15),
            'anual' => $desembolso->copy()->addYear()->day(min($diaPago, $desembolso->copy()->addYear()->daysInMonth)),
            default => tap($desembolso->copy()->startOfMonth()->addMonth(), function (Carbon $base) use ($diaPago): void {
                $base->day(min($diaPago, $base->daysInMonth));
            }),
        };
    }
}
