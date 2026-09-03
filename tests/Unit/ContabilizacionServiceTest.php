<?php

namespace Tests\Unit;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Services\ContabilizacionService;
use App\Services\TesoreriaService;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class ContabilizacionServiceTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_asiento_descuadrado_se_rechaza(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        app(ContabilizacionService::class)->postear($user->id, now()->toDateString(), 'malo', 'test', 1, [
            ['cuenta_contable_id' => $cuenta->cuenta_contable_id, 'debe_centavos' => 100, 'haber_centavos' => 0],
            ['cuenta_contable_id' => $cuenta->cuenta_contable_id, 'debe_centavos' => 0, 'haber_centavos' => 50],
        ]);
    }

    public function test_doble_posteo_del_mismo_origen_se_rechaza(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $patrimonio = \App\Models\CuentaContable::withoutGlobalScopes()->where('usuario_id', $user->id)->where('codigo', '3100')->firstOrFail();
        $svc = app(ContabilizacionService::class);
        $movs = [
            ['cuenta_contable_id' => $cuenta->cuenta_contable_id, 'debe_centavos' => 1000, 'haber_centavos' => 0],
            ['cuenta_contable_id' => $patrimonio->id, 'debe_centavos' => 0, 'haber_centavos' => 1000],
        ];
        $svc->postear($user->id, now()->toDateString(), 'uno', 'origen-test', 99, $movs);

        $this->expectException(\InvalidArgumentException::class);
        $svc->postear($user->id, now()->toDateString(), 'dos', 'origen-test', 99, $movs);
    }

    public function test_reverso_anula_saldos_y_audita(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 50_000_00, $cuenta->id, null, null, 'apertura'
        );
        $asiento = Asiento::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $this->assertSame(50_000_00, $cuenta->fresh()->saldoCentavos());

        $reverso = app(ContabilizacionService::class)->revertir($user->id, $asiento->id, now()->toDateString(), 'corrección de prueba');
        $this->assertTrue($reverso->es_reverso);
        $this->assertSame(0, $cuenta->fresh()->saldoCentavos());
        $this->assertTrue(app(ContabilizacionService::class)->verificarIntegridad($reverso->fresh('movimientos')));
        $this->assertDatabaseHas('seguridad_logs', [
            'usuario_id' => $user->id,
            'accion' => 'correccion_asiento',
        ]);
    }

    public function test_ingreso_gasto_y_transferencia_cuadran(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $destino = app(\App\Services\CuentaLiquidaService::class)->crear($user->id, [
            'nombre' => 'Banco', 'tipo' => 'banco', 'saldo_inicial' => '0',
        ]);
        $catIngreso = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'ingreso')->firstOrFail();
        $catGasto = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();
        $tesoreria = app(TesoreriaService::class);

        $tesoreria->registrar($user->id, TipoHechoTesoreria::Ingreso, now()->toDateString(), 200_000_00, $origen->id, $catIngreso->id);
        $this->assertSame(200_000_00, $origen->fresh()->saldoCentavos());

        $tesoreria->registrar($user->id, TipoHechoTesoreria::Gasto, now()->toDateString(), 50_000_00, $origen->id, $catGasto->id);
        $this->assertSame(150_000_00, $origen->fresh()->saldoCentavos());

        $tesoreria->registrar($user->id, TipoHechoTesoreria::Transferencia, now()->toDateString(), 40_000_00, $origen->id, null, $destino->id, 'traslado');
        $this->assertSame(110_000_00, $origen->fresh()->saldoCentavos());
        $this->assertSame(40_000_00, $destino->fresh()->saldoCentavos());
        $this->assertSame(3, HechoTesoreria::withoutGlobalScopes()->where('usuario_id', $user->id)->count());
    }
}
