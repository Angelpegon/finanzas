<?php

namespace App\Services;

use App\Models\Recurrencia;

class RecurrenciaService
{
    public function crear(
        int $usuarioId,
        string $tipo,
        string $nombre,
        int $montoCentavos,
        int $diaDelMes,
        string $periodicidad = 'mensual',
        ?string $tipoGasto = null,
        ?int $categoriaId = null,
        ?int $cuentaLiquidaId = null
    ): Recurrencia {
        if (! in_array($tipo, ['ingreso', 'gasto'], true)
            || ! in_array($periodicidad, ['unico', 'diario', 'semanal', 'quincenal', 'mensual', 'anual'], true)
            || $montoCentavos <= 0 || $diaDelMes < 1 || $diaDelMes > 31) {
            throw new \InvalidArgumentException('La recurrencia no es válida.');
        }
        return Recurrencia::withoutGlobalScopes()->create([
            'usuario_id' => $usuarioId, 'tipo' => $tipo, 'periodicidad' => $periodicidad, 'tipo_gasto' => $tipoGasto, 'nombre' => $nombre,
            'monto_centavos' => $montoCentavos, 'dia_del_mes' => $diaDelMes,
            'categoria_id' => $categoriaId, 'cuenta_liquida_id' => $cuentaLiquidaId,
        ]);
    }
}
