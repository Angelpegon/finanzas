<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\MovimientoLibro;
use Illuminate\Support\Facades\DB;

class ContabilizacionService
{
    /**
     * @param list<array{cuenta_contable_id:int,debe_centavos:int,haber_centavos:int}> $movimientos
     */
    public function postear(
        int $usuarioId,
        string $fecha,
        string $descripcion,
        string $origenTipo,
        int $origenId,
        array $movimientos
    ): Asiento {
        if ($movimientos === []) {
            throw new \InvalidArgumentException('Un asiento debe tener movimientos.');
        }

        $debe = array_sum(array_column($movimientos, 'debe_centavos'));
        $haber = array_sum(array_column($movimientos, 'haber_centavos'));
        if ($debe <= 0 || $debe !== $haber) {
            throw new \InvalidArgumentException('El asiento debe cuadrar y tener un débito positivo.');
        }

        return DB::transaction(function () use ($usuarioId, $fecha, $descripcion, $origenTipo, $origenId, $movimientos): Asiento {
            if (Asiento::withoutGlobalScopes()->where('usuario_id', $usuarioId)
                ->where('origen_tipo', $origenTipo)->where('origen_id', $origenId)->exists()) {
                throw new \InvalidArgumentException('El hecho financiero ya tiene un asiento contabilizado.');
            }
            $ids = array_column($movimientos, 'cuenta_contable_id');
            $validos = \App\Models\CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->whereIn('id', $ids)->count();
            if ($validos !== count(array_unique($ids))) {
                throw new \InvalidArgumentException('Una cuenta contable no pertenece al usuario.');
            }

            $asiento = Asiento::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'origen_tipo' => $origenTipo,
                'origen_id' => $origenId,
                'hash_integridad' => hash('sha256', json_encode($movimientos, JSON_THROW_ON_ERROR)),
            ]);

            foreach ($movimientos as $movimiento) {
                if ($movimiento['debe_centavos'] < 0 || $movimiento['haber_centavos'] < 0
                    || ($movimiento['debe_centavos'] > 0 && $movimiento['haber_centavos'] > 0)) {
                    throw new \InvalidArgumentException('Cada movimiento debe ser débito o crédito, no ambos.');
                }
                MovimientoLibro::withoutGlobalScopes()->create($movimiento + [
                    'usuario_id' => $usuarioId,
                    'asiento_id' => $asiento->id,
                ]);
            }

            return $asiento->load('movimientos');
        });
    }
}
