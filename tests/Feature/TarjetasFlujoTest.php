<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CompraTarjeta;
use App\Models\CuentaLiquida;
use App\Models\CuotaTarjeta;
use App\Models\Pago;
use App\Models\TarjetaCredito;
use App\Models\User;
use App\Services\TesoreriaService;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class TarjetasFlujoTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    private function fondear(User $usuario, CuentaLiquida $cuenta, int $pesos = 20_000_000): void
    {
        app(TesoreriaService::class)->registrar(
            $usuario->id,
            TipoHechoTesoreria::Apertura,
            now()->toDateString(),
            $pesos * 100,
            (int) $cuenta->id
        );
    }

    private function payloadCrear(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Visa Prueba',
            'entidad' => 'Banco Test',
            'cupo' => '2000000',
            'tasa_compras_mensual' => '2.5',
            'tasa_avances_mensual' => '3.5',
            'dia_corte' => '10',
            'dia_pago' => '25',
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }

    public function test_crear_compra_pago_y_corregir(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.store'), $this->payloadCrear())
            ->assertRedirect(route('app.tarjetas.index'));

        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->where('nombre', 'Visa Prueba')->firstOrFail();
        $this->assertSame(2.5, (float) $tarjeta->tasa_compras_mensual);
        $this->assertSame(3.5, (float) $tarjeta->tasa_avances_mensual);
        $this->assertGreaterThan(0, (float) $tarjeta->ea_porcentaje);
        $this->assertFalse(array_key_exists('cuotas', $tarjeta->getAttributes()));
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $tarjeta->cuotasProgramadas);

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.compras.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'tipo' => 'compra',
                'descripcion' => 'TV',
                'monto' => '300000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $cat->id,
                'cuotas' => '3',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.tarjetas.index'));

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame(2.5, (float) $compra->tasa_interes_porcentaje);
        $this->assertSame(300_000_00, $tarjeta->fresh()->saldo_actual_centavos);

        $cuota = $compra->cuotasProgramadas()->orderBy('numero')->firstOrFail();
        $pagoResponse = $this->actingAs($usuario)
            ->from(route('app.tarjetas.index'))
            ->post(route('app.tarjetas.pagos.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'cuota_tarjeta_id' => $cuota->id,
                'cuenta_liquida_id' => $cuenta->id,
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $pagoResponse->assertSessionHasNoErrors();
        $pagoResponse->assertRedirect(route('app.tarjetas.index'));

        $pago = Pago::where('usuario_id', $usuario->id)->where('tipo', 'tarjeta')->firstOrFail();
        $this->assertSame((int) $cuota->id, (int) $pago->cuota_tarjeta_id);
        $this->assertTrue($cuota->fresh()->pagada);

        $this->actingAs($usuario)
            ->get(route('app.tarjetas.index'))
            ->assertOk()
            ->assertSee('Pagos recientes')
            ->assertSee('Corregir')
            ->assertDontSee('name="interes"', false);

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.pagos.corregir', $pago), ['motivo' => 'Error'])
            ->assertRedirect(route('app.tarjetas.index'));

        $this->assertFalse($cuota->fresh()->pagada);
        $this->assertTrue(
            Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuario->id)
                ->whereNotNull('asiento_reversado_id')
                ->exists()
        );
    }

    public function test_avance_usa_tasa_avances_y_aumenta_liquidez(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta, 1_000_000);
        $liqAntes = $cuenta->fresh()->saldoCentavos();

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.store'), $this->payloadCrear([
                'tasa_compras_mensual' => '1',
                'tasa_avances_mensual' => '4',
            ]))
            ->assertRedirect();

        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.compras.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'tipo' => 'avance',
                'descripcion' => 'Cajero',
                'monto' => '100000',
                'fecha' => now()->toDateString(),
                'cuenta_liquida_id' => $cuenta->id,
                'cuotas' => '2',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.tarjetas.index'));

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame('avance', $compra->tipo);
        $this->assertSame(4.0, (float) $compra->tasa_interes_porcentaje);
        $this->assertNull($compra->categoria_id);
        $this->assertSame($liqAntes + 100_000_00, $cuenta->fresh()->saldoCentavos());

        $interesPrimera = (int) $compra->cuotasProgramadas()->orderBy('numero')->value('interes_centavos');
        $this->assertSame((int) round(100_000_00 * 0.04), $interesPrimera);
    }

    public function test_anular_compra_sin_pagos(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear())->assertRedirect();
        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'compra',
            'descripcion' => 'Error',
            'monto' => '50000',
            'fecha' => now()->toDateString(),
            'categoria_id' => $cat->id,
            'cuotas' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame(50_000_00, $tarjeta->fresh()->saldo_actual_centavos);

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.compras.corregir', $compra), ['motivo' => 'Equivocación'])
            ->assertRedirect(route('app.tarjetas.index'));

        $this->assertTrue($compra->fresh()->anulada);
        $this->assertSame(0, CuotaTarjeta::where('compra_tarjeta_id', $compra->id)->count());
        $this->assertSame(0, $tarjeta->fresh()->saldo_actual_centavos);
    }

    public function test_idempotencia_en_crear_tarjeta(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $clave = (string) Str::uuid();
        $payload = $this->payloadCrear(['idempotency_key' => $clave]);

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $payload)->assertRedirect();
        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $payload)->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, TarjetaCredito::where('usuario_id', $usuario->id)->count());
    }

    public function test_compra_una_cuota_es_corriente_sin_interes(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear([
            'tasa_compras_mensual' => '2.5',
            'tasa_avances_mensual' => '3.5',
        ]))->assertRedirect();

        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'compra',
            'descripcion' => 'Supermercado',
            'monto' => '80000',
            'fecha' => now()->toDateString(),
            'categoria_id' => $cat->id,
            'cuotas' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('app.tarjetas.index'));

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame(0.0, (float) $compra->tasa_interes_porcentaje);
        $cuota = $compra->cuotasProgramadas()->firstOrFail();
        $this->assertSame(80_000_00, (int) $cuota->capital_centavos);
        $this->assertSame(0, (int) $cuota->interes_centavos);
    }

    public function test_avance_una_cuota_si_aplica_interes(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear([
            'tasa_avances_mensual' => '3.5',
        ]))->assertRedirect();

        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'avance',
            'descripcion' => 'Cajero',
            'monto' => '50000',
            'fecha' => now()->toDateString(),
            'cuenta_liquida_id' => $cuenta->id,
            'cuotas' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame(3.5, (float) $compra->tasa_interes_porcentaje);
        $this->assertSame((int) round(50_000_00 * 0.035), (int) $compra->cuotasProgramadas()->value('interes_centavos'));
    }

    public function test_index_usa_toolbar_como_otras_secciones(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();

        $html = $this->actingAs($usuario)->get(route('app.tarjetas.index'))->assertOk()->getContent();
        $this->assertStringContainsString('page-toolbar', $html);
        $this->assertStringContainsString('Nueva tarjeta', $html);
        $this->assertStringNotContainsString('add-button', $html);
    }

    public function test_compra_rechaza_campo_interes(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear())->assertRedirect();
        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $response = $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'compra',
            'descripcion' => 'X',
            'monto' => '10000',
            'fecha' => now()->toDateString(),
            'categoria_id' => $cat->id,
            'cuotas' => '1',
            'interes' => '9.9',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('app.tarjetas.index'));
        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame(0.0, (float) $compra->tasa_interes_porcentaje);
    }

    public function test_pago_con_abono_extra_desde_ui(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear())->assertRedirect();
        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();
        $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'compra',
            'descripcion' => 'TV',
            'monto' => '300000',
            'fecha' => now()->toDateString(),
            'categoria_id' => $cat->id,
            'cuotas' => '3',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $cuotas = CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->orderBy('numero')->get();
        $proxima = $cuotas->first();
        $minimo = (int) $proxima->capital_centavos + (int) $proxima->interes_centavos;
        $extra = intdiv((int) $cuotas->last()->capital_centavos, 2);

        $this->actingAs($usuario)
            ->from(route('app.tarjetas.index'))
            ->post(route('app.tarjetas.pagos.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'cuota_tarjeta_id' => $proxima->id,
                'cuenta_liquida_id' => $cuenta->id,
                'monto' => (string) (($minimo + $extra) / 100),
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.tarjetas.index'));

        $pago = Pago::where('usuario_id', $usuario->id)->where('tipo', 'tarjeta')->firstOrFail();
        $this->assertTrue($pago->extraordinario);
        $this->assertSame(2, CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('pagada', false)->count());
    }
}
