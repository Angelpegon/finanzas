<?php

namespace App\Services;

use App\Enums\SeguridadAccion;
use App\Models\Asiento;
use App\Models\MovimientoLibro;
use Illuminate\Support\Facades\DB;

class ContabilizacionService
{
    public function __construct(private readonly SeguridadService $seguridad) {}

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

        $movimientos = $this->normalizarMovimientos($movimientos);
        $debe = array_sum(array_column($movimientos, 'debe_centavos'));
        $haber = array_sum(array_column($movimientos, 'haber_centavos'));
        if ($debe <= 0 || $debe !== $haber) {
            throw new \InvalidArgumentException('El asiento debe cuadrar y tener un débito positivo.');
        }

        return DB::transaction(function () use ($usuarioId, $fecha, $descripcion, $origenTipo, $origenId, $movimientos): Asiento {
            if (Asiento::withoutGlobalScopes()->where('usuario_id', $usuarioId)
                ->where('origen_tipo', $origenTipo)->where('origen_id', $origenId)
                ->where('es_reverso', false)->exists()) {
                throw new \InvalidArgumentException('El hecho financiero ya tiene un asiento contabilizado.');
            }
            $this->assertCuentasDelUsuario($usuarioId, $movimientos);

            $asiento = Asiento::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'origen_tipo' => $origenTipo,
                'origen_id' => $origenId,
                'es_reverso' => false,
                'hash_integridad' => $this->hashMovimientos($movimientos),
            ]);
            $this->persistirMovimientos($usuarioId, $asiento->id, $movimientos);

            SituacionFinancieraService::olvidarResumenShell($usuarioId);

            return $asiento->load('movimientos');
        });
    }

    /**
     * Corrección append-only: asiento inverso que anula saldos del original.
     * Nunca actualiza montos del asiento original.
     */
    public function revertir(int $usuarioId, int $asientoId, string $fecha, string $motivo): Asiento
    {
        return DB::transaction(function () use ($usuarioId, $asientoId, $fecha, $motivo): Asiento {
            $original = Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->with('movimientos')
                ->findOrFail($asientoId);

            if ($original->es_reverso) {
                throw new \InvalidArgumentException('No se puede revertir un asiento que ya es reverso.');
            }
            if (Asiento::withoutGlobalScopes()->where('usuario_id', $usuarioId)
                ->where('asiento_reversado_id', $original->id)->exists()) {
                throw new \InvalidArgumentException('El asiento ya fue revertido.');
            }

            $movimientos = $original->movimientos->map(fn (MovimientoLibro $m) => [
                'cuenta_contable_id' => (int) $m->cuenta_contable_id,
                'debe_centavos' => (int) $m->haber_centavos,
                'haber_centavos' => (int) $m->debe_centavos,
            ])->all();
            $movimientos = $this->normalizarMovimientos($movimientos);
            $debe = array_sum(array_column($movimientos, 'debe_centavos'));
            $haber = array_sum(array_column($movimientos, 'haber_centavos'));
            if ($debe <= 0 || $debe !== $haber) {
                throw new \InvalidArgumentException('El reverso no cuadra; el asiento original está corrupto.');
            }

            $origenTipo = 'reverso:'.$original->origen_tipo;
            if (Asiento::withoutGlobalScopes()->where('usuario_id', $usuarioId)
                ->where('origen_tipo', $origenTipo)->where('origen_id', $original->id)->exists()) {
                throw new \InvalidArgumentException('El asiento ya fue revertido.');
            }

            $asiento = Asiento::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'descripcion' => 'Reverso: '.$motivo,
                'origen_tipo' => $origenTipo,
                'origen_id' => $original->id,
                'es_reverso' => true,
                'asiento_reversado_id' => $original->id,
                'hash_integridad' => $this->hashMovimientos($movimientos),
            ]);
            $this->persistirMovimientos($usuarioId, $asiento->id, $movimientos);
            $this->seguridad->registrar(
                SeguridadAccion::CorreccionAsiento,
                $motivo,
                $usuarioId,
                'asiento:'.$original->id.'->'.$asiento->id
            );

            if ($original->origen_tipo === \App\Models\HechoTesoreria::class) {
                $hecho = \App\Models\HechoTesoreria::withoutGlobalScopes()
                    ->where('usuario_id', $usuarioId)
                    ->find($original->origen_id);
                if ($hecho?->meta_ahorro_id) {
                    app(\App\Services\MetaAhorroService::class)->sincronizarProgreso($usuarioId, (int) $hecho->meta_ahorro_id);
                }
            }

            SituacionFinancieraService::olvidarResumenShell($usuarioId);

            return $asiento->load('movimientos');
        });
    }

    public function verificarIntegridad(Asiento $asiento): bool
    {
        $movimientos = $asiento->relationLoaded('movimientos')
            ? $asiento->movimientos
            : $asiento->movimientos()->get();

        $payload = $movimientos->map(fn (MovimientoLibro $m) => [
            'cuenta_contable_id' => (int) $m->cuenta_contable_id,
            'debe_centavos' => (int) $m->debe_centavos,
            'haber_centavos' => (int) $m->haber_centavos,
        ])->values()->all();

        return hash_equals((string) $asiento->hash_integridad, $this->hashMovimientos($payload));
    }

    /**
     * @param list<array{cuenta_contable_id:int,debe_centavos:int,haber_centavos:int}> $movimientos
     * @return list<array{cuenta_contable_id:int,debe_centavos:int,haber_centavos:int}>
     */
    private function normalizarMovimientos(array $movimientos): array
    {
        $limpios = [];
        foreach ($movimientos as $movimiento) {
            $debe = (int) ($movimiento['debe_centavos'] ?? 0);
            $haber = (int) ($movimiento['haber_centavos'] ?? 0);
            if ($debe < 0 || $haber < 0 || ($debe > 0 && $haber > 0)) {
                throw new \InvalidArgumentException('Cada movimiento debe ser débito o crédito, no ambos.');
            }
            if ($debe === 0 && $haber === 0) {
                continue;
            }
            $limpios[] = [
                'cuenta_contable_id' => (int) $movimiento['cuenta_contable_id'],
                'debe_centavos' => $debe,
                'haber_centavos' => $haber,
            ];
        }

        return $limpios;
    }

    private function assertCuentasDelUsuario(int $usuarioId, array $movimientos): void
    {
        $ids = array_column($movimientos, 'cuenta_contable_id');
        $validos = \App\Models\CuentaContable::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)->whereIn('id', $ids)->count();
        if ($validos !== count(array_unique($ids))) {
            throw new \InvalidArgumentException('Una cuenta contable no pertenece al usuario.');
        }
    }

    private function persistirMovimientos(int $usuarioId, int $asientoId, array $movimientos): void
    {
        foreach ($movimientos as $movimiento) {
            MovimientoLibro::withoutGlobalScopes()->create($movimiento + [
                'usuario_id' => $usuarioId,
                'asiento_id' => $asientoId,
            ]);
        }
    }

    private function hashMovimientos(array $movimientos): string
    {
        usort($movimientos, fn ($a, $b) => [$a['cuenta_contable_id'], $a['debe_centavos'], $a['haber_centavos']]
            <=> [$b['cuenta_contable_id'], $b['debe_centavos'], $b['haber_centavos']]);

        return hash('sha256', json_encode($movimientos, JSON_THROW_ON_ERROR));
    }
}
