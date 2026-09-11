<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\MetaAhorro;
use App\Support\CuentasOperativas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MetaAhorroService
{
    public function __construct(
        private readonly TesoreriaService $tesoreria,
        private readonly CuentaLiquidaService $cuentas,
        private readonly ContabilizacionService $contabilizacion,
    ) {}

    /**
     * $cuentaReferenciaId es la cuenta operativa de referencia: nunca se usa
     * como bolsillo. Se crea siempre `{nombre}_bolsilloN` dedicada.
     * El estado inicial es siempre `activa` (cumplida se deriva del avance).
     */
    public function crear(
        int $usuarioId,
        string $nombre,
        int $objetivoCentavos,
        ?string $fechaObjetivo,
        int $aporteMensualCentavos = 0,
        ?int $cuentaReferenciaId = null,
        string $prioridad = 'media'
    ): MetaAhorro {
        if ($objetivoCentavos <= 0 || $aporteMensualCentavos < 0) {
            throw new \InvalidArgumentException('La meta debe tener valores válidos.');
        }
        if ($cuentaReferenciaId === null) {
            throw new \InvalidArgumentException('La meta necesita una cuenta de referencia para crear su bolsillo.');
        }

        $meta = DB::transaction(function () use (
            $usuarioId,
            $nombre,
            $objetivoCentavos,
            $fechaObjetivo,
            $aporteMensualCentavos,
            $cuentaReferenciaId,
            $prioridad
        ): MetaAhorro {
            $referencia = CuentaLiquida::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('activa', true)
                ->where('estado', 'activa')
                ->findOrFail($cuentaReferenciaId);

            if ($this->esBolsilloDeMeta($usuarioId, (int) $referencia->id)) {
                throw new \InvalidArgumentException(
                    'Elige una cuenta operativa, no un bolsillo de otra meta.'
                );
            }

            $bolsillo = $this->cuentas->crear($usuarioId, [
                'nombre' => $this->nombreBolsilloUnico($usuarioId, (string) $referencia->nombre),
                'tipo' => $referencia->tipo,
                'institucion' => $referencia->institucion,
                'numero_cuenta_enmascarado' => null,
                'saldo_inicial' => 0,
            ]);

            return MetaAhorro::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'objetivo_centavos' => $objetivoCentavos,
                'monto_actual_centavos' => 0,
                'fecha_objetivo' => $fechaObjetivo,
                'aporte_mensual_centavos' => $aporteMensualCentavos,
                'cuenta_liquida_id' => $bolsillo->id,
                'prioridad' => $prioridad,
                'estado' => 'activa',
                'nombre' => $nombre,
            ]);
        });

        SituacionFinancieraService::olvidarResumenShell($usuarioId);

        return $meta;
    }

    /**
     * Actualiza planificación (no toca el libro ni el bolsillo).
     */
    public function actualizar(
        int $usuarioId,
        int $metaId,
        int $objetivoCentavos,
        ?string $fechaObjetivo,
        ?int $aporteMensualCentavos = null,
        ?string $prioridad = null,
        ?string $nombre = null
    ): MetaAhorro {
        if ($objetivoCentavos <= 0) {
            throw new \InvalidArgumentException('El objetivo debe ser positivo.');
        }
        if ($aporteMensualCentavos !== null && $aporteMensualCentavos < 0) {
            throw new \InvalidArgumentException('El aporte mensual no puede ser negativo.');
        }

        $meta = DB::transaction(function () use (
            $usuarioId, $metaId, $objetivoCentavos, $fechaObjetivo, $aporteMensualCentavos, $prioridad, $nombre
        ): MetaAhorro {
            $meta = MetaAhorro::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($metaId);

            $fill = [
                'objetivo_centavos' => $objetivoCentavos,
                'fecha_objetivo' => $fechaObjetivo,
            ];
            if ($aporteMensualCentavos !== null) {
                $fill['aporte_mensual_centavos'] = $aporteMensualCentavos;
            }
            if ($prioridad !== null) {
                $fill['prioridad'] = $prioridad;
            }
            if ($nombre !== null && trim($nombre) !== '') {
                $fill['nombre'] = trim($nombre);
            }
            $meta->forceFill($fill)->save();

            return $this->sincronizarProgreso($usuarioId, $metaId);
        });

        SituacionFinancieraService::olvidarResumenShell($usuarioId);

        return $meta;
    }

    /**
     * Aporte desde cuenta operativa → bolsillo. Solo operativas (nunca otro bolsillo).
     */
    public function aportar(
        int $usuarioId,
        int $metaId,
        int $montoCentavos,
        int $cuentaOrigenId,
        string $fecha,
        ?string $descripcion = null
    ): HechoTesoreria {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El aporte debe ser positivo.');
        }

        $hecho = DB::transaction(function () use ($usuarioId, $metaId, $montoCentavos, $cuentaOrigenId, $fecha, $descripcion): HechoTesoreria {
            $meta = MetaAhorro::withoutGlobalScopes()->where('usuario_id', $usuarioId)->lockForUpdate()->findOrFail($metaId);
            if (! $meta->cuenta_liquida_id) {
                throw new \InvalidArgumentException('La meta necesita una cuenta destino (bolsillo) para aportes confiables.');
            }
            if ($this->esBolsilloDeMeta($usuarioId, $cuentaOrigenId)) {
                throw new \InvalidArgumentException('El aporte debe salir de una cuenta operativa, no de un bolsillo de meta.');
            }
            if ((int) $meta->cuenta_liquida_id === $cuentaOrigenId) {
                throw new \InvalidArgumentException('El aporte debe salir de una cuenta distinta al bolsillo de la meta.');
            }

            $hecho = $this->tesoreria->registrar(
                $usuarioId,
                TipoHechoTesoreria::AporteMeta,
                $fecha,
                $montoCentavos,
                $cuentaOrigenId,
                null,
                (int) $meta->cuenta_liquida_id,
                $descripcion ?? ('Aporte a meta '.$meta->nombre),
                null,
                $meta->id
            );

            $this->sincronizarProgreso($usuarioId, $meta->id);

            return $hecho;
        });

        SituacionFinancieraService::olvidarResumenShell($usuarioId);

        return $hecho;
    }

    /**
     * Retiro bolsillo → cuenta operativa. Reduce el avance neto. El bolsillo
     * nunca se convierte en operativa.
     */
    public function retirar(
        int $usuarioId,
        int $metaId,
        int $montoCentavos,
        int $cuentaDestinoId,
        string $fecha,
        ?string $descripcion = null
    ): HechoTesoreria {
        if ($montoCentavos <= 0) {
            throw new \InvalidArgumentException('El retiro debe ser positivo.');
        }

        $hecho = DB::transaction(function () use ($usuarioId, $metaId, $montoCentavos, $cuentaDestinoId, $fecha, $descripcion): HechoTesoreria {
            $meta = MetaAhorro::withoutGlobalScopes()->where('usuario_id', $usuarioId)->lockForUpdate()->findOrFail($metaId);
            if (! $meta->cuenta_liquida_id) {
                throw new \InvalidArgumentException('La meta no tiene bolsillo.');
            }
            if ($this->esBolsilloDeMeta($usuarioId, $cuentaDestinoId)) {
                throw new \InvalidArgumentException('El destino del retiro debe ser una cuenta operativa.');
            }
            if ((int) $meta->cuenta_liquida_id === $cuentaDestinoId) {
                throw new \InvalidArgumentException('El destino debe ser distinto al bolsillo.');
            }

            $hecho = $this->tesoreria->registrar(
                $usuarioId,
                TipoHechoTesoreria::RetiroMeta,
                $fecha,
                $montoCentavos,
                (int) $meta->cuenta_liquida_id,
                null,
                $cuentaDestinoId,
                $descripcion ?? ('Retiro de meta '.$meta->nombre),
                null,
                $meta->id
            );

            $this->sincronizarProgreso($usuarioId, $meta->id);

            return $hecho;
        });

        SituacionFinancieraService::olvidarResumenShell($usuarioId);

        return $hecho;
    }

    /**
     * Corrige el último aporte o retiro de la meta (reverso + sync).
     */
    public function corregirMovimiento(
        int $usuarioId,
        int $hechoId,
        string $fecha,
        string $motivo = 'Corrección de movimiento de meta'
    ): void {
        DB::transaction(function () use ($usuarioId, $hechoId, $fecha, $motivo): void {
            $hecho = HechoTesoreria::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereIn('tipo', [TipoHechoTesoreria::AporteMeta, TipoHechoTesoreria::RetiroMeta])
                ->lockForUpdate()
                ->findOrFail($hechoId);

            if ($hecho->meta_ahorro_id === null) {
                throw new \InvalidArgumentException('Este hecho no está vinculado a una meta.');
            }

            $ultimoId = (int) HechoTesoreria::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->where('meta_ahorro_id', $hecho->meta_ahorro_id)
                ->whereIn('tipo', [TipoHechoTesoreria::AporteMeta, TipoHechoTesoreria::RetiroMeta])
                ->orderByDesc('id')
                ->value('id');
            if ($ultimoId !== (int) $hecho->id) {
                throw new \InvalidArgumentException('Solo se puede corregir el último aporte o retiro de la meta.');
            }

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
                throw new \InvalidArgumentException('Este movimiento ya fue corregido.');
            }

            $this->contabilizacion->revertir($usuarioId, (int) $asiento->id, $fecha, $motivo);
        });

        SituacionFinancieraService::olvidarResumenShell($usuarioId);
    }

    /**
     * Avance neto = aportes − retiros (excluye reversos).
     */
    public function avanceCentavos(int $usuarioId, int $metaId): int
    {
        $aportes = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('meta_ahorro_id', $metaId)
            ->where('tipo', TipoHechoTesoreria::AporteMeta->value);
        $retiros = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('meta_ahorro_id', $metaId)
            ->where('tipo', TipoHechoTesoreria::RetiroMeta->value);

        $sumaAportes = (int) \App\Support\AgregadosLibro::excluirOrigenesRevertidos(
            $aportes, HechoTesoreria::class, $usuarioId
        )->sum('monto_centavos');
        $sumaRetiros = (int) \App\Support\AgregadosLibro::excluirOrigenesRevertidos(
            $retiros, HechoTesoreria::class, $usuarioId
        )->sum('monto_centavos');

        return max(0, $sumaAportes - $sumaRetiros);
    }

    public function sincronizarProgreso(int $usuarioId, int $metaId): MetaAhorro
    {
        $meta = MetaAhorro::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($metaId);
        $avance = $this->avanceCentavos($usuarioId, $metaId);
        $estado = $avance >= (int) $meta->objetivo_centavos ? 'cumplida' : 'activa';
        $meta->forceFill([
            'monto_actual_centavos' => $avance,
            'estado' => $estado,
        ])->save();

        return $meta->fresh();
    }

    /**
     * Plan mensual de metas activas menos aportes netos ya hechos en el mes.
     * Si $limitarPorFaltante, no compromete más que lo que falta para el objetivo.
     */
    public function comprometidoMensualNeto(int $usuarioId, ?Carbon $fecha = null, bool $limitarPorFaltante = false): int
    {
        $fecha ??= now();
        $inicio = $fecha->copy()->startOfMonth();
        $fin = $fecha->copy()->endOfMonth();

        $metas = MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'activa')
            ->get();

        $plan = (int) $metas->sum('aporte_mensual_centavos');

        $aportes = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', TipoHechoTesoreria::AporteMeta->value)
            ->whereBetween('fecha', [$inicio, $fin]);
        $retiros = HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('tipo', TipoHechoTesoreria::RetiroMeta->value)
            ->whereBetween('fecha', [$inicio, $fin]);

        $neto = (int) \App\Support\AgregadosLibro::excluirOrigenesRevertidos($aportes, HechoTesoreria::class, $usuarioId)->sum('monto_centavos')
            - (int) \App\Support\AgregadosLibro::excluirOrigenesRevertidos($retiros, HechoTesoreria::class, $usuarioId)->sum('monto_centavos');

        $comprometido = max(0, $plan - max(0, $neto));
        if (! $limitarPorFaltante) {
            return $comprometido;
        }

        $faltante = (int) $metas->sum(
            fn (MetaAhorro $m): int => max(0, (int) $m->objetivo_centavos - (int) $m->monto_actual_centavos)
        );

        return min($comprometido, $faltante);
    }

    /**
     * @return list<int>
     */
    public function idsBolsillos(int $usuarioId): array
    {
        return CuentasOperativas::idsBolsillosMetas($usuarioId);
    }

    private function esBolsilloDeMeta(int $usuarioId, int $cuentaId): bool
    {
        return in_array($cuentaId, $this->idsBolsillos($usuarioId), true);
    }

    private function nombreBolsilloUnico(int $usuarioId, string $nombreReferencia): string
    {
        $base = trim($nombreReferencia);
        if ($base === '') {
            $base = 'Cuenta';
        }
        $prefijo = $base.'_bolsillo';
        $n = 1;
        while (CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('nombre', $prefijo.$n)
            ->exists()) {
            $n++;
        }

        return $prefijo.$n;
    }
}
