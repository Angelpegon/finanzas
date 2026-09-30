<?php

namespace App\Services;

use App\Models\CicloFacturacion;
use App\Models\CompraTarjeta;
use App\Models\CuentaContable;
use App\Models\CuotaTarjeta;
use App\Models\Pago;
use App\Models\TarjetaCredito;
use App\Support\CuentasOperativas;
use App\Models\CuentaLiquida;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Extracto colombiano: un solo pago por corte.
 * Diferido entra solo con la cuota del mes. Corriente conserva gracia si el
 * ciclo anterior se pagó por el total; si no, el saldo rota con interés
 * desde la compra. El avance no tiene gracia. Mora solo si el mínimo anterior
 * no se cubrió a la fecha límite.
 */
class ExtractoTarjetaService
{
    public function __construct(private readonly ContabilizacionService $contabilizacion) {}

    public function cerrarExtractosVencidos(int $usuarioId, ?Carbon $hasta = null): void
    {
        $hasta ??= now();
        if ($hasta->greaterThan(now())) {
            $hasta = now();
        }
        $tarjetas = TarjetaCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->get();

        foreach ($tarjetas as $tarjeta) {
            $this->cerrarTarjeta($tarjeta, $hasta);
        }
    }

    public function vencimientoCuota(TarjetaCredito $tarjeta, Carbon $compra, int $numero): Carbon
    {
        $corte = $this->corteQueCubre($tarjeta, $compra);
        $pago = $this->fechaLimite($tarjeta, $corte);

        return $pago->addMonthsNoOverflow(max(0, $numero - 1));
    }

    /**
     * @param  ?int  $cuotaIgnorada  Compatibilidad: el extracto no se paga por cuota.
     */
    public function registrarPago(
        int $usuarioId,
        int $tarjetaId,
        int $cuentaLiquidaId,
        ?int $cuotaIgnorada,
        string $fecha,
        ?int $montoCentavos = null
    ): Pago {
        unset($cuotaIgnorada);

        return DB::transaction(function () use ($usuarioId, $tarjetaId, $cuentaLiquidaId, $fecha, $montoCentavos): Pago {
            $tarjeta = TarjetaCredito::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($tarjetaId);

            if (! $tarjeta->activa) {
                throw new \InvalidArgumentException('La tarjeta no está activa.');
            }

            $this->cerrarTarjeta($tarjeta, Carbon::parse($fecha)->greaterThan(now()) ? Carbon::parse($fecha) : now());
            $ciclo = $this->cicloAbiertoBloqueado($usuarioId, (int) $tarjeta->id);
            if (! $ciclo) {
                throw new \InvalidArgumentException('Todavía no hay extracto. Se arma el día de corte.');
            }

            $restante = $ciclo->restanteCentavos();
            if ($restante <= 0) {
                throw new \InvalidArgumentException('El extracto de esta tarjeta ya está pagado.');
            }

            $minimoRestante = $ciclo->minimoRestanteCentavos();
            $pagar = $montoCentavos ?? ($minimoRestante > 0 ? $minimoRestante : $restante);
            if ($pagar <= 0) {
                throw new \InvalidArgumentException('El monto del pago debe ser positivo.');
            }
            if ($pagar > $restante) {
                throw new \InvalidArgumentException('El pago supera lo que pide el extracto. El saldo diferido de meses futuros no se adelanta aquí.');
            }

            $liquida = $this->cuentaOperativaBloqueada($usuarioId, $cuentaLiquidaId);
            if ($liquida->saldoCentavos() < $pagar) {
                throw new \InvalidArgumentException('Saldo insuficiente en la cuenta de pago.');
            }

            $cubetas = $this->aplicarCubetas($ciclo, $pagar);
            $cuotas = $this->cuotasDelCiclo($usuarioId, (int) $tarjeta->id, $ciclo);
            $snapshot = $cuotas->map(fn (CuotaTarjeta $c) => [
                'id' => (int) $c->id,
                'abonado_centavos' => (int) $c->abonado_centavos,
                'pagada' => (bool) $c->pagada,
            ])->values()->all();

            $this->abonarCuotas(
                $cuotas->filter(fn (CuotaTarjeta $c) => $this->esExigida($c))->values(),
                $cubetas['aplicado_interes_diferido'],
                $cubetas['aplicado_capital_diferido']
            );
            $this->abonarCuotas(
                $cuotas->filter(fn (CuotaTarjeta $c) => $this->esRotativa($c))->values(),
                0,
                $cubetas['aplicado_capital_rotativo']
            );

            $nuevoPagado = (int) $ciclo->pagado_centavos + $pagar;
            $ciclo->forceFill([
                'pagado_centavos' => $nuevoPagado,
                'estado' => $nuevoPagado >= (int) $ciclo->pago_total_centavos ? 'liquidado' : 'abierto',
            ])->save();

            $interesPago = $cubetas['aplicado_mora']
                + $cubetas['aplicado_interes_rotativo']
                + $cubetas['aplicado_interes_diferido']
                + $cubetas['aplicado_cargos'];
            $capitalPago = $cubetas['aplicado_capital_diferido'] + $cubetas['aplicado_capital_rotativo'];

            $pago = Pago::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'tipo' => 'tarjeta',
                'tarjeta_credito_id' => $tarjeta->id,
                'ciclo_facturacion_id' => $ciclo->id,
                'cuenta_liquida_id' => $liquida->id,
                'fecha' => $fecha,
                'monto_centavos' => $pagar,
                'capital_centavos' => $capitalPago,
                'interes_centavos' => $interesPago,
                'extraordinario' => $nuevoPagado > (int) $ciclo->pago_minimo_centavos,
                'cronograma_snapshot' => [
                    'ciclo_id' => (int) $ciclo->id,
                    'pagado_antes' => $nuevoPagado - $pagar,
                    'cuotas' => $snapshot,
                ],
                'descripcion' => 'Pago de extracto '.$ciclo->fecha_corte?->format('d/m/Y'),
            ]);

            $this->contabilizacion->postear(
                $usuarioId,
                $fecha,
                'Pago de tarjeta '.$tarjeta->nombre,
                Pago::class,
                (int) $pago->id,
                [[
                    'cuenta_contable_id' => (int) $tarjeta->cuenta_contable_id,
                    'debe_centavos' => $pagar,
                    'haber_centavos' => 0,
                ], [
                    'cuenta_contable_id' => (int) $liquida->cuenta_contable_id,
                    'debe_centavos' => 0,
                    'haber_centavos' => $pagar,
                ]]
            );

            return $pago;
        });
    }

    public function corregirPago(int $usuarioId, int $pagoId, string $fecha, string $motivo): void
    {
        DB::transaction(function () use ($usuarioId, $pagoId, $fecha, $motivo): void {
            $pago = Pago::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tipo', 'tarjeta')
                ->lockForUpdate()
                ->findOrFail($pagoId);

            if ($pago->ciclo_facturacion_id === null) {
                throw new \InvalidArgumentException('Este pago no está vinculado a un extracto.');
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

            $posterior = CicloFacturacion::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $pago->tarjeta_credito_id)
                ->where('id', '>', (int) $pago->ciclo_facturacion_id)
                ->exists();
            if ($posterior) {
                throw new \InvalidArgumentException('Ya hubo un corte posterior. Ese pago no se puede reabrir.');
            }

            $asiento = \App\Models\Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('origen_tipo', Pago::class)
                ->where('origen_id', $pago->id)
                ->where('es_reverso', false)
                ->firstOrFail();
            if (\App\Models\Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('asiento_reversado_id', $asiento->id)
                ->exists()) {
                throw new \InvalidArgumentException('Este pago ya fue corregido.');
            }

            $this->contabilizacion->revertir($usuarioId, (int) $asiento->id, $fecha, $motivo);

            $snap = $pago->cronograma_snapshot ?? [];
            foreach ($snap['cuotas'] ?? [] as $fila) {
                CuotaTarjeta::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)
                    ->whereKey((int) $fila['id'])
                    ->update([
                        'abonado_centavos' => (int) $fila['abonado_centavos'],
                        'pagada' => (bool) $fila['pagada'],
                        'pagada_en' => null,
                    ]);
            }

            $ciclo = CicloFacturacion::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereKey((int) $pago->ciclo_facturacion_id)
                ->lockForUpdate()
                ->firstOrFail();
            $pagado = max(0, (int) ($snap['pagado_antes'] ?? ((int) $ciclo->pagado_centavos - (int) $pago->monto_centavos)));
            $ciclo->forceFill([
                'pagado_centavos' => $pagado,
                'estado' => $pagado >= (int) $ciclo->pago_total_centavos ? 'liquidado' : 'abierto',
            ])->save();
        });
    }

    public function compromiso(int $usuarioId, \DateTimeInterface $inicio, \DateTimeInterface $fin, bool $incluirAnteriores): int
    {
        $inicio = Carbon::instance($inicio);
        $fin = Carbon::instance($fin);
        $this->cerrarExtractosVencidos($usuarioId, now());
        $total = 0;
        $tarjetas = TarjetaCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->get();

        foreach ($tarjetas as $tarjeta) {
            $abierto = $this->cicloAbierto($usuarioId, (int) $tarjeta->id);
            $desde = $inicio->copy()->startOfDay();
            if ($abierto) {
                $limite = $abierto->fecha_pago->copy()->startOfDay();
                $cuenta = $limite->lte($fin->copy()->startOfDay())
                    && ($incluirAnteriores || $limite->gte($inicio->copy()->startOfDay()));
                if ($cuenta) {
                    $total += $abierto->restanteCentavos();
                }
                if ($limite->gte($desde)) {
                    $desde = $limite->copy()->addDay();
                }
            }

            if ($desde->gt($fin)) {
                continue;
            }

            $futuras = CuotaTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $tarjeta->id)
                ->where('pagada', false)
                ->whereNull('ciclo_facturacion_id')
                ->whereDate('fecha_vencimiento', '>=', $desde->toDateString())
                ->whereDate('fecha_vencimiento', '<=', $fin->toDateString())
                ->get();
            $total += (int) $futuras->sum(fn (CuotaTarjeta $c) => (int) $c->capital_centavos + (int) $c->interes_centavos);

            $manejo = (int) $tarjeta->cuota_manejo_centavos;
            if ($manejo > 0) {
                $total += $manejo * $this->cortesEnRango($tarjeta, $desde, $fin);
            }
        }

        return $total;
    }

    /**
     * @return list<array{fecha: string, monto_centavos: int, descripcion: string}>
     */
    public function eventos(int $usuarioId, \DateTimeInterface $inicio, \DateTimeInterface $fin): array
    {
        $inicio = Carbon::instance($inicio);
        $fin = Carbon::instance($fin);
        $this->cerrarExtractosVencidos($usuarioId, now());
        $eventos = [];
        $tarjetas = TarjetaCredito::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('activa', true)
            ->get();

        foreach ($tarjetas as $tarjeta) {
            $abierto = $this->cicloAbierto($usuarioId, (int) $tarjeta->id);
            if ($abierto && $abierto->fecha_pago->betweenIncluded($inicio, $fin) && $abierto->restanteCentavos() > 0) {
                $eventos[] = [
                    'fecha' => $abierto->fecha_pago->toDateString(),
                    'monto_centavos' => $abierto->restanteCentavos(),
                    'descripcion' => 'Extracto '.$tarjeta->nombre,
                ];
            }

            $desde = $abierto ? $abierto->fecha_pago->copy()->addDay() : $inicio->copy();
            if ($desde->lt($inicio)) {
                $desde = $inicio->copy();
            }
            $futuras = CuotaTarjeta::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('tarjeta_credito_id', $tarjeta->id)
                ->where('pagada', false)
                ->whereNull('ciclo_facturacion_id')
                ->whereDate('fecha_vencimiento', '>=', $desde->toDateString())
                ->whereDate('fecha_vencimiento', '<=', $fin->toDateString())
                ->get()
                ->groupBy(fn (CuotaTarjeta $c) => $c->fecha_vencimiento->toDateString());

            foreach ($futuras as $fecha => $grupo) {
                $monto = (int) $grupo->sum(fn (CuotaTarjeta $c) => (int) $c->capital_centavos + (int) $c->interes_centavos);
                if ($monto > 0) {
                    $eventos[] = [
                        'fecha' => (string) $fecha,
                        'monto_centavos' => $monto,
                        'descripcion' => 'Extracto '.$tarjeta->nombre,
                    ];
                }
            }

            $manejo = (int) $tarjeta->cuota_manejo_centavos;
            if ($manejo > 0) {
                foreach ($this->fechasCorteEnRango($tarjeta, $desde, $fin) as $fechaManejo) {
                    $eventos[] = [
                        'fecha' => $fechaManejo,
                        'monto_centavos' => $manejo,
                        'descripcion' => 'Cuota de manejo '.$tarjeta->nombre,
                    ];
                }
            }
        }

        return $eventos;
    }

    public function cerrarTarjeta(TarjetaCredito $tarjeta, Carbon $hasta): void
    {
        DB::transaction(function () use ($tarjeta, $hasta): void {
            $bloqueada = TarjetaCredito::withoutGlobalScopes()
                ->whereKey($tarjeta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $limite = $hasta->copy()->startOfDay();
            $mes = $this->primerCorte($bloqueada)->copy()->startOfMonth();
            $ultimo = $limite->copy()->addMonth()->startOfMonth();

            while ($mes->lte($ultimo)) {
                $corte = $this->diaEnMes($mes, (int) $bloqueada->dia_corte);
                if ($corte->lte($limite)) {
                    $ya = CicloFacturacion::withoutGlobalScopes()
                        ->where('tarjeta_credito_id', $bloqueada->id)
                        ->whereDate('fecha_corte', $corte->toDateString())
                        ->exists();
                    if (! $ya) {
                        $this->abrirCiclo($bloqueada, $corte);
                    }
                }
                $mes->addMonth();
            }
        });
    }

    private function abrirCiclo(TarjetaCredito $tarjeta, Carbon $corte): void
    {
        $corte = $corte->copy()->startOfDay();
        $fechaPago = $this->fechaLimite($tarjeta, $corte);
        $prev = CicloFacturacion::withoutGlobalScopes()
            ->where('tarjeta_credito_id', $tarjeta->id)
            ->orderByDesc('fecha_corte')
            ->orderByDesc('id')
            ->first();

        $gracia = $prev === null || (int) $prev->pagado_centavos >= (int) $prev->pago_total_centavos;
        $impagos = $prev ? $this->impagos($prev) : [
            'interes_rotativo' => 0,
            'interes_mora' => 0,
            'cargos' => 0,
        ];

        $cuotas = $this->cuotasVivas((int) $tarjeta->usuario_id, (int) $tarjeta->id);
        $prevCorte = $prev?->fecha_corte?->copy()->startOfDay();
        $exigidas = collect();
        $rotativas = collect();

        foreach ($cuotas as $cuota) {
            $compra = $cuota->compra;
            if (! $compra || $compra->anulada) {
                continue;
            }
            $vence = $cuota->fecha_vencimiento->copy()->startOfDay();
            if ($this->esExigida($cuota)) {
                if ($vence->lte($fechaPago) && $this->faltaTotal($cuota) > 0) {
                    $exigidas->push($cuota);
                }
            } elseif ($this->esRotativa($cuota)) {
                $entra = $cuota->ciclo_facturacion_id || $this->enVentana($compra->fecha, $prevCorte, $corte);
                if ($entra && $vence->lte($fechaPago) && $this->faltaCapital($cuota) > 0) {
                    $rotativas->push($cuota);
                }
            }
        }

        $tasaCompras = (float) $tarjeta->tasa_compras_mensual;
        $tasaAvances = (float) $tarjeta->tasa_avances_mensual;
        $tasaMora = (float) $tarjeta->tasa_mora_mensual > 0
            ? (float) $tarjeta->tasa_mora_mensual
            : $tasaCompras;

        $interesRotativoNuevo = 0;
        $yaHuboRotativo = $prev && (int) $prev->interes_rotativo_centavos > 0;
        foreach ($rotativas as $cuota) {
            if ($gracia) {
                continue;
            }
            $desde = ($cuota->ciclo_facturacion_id && $yaHuboRotativo)
                ? ($prevCorte ?? $cuota->compra->fecha->copy())
                : $cuota->compra->fecha->copy();
            $interesRotativoNuevo += $this->interesDiario($this->faltaCapital($cuota), $tasaCompras, $desde, $corte);
        }
        foreach ($exigidas as $cuota) {
            if (! $this->esAvanceUna($cuota)) {
                continue;
            }
            $desde = $cuota->ciclo_facturacion_id
                ? ($prevCorte ?? $cuota->compra->fecha->copy())
                : $cuota->compra->fecha->copy();
            $interesRotativoNuevo += $this->interesDiario($this->faltaCapital($cuota), $tasaAvances, $desde, $corte);
        }

        $moraNueva = 0;
        if ($prev && (int) $prev->pagado_centavos < (int) $prev->pago_minimo_centavos && $corte->gt($prev->fecha_pago)) {
            $base = (int) $prev->pago_minimo_centavos - (int) $prev->pagado_centavos;
            $moraNueva = $this->interesDiario($base, $tasaMora, $prev->fecha_pago, $corte);
        }

        $interesDiferidoNuevo = (int) $exigidas
            ->filter(fn (CuotaTarjeta $c) => $c->ciclo_facturacion_id === null && ! $this->esAvanceUna($c))
            ->sum(fn (CuotaTarjeta $c) => (int) $c->interes_centavos);

        $capitalDiferido = (int) $exigidas->sum(fn (CuotaTarjeta $c) => $this->faltaCapital($c));
        $interesDiferido = (int) $exigidas->sum(fn (CuotaTarjeta $c) => $this->faltaInteres($c));
        $capitalRotativo = (int) $rotativas->sum(fn (CuotaTarjeta $c) => $this->faltaCapital($c));
        $manejo = max(0, (int) $tarjeta->cuota_manejo_centavos);
        $pct = max(0.0, (float) $tarjeta->porcentaje_abono_capital_minimo);
        $abono = (int) round($capitalRotativo * $pct / 100);
        $abono = min($capitalRotativo, max(0, $abono));

        $interesRotativo = $impagos['interes_rotativo'] + $interesRotativoNuevo;
        $interesMora = $impagos['interes_mora'] + $moraNueva;
        $cargos = $impagos['cargos'] + $manejo;

        $pagoTotal = $interesMora + $interesRotativo + $interesDiferido + $cargos + $capitalDiferido + $capitalRotativo;
        $pagoMinimo = $interesMora + $interesRotativo + $interesDiferido + $cargos + $capitalDiferido + $abono;
        $causado = $interesRotativoNuevo + $moraNueva + $manejo + $interesDiferidoNuevo;

        $estado = $pagoTotal === 0 ? 'liquidado' : 'abierto';

        if ($prev && $prev->estado === 'abierto') {
            $prev->forceFill([
                'estado' => (int) $prev->pagado_centavos >= (int) $prev->pago_total_centavos ? 'liquidado' : 'arrastrado',
            ])->save();
        }

        $ciclo = CicloFacturacion::withoutGlobalScopes()->create([
            'usuario_id' => $tarjeta->usuario_id,
            'tarjeta_credito_id' => $tarjeta->id,
            'fecha_corte' => $corte->toDateString(),
            'fecha_pago' => $fechaPago->toDateString(),
            'saldo_corte_centavos' => $pagoTotal,
            'pago_minimo_centavos' => $pagoMinimo,
            'pago_total_centavos' => $pagoTotal,
            'pagado_centavos' => 0,
            'interes_centavos' => $interesMora + $interesRotativo + $interesDiferido,
            'capital_rotativo_centavos' => $capitalRotativo,
            'capital_diferido_centavos' => $capitalDiferido,
            'interes_rotativo_centavos' => $interesRotativo,
            'interes_diferido_centavos' => $interesDiferido,
            'interes_mora_centavos' => $interesMora,
            'cargos_centavos' => $cargos,
            'causado_centavos' => $causado,
            'estado' => $estado,
        ]);

        foreach ($exigidas->concat($rotativas) as $cuota) {
            if ($cuota->ciclo_facturacion_id === null) {
                $cuota->forceFill(['ciclo_facturacion_id' => $ciclo->id])->save();
            }
        }

        if ($causado > 0) {
            $gastoId = (int) CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $tarjeta->usuario_id)
                ->where('codigo', '5200')
                ->firstOrFail()
                ->id;
            $this->contabilizacion->postear(
                (int) $tarjeta->usuario_id,
                $corte->toDateString(),
                'Extracto '.$tarjeta->nombre.' corte '.$corte->format('d/m/Y'),
                CicloFacturacion::class,
                (int) $ciclo->id,
                [[
                    'cuenta_contable_id' => $gastoId,
                    'debe_centavos' => $causado,
                    'haber_centavos' => 0,
                ], [
                    'cuenta_contable_id' => (int) $tarjeta->cuenta_contable_id,
                    'debe_centavos' => 0,
                    'haber_centavos' => $causado,
                ]]
            );
        }
    }

    /**
     * @return array{aplicado_mora:int,aplicado_interes_rotativo:int,aplicado_interes_diferido:int,aplicado_cargos:int,aplicado_capital_diferido:int,aplicado_capital_rotativo:int}
     */
    private function aplicarCubetas(CicloFacturacion $ciclo, int $monto): array
    {
        $ya = $this->impagosSeparados($ciclo);
        $orden = [
            'aplicado_mora' => (int) $ciclo->interes_mora_centavos,
            'aplicado_interes_rotativo' => (int) $ciclo->interes_rotativo_centavos,
            'aplicado_interes_diferido' => (int) $ciclo->interes_diferido_centavos,
            'aplicado_cargos' => (int) $ciclo->cargos_centavos,
            'aplicado_capital_diferido' => (int) $ciclo->capital_diferido_centavos,
            'aplicado_capital_rotativo' => (int) $ciclo->capital_rotativo_centavos,
        ];
        $consumido = [
            'interes_mora' => (int) $ciclo->interes_mora_centavos - $ya['interes_mora'],
            'interes_rotativo' => (int) $ciclo->interes_rotativo_centavos - $ya['interes_rotativo'],
            'interes_diferido' => (int) $ciclo->interes_diferido_centavos - $ya['interes_diferido'],
            'cargos' => (int) $ciclo->cargos_centavos - $ya['cargos'],
            'capital_diferido' => (int) $ciclo->capital_diferido_centavos - $ya['capital_diferido'],
            'capital_rotativo' => (int) $ciclo->capital_rotativo_centavos - $ya['capital_rotativo'],
        ];
        $claves = array_keys($orden);
        $nombres = array_keys($consumido);
        $out = array_fill_keys($claves, 0);
        $bolsa = $monto;
        foreach ($nombres as $i => $nombre) {
            $libre = max(0, $orden[$claves[$i]] - $consumido[$nombre]);
            $usa = min($bolsa, $libre);
            $out[$claves[$i]] = $usa;
            $bolsa -= $usa;
        }

        return $out;
    }

    /**
     * @return array{interes_rotativo:int,interes_mora:int,cargos:int,interes_diferido:int,capital_diferido:int,capital_rotativo:int}
     */
    private function impagosSeparados(CicloFacturacion $ciclo): array
    {
        $bolsa = (int) $ciclo->pagado_centavos;
        $tomar = function (int $monto) use (&$bolsa): int {
            $usa = min($bolsa, max(0, $monto));
            $bolsa -= $usa;

            return max(0, $monto - $usa);
        };

        return [
            'interes_mora' => $tomar((int) $ciclo->interes_mora_centavos),
            'interes_rotativo' => $tomar((int) $ciclo->interes_rotativo_centavos),
            'interes_diferido' => $tomar((int) $ciclo->interes_diferido_centavos),
            'cargos' => $tomar((int) $ciclo->cargos_centavos),
            'capital_diferido' => $tomar((int) $ciclo->capital_diferido_centavos),
            'capital_rotativo' => $tomar((int) $ciclo->capital_rotativo_centavos),
        ];
    }

    /**
     * @return array{interes_rotativo:int,interes_mora:int,cargos:int}
     */
    private function impagos(CicloFacturacion $ciclo): array
    {
        $todo = $this->impagosSeparados($ciclo);

        return [
            'interes_rotativo' => $todo['interes_rotativo'],
            'interes_mora' => $todo['interes_mora'],
            'cargos' => $todo['cargos'],
        ];
    }

    /**
     * @param  Collection<int, CuotaTarjeta>  $cuotas
     */
    private function abonarCuotas(Collection $cuotas, int $interes, int $capital): void
    {
        $ordenadas = $cuotas->sortBy([
            ['fecha_vencimiento', 'asc'],
            ['numero', 'asc'],
            ['id', 'asc'],
        ])->values();

        $bolsa = $interes;
        foreach ($ordenadas as $cuota) {
            if ($bolsa <= 0) {
                break;
            }
            $falta = $this->faltaInteres($cuota);
            if ($falta <= 0) {
                continue;
            }
            $usa = min($bolsa, $falta);
            $this->aplicarAbono($cuota, $usa);
            $bolsa -= $usa;
        }

        $bolsa = $capital;
        foreach ($ordenadas as $cuota) {
            if ($bolsa <= 0) {
                break;
            }
            $falta = $this->faltaCapital($cuota);
            if ($falta <= 0) {
                continue;
            }
            $usa = min($bolsa, $falta);
            $this->aplicarAbono($cuota, $usa);
            $bolsa -= $usa;
        }
    }

    private function aplicarAbono(CuotaTarjeta $cuota, int $usa): void
    {
        $abonado = (int) $cuota->abonado_centavos + $usa;
        $total = (int) $cuota->capital_centavos + (int) $cuota->interes_centavos;
        $cuota->forceFill([
            'abonado_centavos' => $abonado,
            'pagada' => $abonado >= $total,
            'pagada_en' => $abonado >= $total ? now() : null,
        ])->save();
    }

    /** @return Collection<int, CuotaTarjeta> */
    private function cuotasDelCiclo(int $usuarioId, int $tarjetaId, CicloFacturacion $ciclo): Collection
    {
        return $this->cuotasVivas($usuarioId, $tarjetaId)
            ->filter(function (CuotaTarjeta $cuota) use ($ciclo): bool {
                if ((int) $cuota->ciclo_facturacion_id === (int) $ciclo->id) {
                    return true;
                }

                return $cuota->ciclo_facturacion_id !== null && $this->faltaTotal($cuota) > 0;
            })
            ->values();
    }

    /** @return Collection<int, CuotaTarjeta> */
    private function cuotasVivas(int $usuarioId, int $tarjetaId): Collection
    {
        return CuotaTarjeta::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tarjeta_credito_id', $tarjetaId)
            ->where('pagada', false)
            ->with('compra')
            ->get();
    }

    private function esDiferida(CuotaTarjeta $cuota): bool
    {
        $compra = $cuota->compra;

        return $compra instanceof CompraTarjeta && (int) $compra->cuotas > 1;
    }

    private function esAvanceUna(CuotaTarjeta $cuota): bool
    {
        $compra = $cuota->compra;

        return $compra instanceof CompraTarjeta
            && $compra->tipo === 'avance'
            && (int) $compra->cuotas <= 1;
    }

    private function esExigida(CuotaTarjeta $cuota): bool
    {
        return $this->esDiferida($cuota) || $this->esAvanceUna($cuota);
    }

    private function esRotativa(CuotaTarjeta $cuota): bool
    {
        $compra = $cuota->compra;

        return $compra instanceof CompraTarjeta
            && $compra->tipo === 'compra'
            && (int) $compra->cuotas <= 1;
    }

    private function faltaInteres(CuotaTarjeta $cuota): int
    {
        return max(0, (int) $cuota->interes_centavos - (int) $cuota->abonado_centavos);
    }

    private function faltaCapital(CuotaTarjeta $cuota): int
    {
        $trasInteres = max(0, (int) $cuota->abonado_centavos - (int) $cuota->interes_centavos);

        return max(0, (int) $cuota->capital_centavos - $trasInteres);
    }

    private function faltaTotal(CuotaTarjeta $cuota): int
    {
        return max(0, (int) $cuota->capital_centavos + (int) $cuota->interes_centavos - (int) $cuota->abonado_centavos);
    }

    private function enVentana(Carbon $fecha, ?Carbon $prevCorte, Carbon $corte): bool
    {
        $dia = $fecha->copy()->startOfDay();
        if ($dia->gt($corte->copy()->startOfDay())) {
            return false;
        }

        return $prevCorte === null || $dia->gt($prevCorte->copy()->startOfDay());
    }

    private function interesDiario(int $capital, float $tasaMensual, Carbon $desde, Carbon $hasta): int
    {
        if ($capital <= 0 || $tasaMensual <= 0) {
            return 0;
        }
        $dias = $desde->copy()->startOfDay()->diffInDays($hasta->copy()->startOfDay(), false);
        if ($dias <= 0) {
            return 0;
        }

        return (int) round($capital * ($tasaMensual / 100) * $dias / 30);
    }

    private function cicloAbierto(int $usuarioId, int $tarjetaId): ?CicloFacturacion
    {
        return CicloFacturacion::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tarjeta_credito_id', $tarjetaId)
            ->where('estado', 'abierto')
            ->orderBy('fecha_corte')
            ->first();
    }

    private function cicloAbiertoBloqueado(int $usuarioId, int $tarjetaId): ?CicloFacturacion
    {
        return CicloFacturacion::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tarjeta_credito_id', $tarjetaId)
            ->where('estado', 'abierto')
            ->orderBy('fecha_corte')
            ->lockForUpdate()
            ->first();
    }

    private function cortesEnRango(TarjetaCredito $tarjeta, Carbon $desde, Carbon $fin): int
    {
        $n = 0;
        $mes = $desde->copy()->startOfMonth();
        $tope = $fin->copy()->startOfMonth();
        while ($mes->lte($tope)) {
            $corte = $this->diaEnMes($mes, (int) $tarjeta->dia_corte);
            $pago = $this->fechaLimite($tarjeta, $corte);
            if ($pago->gte($desde->copy()->startOfDay()) && $pago->lte($fin->copy()->endOfDay())) {
                $existe = CicloFacturacion::withoutGlobalScopes()
                    ->where('tarjeta_credito_id', $tarjeta->id)
                    ->whereDate('fecha_corte', $corte->toDateString())
                    ->exists();
                if (! $existe) {
                    $n++;
                }
            }
            $mes->addMonth();
        }

        return $n;
    }

    /** @return list<string> */
    private function fechasCorteEnRango(TarjetaCredito $tarjeta, Carbon $desde, Carbon $fin): array
    {
        $fechas = [];
        $mes = $desde->copy()->startOfMonth();
        $tope = $fin->copy()->startOfMonth();
        while ($mes->lte($tope)) {
            $corte = $this->diaEnMes($mes, (int) $tarjeta->dia_corte);
            $pago = $this->fechaLimite($tarjeta, $corte);
            if ($pago->gte($desde->copy()->startOfDay()) && $pago->lte($fin->copy()->endOfDay())) {
                $existe = CicloFacturacion::withoutGlobalScopes()
                    ->where('tarjeta_credito_id', $tarjeta->id)
                    ->whereDate('fecha_corte', $corte->toDateString())
                    ->exists();
                if (! $existe) {
                    $fechas[] = $pago->toDateString();
                }
            }
            $mes->addMonth();
        }

        return $fechas;
    }

    public function corteQueCubre(TarjetaCredito $tarjeta, Carbon $compra): Carbon
    {
        $dia = $compra->copy()->startOfDay();
        $corte = $this->diaEnMes($dia, (int) $tarjeta->dia_corte);
        if ($dia->gt($corte)) {
            $corte = $this->diaEnMes($dia->copy()->addMonth(), (int) $tarjeta->dia_corte);
        }

        return $corte;
    }

    public function fechaLimite(TarjetaCredito $tarjeta, Carbon $corte): Carbon
    {
        $pago = $corte->copy()->startOfDay();
        if ((int) $tarjeta->dia_pago <= (int) $tarjeta->dia_corte) {
            $pago->addMonth();
        }

        return $this->diaEnMes($pago, (int) $tarjeta->dia_pago);
    }

    private function primerCorte(TarjetaCredito $tarjeta): Carbon
    {
        $inicio = ($tarjeta->fecha_inicio ?? $tarjeta->created_at ?? now())->copy()->startOfDay();

        return $this->corteQueCubre($tarjeta, $inicio);
    }

    private function diaEnMes(Carbon $mes, int $dia): Carbon
    {
        $base = $mes->copy()->startOfMonth();

        return $base->day(min($dia, $base->daysInMonth))->startOfDay();
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
}
