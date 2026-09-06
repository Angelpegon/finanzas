<?php

namespace Tests\Unit;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Services\CuentaLiquidaService;
use App\Services\TesoreriaService;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class CuentaLiquidaCicloTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_archivar_y_restaurar_no_tocan_el_libro(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $service = app(CuentaLiquidaService::class);

        $service->archivar($user->id, $cuenta);
        $cuenta->refresh();
        $this->assertFalse($cuenta->activa);
        $this->assertSame('inactiva', $cuenta->estado);

        $service->restaurar($user->id, $cuenta);
        $cuenta->refresh();
        $this->assertTrue($cuenta->activa);
        $this->assertSame('activa', $cuenta->estado);
    }

    public function test_cancelar_con_transferencia_deja_origen_en_cero(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $destino = app(CuentaLiquidaService::class)->crear($user->id, [
            'nombre' => 'Destino',
            'tipo' => 'banco',
            'saldo_inicial' => 0,
        ]);
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 150_000_00, $origen->id
        );

        $saldoOrigen = $origen->fresh()->saldoCentavos();
        $this->assertGreaterThan(0, $saldoOrigen);

        app(CuentaLiquidaService::class)->cancelar($user->id, $origen, 'transferir', $destino->id);

        $origen->refresh();
        $destino->refresh();
        $this->assertSame(0, $origen->saldoCentavos());
        $this->assertSame('cancelada', $origen->estado);
        $this->assertFalse($origen->activa);
        $this->assertSame($saldoOrigen, $destino->saldoCentavos());
        $this->assertTrue(
            HechoTesoreria::withoutGlobalScopes()
                ->where('usuario_id', $user->id)
                ->where('tipo', TipoHechoTesoreria::Transferencia->value)
                ->where('cuenta_liquida_id', $origen->id)
                ->exists()
        );
    }

    public function test_cancelar_con_baja_reduce_disponible(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 80_000_00, $cuenta->id
        );
        $antes = $cuenta->fresh()->saldoCentavos();

        app(CuentaLiquidaService::class)->cancelar($user->id, $cuenta, 'baja');

        $cuenta->refresh();
        $this->assertSame(0, $cuenta->saldoCentavos());
        $this->assertSame('cancelada', $cuenta->estado);
        $this->assertTrue(
            HechoTesoreria::withoutGlobalScopes()
                ->where('usuario_id', $user->id)
                ->where('tipo', TipoHechoTesoreria::Cierre->value)
                ->where('monto_centavos', $antes)
                ->exists()
        );
    }

    public function test_cancelar_con_saldo_sin_disposicion_falla(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 10_000_00, $cuenta->id
        );

        $this->expectException(\InvalidArgumentException::class);
        app(CuentaLiquidaService::class)->cancelar($user->id, $cuenta);
    }

    public function test_cuenta_cancelada_no_se_restaura(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(CuentaLiquidaService::class)->cancelar($user->id, $cuenta);

        $this->expectException(\InvalidArgumentException::class);
        app(CuentaLiquidaService::class)->restaurar($user->id, $cuenta->fresh());
    }
}
