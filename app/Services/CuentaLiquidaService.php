<?php

namespace App\Services;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\MetaAhorro;
use App\Models\Prestamo;
use App\Support\CuentasOperativas;
use App\Support\Dinero;
use Illuminate\Support\Facades\DB;

class CuentaLiquidaService
{
    public function __construct(private readonly TesoreriaService $tesoreria) {}

    public function crear(int $usuarioId, array $datos): CuentaLiquida
    {
        $saldoInicial = Dinero::pesosACentavos($datos['saldo_inicial'] ?? 0);

        return DB::transaction(function () use ($usuarioId, $datos, $saldoInicial): CuentaLiquida {
            $codigo = '11'.str_pad((string) (CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)->count() + 1), 6, '0', STR_PAD_LEFT);
            $contable = CuentaContable::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'codigo' => $codigo,
                'nombre' => $datos['nombre'],
                'naturaleza' => 'activo',
            ]);
            $cuenta = CuentaLiquida::withoutGlobalScopes()->create([
                'usuario_id' => $usuarioId,
                'cuenta_contable_id' => $contable->id,
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'institucion' => $datos['institucion'] ?? null,
                'numero_cuenta_enmascarado' => $datos['numero_cuenta_enmascarado'] ?? null,
                'saldo_inicial_centavos' => $saldoInicial,
                'moneda' => 'COP',
                'estado' => 'activa',
                'activa' => true,
            ]);
            if ($saldoInicial > 0) {
                $this->tesoreria->registrar(
                    $usuarioId, TipoHechoTesoreria::Apertura, now()->toDateString(),
                    $saldoInicial, $cuenta->id, null, null, 'Saldo inicial de '.$cuenta->nombre
                );
            }

            SituacionFinancieraService::olvidarResumenShell($usuarioId);

            return $cuenta->fresh('cuentaContable');
        });
    }

    public function actualizar(int $usuarioId, CuentaLiquida $cuenta, array $datos): CuentaLiquida
    {
        $this->assertDueno($usuarioId, $cuenta);
        $this->assertNoBolsillo($usuarioId, $cuenta);
        if ($cuenta->estado === 'cancelada') {
            throw new \InvalidArgumentException('Una cuenta cancelada no se puede editar.');
        }

        return DB::transaction(function () use ($usuarioId, $cuenta, $datos): CuentaLiquida {
            $cuenta = CuentaLiquida::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            $cuenta->update([
                'nombre' => $datos['nombre'],
                'tipo' => $datos['tipo'],
                'institucion' => $datos['institucion'] ?? null,
                'numero_cuenta_enmascarado' => $datos['numero_cuenta_enmascarado'] ?? null,
            ]);
            CuentaContable::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->whereKey($cuenta->cuenta_contable_id)
                ->update(['nombre' => $datos['nombre']]);

            return $cuenta->fresh('cuentaContable');
        });
    }

    /**
     * Archiva la cuenta. Si hay saldo, debe transferirse a otra cuenta operativa.
     */
    public function archivar(int $usuarioId, CuentaLiquida $cuenta, ?int $cuentaDestinoId = null): void
    {
        $this->assertDueno($usuarioId, $cuenta);
        $this->assertNoBolsillo($usuarioId, $cuenta);
        if ($cuenta->estado === 'cancelada') {
            throw new \InvalidArgumentException('Una cuenta cancelada no se puede archivar.');
        }
        if ($cuenta->estado === 'inactiva' && ! $cuenta->activa) {
            throw new \InvalidArgumentException('La cuenta ya está archivada.');
        }

        DB::transaction(function () use ($usuarioId, $cuenta, $cuentaDestinoId): void {
            $cuenta = CuentaLiquida::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            $saldo = $cuenta->saldoCentavos();
            if ($saldo < 0) {
                throw new \InvalidArgumentException('La cuenta tiene saldo negativo; corrige el libro antes de archivarla.');
            }
            if ($saldo > 0) {
                if ($cuentaDestinoId === null) {
                    throw new \InvalidArgumentException(
                        'Para archivar con saldo debes transferir los fondos a otra cuenta operativa.'
                    );
                }
                $this->assertDestinoOperativo($usuarioId, $cuentaDestinoId, (int) $cuenta->id);
                $this->tesoreria->registrar(
                    $usuarioId,
                    TipoHechoTesoreria::Transferencia,
                    now()->toDateString(),
                    $saldo,
                    $cuenta->id,
                    null,
                    $cuentaDestinoId,
                    'Traslado al archivar '.$cuenta->nombre
                );
                $cuenta->refresh();
                if ($cuenta->saldoCentavos() !== 0) {
                    throw new \InvalidArgumentException('No se pudo dejar la cuenta en cero antes de archivarla.');
                }
            }

            $cuenta->update(['activa' => false, 'estado' => 'inactiva']);
            SituacionFinancieraService::olvidarResumenShell($usuarioId);
        });
    }

    public function restaurar(int $usuarioId, CuentaLiquida $cuenta): void
    {
        $this->assertDueno($usuarioId, $cuenta);
        $this->assertNoBolsillo($usuarioId, $cuenta);
        if ($cuenta->estado === 'cancelada') {
            throw new \InvalidArgumentException('Una cuenta cancelada no se puede restaurar.');
        }
        $cuenta->update(['activa' => true, 'estado' => 'activa']);
        SituacionFinancieraService::olvidarResumenShell($usuarioId);
    }

    /**
     * Cancela la cuenta de forma permanente (soft). Desaparece de toda la UI operativa.
     * El libro append-only se conserva; no se borran asientos.
     *
     * @param  'transferir'|'baja'|null  $disposicion
     */
    public function cancelar(
        int $usuarioId,
        CuentaLiquida $cuenta,
        ?string $disposicion = null,
        ?int $cuentaDestinoId = null
    ): void {
        $this->assertDueno($usuarioId, $cuenta);
        $this->assertNoBolsillo($usuarioId, $cuenta);
        if ($cuenta->estado === 'cancelada') {
            throw new \InvalidArgumentException('La cuenta ya está cancelada.');
        }

        DB::transaction(function () use ($usuarioId, $cuenta, $disposicion, $cuentaDestinoId): void {
            $cuenta = CuentaLiquida::withoutGlobalScopes()
                ->where('usuario_id', $usuarioId)
                ->lockForUpdate()
                ->findOrFail($cuenta->id);

            $this->assertPuedeCancelarse($usuarioId, $cuenta);

            $saldo = $cuenta->saldoCentavos();
            if ($saldo < 0) {
                throw new \InvalidArgumentException('La cuenta tiene saldo negativo; corrige el libro antes de cancelarla.');
            }

            if (! $cuenta->activa) {
                $cuenta->update(['activa' => true]);
            }

            if ($saldo > 0) {
                if ($disposicion === 'transferir') {
                    if ($cuentaDestinoId === null) {
                        throw new \InvalidArgumentException('Elige la cuenta destino para transferir el saldo.');
                    }
                    $this->assertDestinoOperativo($usuarioId, $cuentaDestinoId, (int) $cuenta->id);
                    $this->tesoreria->registrar(
                        $usuarioId,
                        TipoHechoTesoreria::Transferencia,
                        now()->toDateString(),
                        $saldo,
                        $cuenta->id,
                        null,
                        $cuentaDestinoId,
                        'Traslado al cancelar '.$cuenta->nombre
                    );
                } elseif ($disposicion === 'baja') {
                    $this->tesoreria->registrar(
                        $usuarioId,
                        TipoHechoTesoreria::Cierre,
                        now()->toDateString(),
                        $saldo,
                        $cuenta->id,
                        null,
                        null,
                        'Baja de liquidez al cancelar '.$cuenta->nombre
                    );
                } else {
                    throw new \InvalidArgumentException(
                        'La cuenta tiene saldo. Transfiere a otra cuenta o confirma la baja (sale del disponible).'
                    );
                }

                $cuenta->refresh();
                if ($cuenta->saldoCentavos() !== 0) {
                    throw new \InvalidArgumentException('No se pudo dejar la cuenta en cero antes de cancelarla.');
                }
            }

            $cuenta->update(['activa' => false, 'estado' => 'cancelada']);
            SituacionFinancieraService::olvidarResumenShell($usuarioId);
        });
    }

    private function assertPuedeCancelarse(int $usuarioId, CuentaLiquida $cuenta): void
    {
        $metas = MetaAhorro::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('cuenta_liquida_id', $cuenta->id)
            ->where('estado', '!=', 'cancelada')
            ->exists();
        if ($metas) {
            throw new \InvalidArgumentException('La cuenta es bolsillo de una meta activa; cancela o cambia la meta primero.');
        }

        $prestamos = Prestamo::withoutGlobalScopes()
            ->where('usuario_id', $usuarioId)
            ->where('cuenta_liquida_id', $cuenta->id)
            ->whereHas('cuotas', fn ($q) => $q->where('pagada', false))
            ->exists();
        if ($prestamos) {
            throw new \InvalidArgumentException('La cuenta está ligada a un préstamo con cuotas pendientes.');
        }
    }

    private function assertNoBolsillo(int $usuarioId, CuentaLiquida $cuenta): void
    {
        if (in_array((int) $cuenta->id, CuentasOperativas::idsBolsillosActivos($usuarioId), true)) {
            throw new \InvalidArgumentException('Los bolsillos de meta se gestionan desde Metas, no desde Cuentas.');
        }
    }

    private function assertDestinoOperativo(int $usuarioId, int $destinoId, int $origenId): void
    {
        if ($destinoId === $origenId) {
            throw new \InvalidArgumentException('La cuenta destino debe ser distinta.');
        }
        $ok = CuentasOperativas::queryActivas($usuarioId)->whereKey($destinoId)->exists();
        if (! $ok) {
            throw new \InvalidArgumentException('Elige una cuenta operativa activa como destino.');
        }
    }

    private function assertDueno(int $usuarioId, CuentaLiquida $cuenta): void
    {
        if ((int) $cuenta->usuario_id !== $usuarioId) {
            abort(404);
        }
    }
}
