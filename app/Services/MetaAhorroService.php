<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\MetaAhorro;
use Illuminate\Support\Facades\DB;

class MetaAhorroService
{
    public function __construct(private readonly TesoreriaService $tesoreria) {}

    public function crear(
        int $usuarioId,
        string $nombre,
        int $objetivoCentavos,
        ?string $fechaObjetivo,
        int $aporteMensualCentavos = 0,
        ?int $cuentaLiquidaId = null,
        string $prioridad = 'media',
        string $estado = 'activa'
    ): MetaAhorro {
        if ($objetivoCentavos <= 0 || $aporteMensualCentavos < 0) {
            throw new \InvalidArgumentException('La meta debe tener valores válidos.');
        }
        if ($cuentaLiquidaId !== null) {
            CuentaLiquida::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->where('activa', true)->findOrFail($cuentaLiquidaId);
        }

        return MetaAhorro::withoutGlobalScopes()->create([
            'usuario_id' => $usuarioId,
            'objetivo_centavos' => $objetivoCentavos,
            'monto_actual_centavos' => 0,
            'fecha_objetivo' => $fechaObjetivo,
            'aporte_mensual_centavos' => $aporteMensualCentavos,
            'cuenta_liquida_id' => $cuentaLiquidaId,
            'prioridad' => $prioridad,
            'estado' => $estado,
            'nombre' => $nombre,
        ]);
    }

    /**
     * El avance de la meta solo crece con aportes contabilizados (transferencia al bolsillo de la meta).
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

        return DB::transaction(function () use ($usuarioId, $metaId, $montoCentavos, $cuentaOrigenId, $fecha, $descripcion): HechoTesoreria {
            $meta = MetaAhorro::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($metaId);
            if ($meta->estado === 'cancelada') {
                throw new \InvalidArgumentException('No se puede aportar a una meta cancelada.');
            }
            if (! $meta->cuenta_liquida_id) {
                throw new \InvalidArgumentException('La meta necesita una cuenta destino (bolsillo) para aportes confiables.');
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

            $avance = $this->avanceCentavos($usuarioId, $meta->id);
            $meta->forceFill([
                'monto_actual_centavos' => $avance,
                'estado' => $avance >= (int) $meta->objetivo_centavos ? 'cumplida' : $meta->estado,
            ])->save();

            return $hecho;
        });
    }

    public function avanceCentavos(int $usuarioId, int $metaId): int
    {
        return (int) HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('meta_ahorro_id', $metaId)
            ->where('tipo', TipoHechoTesoreria::AporteMeta->value)
            ->sum('monto_centavos');
    }

    public function sincronizarProgreso(int $usuarioId, int $metaId): MetaAhorro
    {
        $meta = MetaAhorro::withoutGlobalScopes()->where('usuario_id', $usuarioId)->findOrFail($metaId);
        $avance = $this->avanceCentavos($usuarioId, $metaId);
        $meta->forceFill([
            'monto_actual_centavos' => $avance,
            'estado' => $avance >= (int) $meta->objetivo_centavos && $meta->estado !== 'cancelada'
                ? 'cumplida'
                : $meta->estado,
        ])->save();

        return $meta->fresh();
    }
}
