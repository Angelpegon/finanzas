<?php

namespace Tests\Unit;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Services\MetaAhorroService;
use App\Services\PrestamoService;
use App\Services\SituacionFinancieraService;
use App\Services\TarjetaService;
use App\Services\TesoreriaService;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class ObligacionesYMetasTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_prestamo_calendario_y_pago_con_abono_extra_reduce_plazo(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-01-01', 20_000_000_00, $cuenta->id
        );

        $prestamo = app(PrestamoService::class)->crear(
            $user->id, 'Libre inversión', 5_000_000_00, 24.0, 12, '2026-01-10', 15, $cuenta->id
        );
        $this->assertCount(12, $prestamo->cuotas);
        $primera = $prestamo->cuotas->first();
        $total = (int) $primera->total_centavos;
        $pendientesAntes = $prestamo->cuotas()->where('pagada', false)->count();

        app(PrestamoService::class)->registrarPago($user->id, $prestamo->id, $total + 500_000_00, '2026-02-15');
        $pendientesDespues = CuotaPrestamo::withoutGlobalScopes()
            ->where('prestamo_id', $prestamo->id)->where('pagada', false)->count();

        $this->assertLessThan($pendientesAntes - 1, $pendientesDespues + 0); // paid one + shortened
        $this->assertTrue($primera->fresh()->pagada);
    }

    public function test_compra_tarjeta_no_aumenta_liquidez_y_pago_reduce_pasivo(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 1_000_000_00, $cuenta->id
        );
        $liqAntes = $cuenta->fresh()->saldoCentavos();
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 5, 20, 30);
        $compra = app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 300_000_00, 3, now()->toDateString(), $cat->id, 'TV', 1.5
        );

        $this->assertSame($liqAntes, $cuenta->fresh()->saldoCentavos());
        $this->assertCount(3, $compra->cuotasProgramadas);
        $this->assertSame(300_000_00, $tarjeta->fresh()->saldo_actual_centavos);

        $cuota = $compra->cuotasProgramadas->first();
        app(TarjetaService::class)->registrarPago($user->id, $tarjeta->id, $cuenta->id, $cuota->id, now()->toDateString());
        $this->assertTrue($cuota->fresh()->pagada);
        $this->assertLessThan($liqAntes, $cuenta->fresh()->saldoCentavos());
        $this->assertLessThan(300_000_00, $tarjeta->fresh()->saldo_actual_centavos);
    }

    public function test_meta_avance_solo_con_aportes_posteados(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 500_000_00, $origen->id
        );
        $meta = app(MetaAhorroService::class)->crear(
            $user->id, 'Viaje', 1_000_000_00, null, 100_000_00, $origen->id
        );
        $bolsillo = $meta->cuentaLiquida;
        $this->assertNotNull($bolsillo);
        $this->assertSame($origen->nombre.'_bolsillo1', $bolsillo->nombre);
        $this->assertNotSame($origen->id, $bolsillo->id);
        $this->assertSame(0, $meta->progreso_centavos);

        app(MetaAhorroService::class)->aportar($user->id, $meta->id, 150_000_00, $origen->id, now()->toDateString());
        $this->assertSame(150_000_00, $meta->fresh()->progreso_centavos);
        $this->assertSame(150_000_00, $bolsillo->fresh()->saldoCentavos());
    }

    public function test_disponible_excluye_saldo_de_bolsillo_de_meta_activa(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 500_000_00, $origen->id
        );
        $meta = app(MetaAhorroService::class)->crear(
            $user->id, 'Viaje', 1_000_000_00, null, 0, $origen->id
        );
        app(MetaAhorroService::class)->aportar($user->id, $meta->id, 150_000_00, $origen->id, now()->toDateString());

        $situacion = app(SituacionFinancieraService::class)->responder($user->id);

        $this->assertSame(500_000_00, $situacion['tengo_centavos']);
        $this->assertSame(150_000_00, $situacion['reservado_metas_centavos']);
        // Libre 350k − cuotas 0 − aporte_mensual 0 − gastos proyectados pendientes
        $this->assertSame(350_000_00, $situacion['dinero_disponible_real_centavos']);
    }

    public function test_segunda_meta_sobre_misma_referencia_incrementa_sufijo_bolsillo(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $meta1 = app(MetaAhorroService::class)->crear($user->id, 'Viaje', 100_000_00, null, 0, $origen->id);
        $meta2 = app(MetaAhorroService::class)->crear($user->id, 'Emergencia', 200_000_00, null, 0, $origen->id);

        $this->assertSame($origen->nombre.'_bolsillo1', $meta1->cuentaLiquida->nombre);
        $this->assertSame($origen->nombre.'_bolsillo2', $meta2->cuentaLiquida->nombre);
        $this->assertNotSame($meta1->cuenta_liquida_id, $meta2->cuenta_liquida_id);
    }

    public function test_transferencia_rechaza_bolsillo_de_meta_activa(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 500_000_00, $origen->id
        );
        $meta = app(MetaAhorroService::class)->crear(
            $user->id, 'Viaje', 1_000_000_00, null, 0, $origen->id
        );
        app(MetaAhorroService::class)->aportar($user->id, $meta->id, 100_000_00, $origen->id, now()->toDateString());
        $bolsillo = $meta->fresh()->cuentaLiquida;
        $this->assertNotNull($bolsillo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/bolsillo/i');

        app(TesoreriaService::class)->registrar(
            $user->id,
            TipoHechoTesoreria::Transferencia,
            now()->toDateString(),
            50_000_00,
            $bolsillo->id,
            null,
            $origen->id,
            'sacar del bolsillo a mano'
        );
    }
}
