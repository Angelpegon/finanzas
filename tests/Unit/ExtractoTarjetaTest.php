<?php

namespace Tests\Unit;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CicloFacturacion;
use App\Models\CuentaLiquida;
use App\Models\CuotaTarjeta;
use App\Services\ExtractoTarjetaService;
use App\Services\TarjetaService;
use App\Services\TesoreriaService;
use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class ExtractoTarjetaTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_diferido_solo_exige_la_cuota_del_mes_y_el_exceso_adelanta(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 2.5, 3.5);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 300_000_00, 3, '2026-03-02', 'compra', $cat->id
        );
        $this->travelTo(Carbon::parse('2026-03-12'));

        $pago = app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-12'
        );

        $cuotas = CuotaTarjeta::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->orderBy('numero')->get();
        $this->assertTrue($cuotas[0]->pagada);
        $this->assertFalse($cuotas[1]->pagada);
        $this->assertNotNull($pago->ciclo_facturacion_id);

        $capital2 = (int) $cuotas[1]->capital_centavos;
        $interes2 = (int) $cuotas[1]->interes_centavos;
        $pasivoAntes = $tarjeta->fresh()->saldo_actual_centavos;
        $extra = app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-12', $capital2
        );

        $this->assertTrue($extra->extraordinario);
        $this->assertNull($extra->ciclo_facturacion_id);
        $this->assertSame($capital2, (int) $extra->capital_centavos);
        $this->assertSame(0, (int) $extra->interes_centavos);
        $cuota2 = $cuotas[1]->fresh();
        $this->assertTrue($cuota2->pagada);
        $this->assertSame($capital2 + $interes2, (int) $cuota2->abonado_centavos);
        $this->assertSame($pasivoAntes - $capital2, $tarjeta->fresh()->saldo_actual_centavos);
        $this->assertFalse($cuotas[2]->fresh()->pagada);
    }

    public function test_abono_antes_del_primer_corte_no_crea_ciclo(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 2.5, 3.5);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 300_000_00, 3, '2026-03-02', 'compra', $cat->id
        );

        $cuotas = CuotaTarjeta::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->orderBy('numero')->get();
        $capital1 = (int) $cuotas[0]->capital_centavos;
        $pago = app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-05', $capital1
        );

        $this->assertTrue($pago->extraordinario);
        $this->assertNull($pago->ciclo_facturacion_id);
        $this->assertSame(0, CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->count());
        $this->assertTrue($cuotas[0]->fresh()->pagada);
        $this->assertSame(300_000_00 - $capital1, $tarjeta->fresh()->saldo_actual_centavos);
    }

    public function test_pago_sobre_el_pasivo_se_rechaza(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 2.5, 3.5);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 100_000_00, 1, '2026-03-02', 'compra', $cat->id
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El pago supera el saldo de la tarjeta.');
        app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-05', 100_000_00 + 1
        );
    }

    public function test_corregir_abono_extraordinario_sin_ciclo_restaura_cuotas(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 2.5, 3.5);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 300_000_00, 3, '2026-03-02', 'compra', $cat->id
        );

        $cuotas = CuotaTarjeta::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->orderBy('numero')->get();
        $capital1 = (int) $cuotas[0]->capital_centavos;
        $pago = app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-05', $capital1
        );
        $this->assertTrue($cuotas[0]->fresh()->pagada);

        app(TarjetaService::class)->corregirPago($user->id, (int) $pago->id, '2026-03-05', 'Error de abono');

        $cuota = $cuotas[0]->fresh();
        $this->assertFalse($cuota->pagada);
        $this->assertSame(0, (int) $cuota->abonado_centavos);
        $this->assertSame(300_000_00, $tarjeta->fresh()->saldo_actual_centavos);
    }

    public function test_pagar_el_total_conserva_la_gracia_y_el_minimo_la_pierde(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 3.0, 4.0);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 200_000_00, 1, '2026-03-02', 'compra', $cat->id
        );

        $this->travelTo(Carbon::parse('2026-03-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);
        $marzo = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-03-10')->firstOrFail();
        $this->assertSame(0, (int) $marzo->interes_rotativo_centavos);
        $this->assertSame(200_000_00, (int) $marzo->capital_rotativo_centavos);
        $this->assertSame((int) round(200_000_00 * 0.05), (int) $marzo->pago_minimo_centavos);

        app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-20', (int) $marzo->pago_total_centavos
        );
        $this->travelTo(Carbon::parse('2026-04-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);
        $abril = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-04-10')->firstOrFail();
        $this->assertSame(0, (int) $abril->interes_rotativo_centavos);
        $this->assertSame(0, (int) $abril->pago_total_centavos);
    }

    public function test_minimo_rota_desde_la_compra_y_la_mora_entra_si_no_cubre_el_minimo(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 3.0, 4.0, null, 5, 0, 3.5);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 200_000_00, 1, '2026-03-02', 'compra', $cat->id
        );

        $this->travelTo(Carbon::parse('2026-03-12'));
        app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-12'
        );
        $marzo = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-03-10')->firstOrFail();
        $this->assertSame((int) $marzo->pago_minimo_centavos, (int) $marzo->fresh()->pagado_centavos);

        $this->travelTo(Carbon::parse('2026-04-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);
        $abril = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-04-10')->firstOrFail();

        $capitalImpago = 200_000_00 - (int) round(200_000_00 * 0.05);
        $dias = Carbon::parse('2026-03-02')->diffInDays(Carbon::parse('2026-04-10'), false);
        $esperado = (int) round($capitalImpago * 0.03 * $dias / 30);
        $this->assertSame($esperado, (int) $abril->interes_rotativo_centavos);
        $this->assertSame(0, (int) $abril->interes_mora_centavos);

        [$user2, $cuenta2, $cat2] = $this->base();
        $otra = app(TarjetaService::class)->crear($user2->id, 'Otra', 2_000_000_00, 10, 25, 3.0, 4.0, null, 5, 0, 3.5);
        app(TarjetaService::class)->registrarCompra(
            $user2->id, $otra->id, 200_000_00, 1, '2026-03-02', 'compra', $cat2->id
        );
        $this->travelTo(Carbon::parse('2026-04-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user2->id);
        $cicloMora = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $otra->id)->whereDate('fecha_corte', '2026-04-10')->firstOrFail();
        $this->assertGreaterThan(0, (int) $cicloMora->interes_mora_centavos);
    }

    public function test_avance_de_una_cuota_causa_interes_aunque_haya_gracia(): void
    {
        [$user, $cuenta] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 0, 3.0);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 100_000_00, 1, '2026-03-02', 'avance', null, $cuenta->id
        );
        $this->travelTo(Carbon::parse('2026-03-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);

        $marzo = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-03-10')->firstOrFail();
        $dias = Carbon::parse('2026-03-02')->diffInDays(Carbon::parse('2026-03-10'), false);
        $this->assertSame((int) round(100_000_00 * 0.03 * $dias / 30), (int) $marzo->interes_rotativo_centavos);
        $this->assertSame(0, (int) $marzo->interes_diferido_centavos);
        $this->assertSame(100_000_00, (int) $marzo->capital_diferido_centavos);
        $this->assertSame(0, (int) $marzo->capital_rotativo_centavos);

        app(TarjetaService::class)->registrarPago(
            $user->id, $tarjeta->id, $cuenta->id, null, '2026-03-12', (int) $marzo->pago_total_centavos
        );
        $this->assertSame(0, $tarjeta->fresh()->saldo_actual_centavos);
    }

    public function test_abono_parcial_paga_interes_del_diferido_antes_que_capital_de_avance(): void
    {
        [$user, $cuenta, $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 2.5, 3.0);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 100_000_00, 1, '2026-03-02', 'avance', null, $cuenta->id
        );
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 300_000_00, 3, '2026-03-02', 'compra', $cat->id
        );
        $this->travelTo(Carbon::parse('2026-03-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);
        $marzo = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-03-10')->firstOrFail();

        app(TarjetaService::class)->registrarPago(
            $user->id,
            $tarjeta->id,
            $cuenta->id,
            null,
            '2026-03-12',
            (int) $marzo->interes_rotativo_centavos + (int) $marzo->interes_diferido_centavos
        );

        $avance = CuotaTarjeta::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->orderBy('id')->firstOrFail();
        $diferido = CuotaTarjeta::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->orderBy('id')->skip(1)->firstOrFail();
        $this->assertSame(0, (int) $avance->fresh()->abonado_centavos);
        $this->assertSame((int) $marzo->interes_diferido_centavos, (int) $diferido->fresh()->abonado_centavos);
    }

    public function test_consultar_un_mes_futuro_no_causa_ese_corte(): void
    {
        [$user, , $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 3.0, 4.0);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 200_000_00, 1, '2026-03-02', 'compra', $cat->id
        );
        $this->travelTo(Carbon::parse('2026-03-12'));

        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id, Carbon::parse('2026-06-15'));

        $this->assertNotNull(CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-03-10')->first());
        $this->assertNull(CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->whereDate('fecha_corte', '2026-04-10')->first());
    }

    public function test_la_cuota_del_segundo_mes_entra_aunque_la_compra_sea_anterior_al_corte(): void
    {
        [$user, , $cat] = $this->base();
        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 10, 25, 2.5, 3.0);
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 300_000_00, 3, '2026-03-02', 'compra', $cat->id
        );

        $this->travelTo(Carbon::parse('2026-03-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);
        $marzo = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->where('estado', 'abierto')->firstOrFail();
        $this->assertSame(100_000_00, (int) $marzo->capital_diferido_centavos);

        $this->travelTo(Carbon::parse('2026-04-12'));
        app(ExtractoTarjetaService::class)->cerrarExtractosVencidos($user->id);
        $abril = CicloFacturacion::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->where('estado', 'abierto')->firstOrFail();
        $cuotas = CuotaTarjeta::withoutGlobalScopes()->where('tarjeta_credito_id', $tarjeta->id)->orderBy('numero')->get();
        $this->assertSame((int) $cuotas[0]->capital_centavos + (int) $cuotas[1]->capital_centavos, (int) $abril->capital_diferido_centavos);
        $this->assertNull($cuotas[2]->ciclo_facturacion_id);
    }

    /** @return array{0: \App\Models\User, 1: CuentaLiquida, 2: Categoria} */
    private function base(): array
    {
        $this->travelTo(Carbon::parse('2026-03-02'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-03-01', 20_000_000_00, $cuenta->id
        );
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();

        return [$user, $cuenta, $cat];
    }
}
