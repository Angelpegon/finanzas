<?php

namespace App\Services;

use App\Models\HechoTesoreria;
use App\Models\MetaAhorro;
use Illuminate\Support\Facades\DB;

class MetaAhorroService
{
    public function crear(int $usuarioId, string $nombre, int $objetivoCentavos, int $montoActualCentavos, ?string $fechaObjetivo, int $aporteMensualCentavos = 0, ?int $cuentaLiquidaId = null, string $prioridad = 'media', string $estado = 'activa'): MetaAhorro
    {
        if ($objetivoCentavos <= 0 || $montoActualCentavos < 0 || $montoActualCentavos > $objetivoCentavos || $aporteMensualCentavos < 0) {
            throw new \InvalidArgumentException('La meta debe tener valores válidos.');
        }
        return MetaAhorro::withoutGlobalScopes()->create([
            'usuario_id' => $usuarioId, 'objetivo_centavos' => $objetivoCentavos, 'monto_actual_centavos' => $montoActualCentavos,
            'fecha_objetivo' => $fechaObjetivo, 'aporte_mensual_centavos' => $aporteMensualCentavos,
            'cuenta_liquida_id' => $cuentaLiquidaId, 'prioridad' => $prioridad, 'estado' => $estado,
            'nombre' => $nombre,
        ]);
    }

    public function avanceCentavos(int $usuarioId, int $metaId): int
    {
        return (int) HechoTesoreria::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->where('meta_ahorro_id', $metaId)
            ->where('tipo', 'aporte_meta')->sum('monto_centavos');
    }
}
