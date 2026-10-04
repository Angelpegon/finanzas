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
use Illuminate\Support\Carbon;
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
            'porcentaje_abono_capital_minimo' => '5',
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }

    public function test_crear_compra_pago_y_corregir(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-03-02'));
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
        $this->travelTo(Carbon::parse('2026-03-12'));
        $pagoResponse = $this->actingAs($usuario)
            ->from(route('app.tarjetas.index'))
            ->post(route('app.tarjetas.pagos.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'cuenta_liquida_id' => $cuenta->id,
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $pagoResponse->assertSessionHasNoErrors();
        $pagoResponse->assertRedirect(route('app.tarjetas.index'));

        $pago = Pago::where('usuario_id', $usuario->id)->where('tipo', 'tarjeta')->firstOrFail();
        $this->assertNotNull($pago->ciclo_facturacion_id);
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
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-03-02'));
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
            'fecha' => '2026-03-02',
            'cuenta_liquida_id' => $cuenta->id,
            'cuotas' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertSame(3.5, (float) $compra->tasa_interes_porcentaje);
        $this->assertSame(0, (int) $compra->cuotasProgramadas()->value('interes_centavos'));

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-03-12'));
        $this->actingAs($usuario)->get(route('app.tarjetas.index'))->assertOk();
        $ciclo = \App\Models\CicloFacturacion::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $dias = \Illuminate\Support\Carbon::parse('2026-03-02')->diffInDays(\Illuminate\Support\Carbon::parse('2026-03-10'), false);
        $this->assertSame((int) round(50_000_00 * 0.035 * $dias / 30), (int) $ciclo->interes_rotativo_centavos);
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
        $this->travelTo(Carbon::parse('2026-03-02'));
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
        $capitalUltima = (int) $cuotas->last()->capital_centavos;
        $this->travelTo(Carbon::parse('2026-03-12'));

        $this->actingAs($usuario)
            ->from(route('app.tarjetas.index'))
            ->post(route('app.tarjetas.pagos.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'cuenta_liquida_id' => $cuenta->id,
                'monto' => (string) (((int) $cuotas->first()->capital_centavos + (int) $cuotas->first()->interes_centavos) / 100),
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.tarjetas.index'));

        $this->assertSame(2, CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('pagada', false)->count());
        $this->assertSame($capitalUltima, (int) CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->orderByDesc('numero')->value('capital_centavos'));

        $this->actingAs($usuario)
            ->from(route('app.tarjetas.index'))
            ->post(route('app.tarjetas.pagos.store'), [
                'tarjeta_credito_id' => $tarjeta->id,
                'cuenta_liquida_id' => $cuenta->id,
                'monto' => '500000',
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrorsIn('pago_tarjeta', ['monto']);
    }

    public function test_editar_fechas_reprograma_cuotas_no_facturadas_y_extracto_abierto(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-03-02'));
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear())->assertRedirect();
        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();
        $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'compra',
            'descripcion' => 'Mueble',
            'monto' => '300000',
            'fecha' => '2026-03-02',
            'categoria_id' => $cat->id,
            'cuotas' => '3',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->travelTo(Carbon::parse('2026-03-12'));
        $this->actingAs($usuario)->get(route('app.tarjetas.index'))->assertOk();
        $ciclo = \App\Models\CicloFacturacion::where('tarjeta_credito_id', $tarjeta->id)->where('estado', 'abierto')->firstOrFail();
        $corteOriginal = $ciclo->fecha_corte->toDateString();
        $this->assertSame('2026-03-25', $ciclo->fecha_pago->toDateString());

        $cuota1 = CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('numero', 1)->firstOrFail();
        $this->assertNotNull($cuota1->ciclo_facturacion_id);
        $vencimientoFacturada = $cuota1->fecha_vencimiento->toDateString();
        $cuota2Antes = CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('numero', 2)->value('fecha_vencimiento');

        $this->actingAs($usuario)
            ->put(route('app.tarjetas.update', $tarjeta), [
                'nombre' => 'Visa Editada',
                'entidad' => 'Banco Nuevo',
                'cupo' => '2500000',
                'tasa_compras_mensual' => '2.8',
                'tasa_avances_mensual' => '3.8',
                'tasa_mora_mensual' => '0',
                'porcentaje_abono_capital_minimo' => '5',
                'cuota_manejo' => '0',
                'dia_corte' => '10',
                'dia_pago' => '28',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.tarjetas.index'));

        $tarjeta->refresh();
        $this->assertSame(28, (int) $tarjeta->dia_pago);
        $ciclo->refresh();
        $this->assertSame($corteOriginal, $ciclo->fecha_corte->toDateString());
        $this->assertSame('2026-03-28', $ciclo->fecha_pago->toDateString());
        $this->assertSame($vencimientoFacturada, $cuota1->fresh()->fecha_vencimiento->toDateString());

        $cuota2 = CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('numero', 2)->firstOrFail();
        $this->assertNull($cuota2->ciclo_facturacion_id);
        $this->assertNotSame(
            Carbon::parse($cuota2Antes)->toDateString(),
            $cuota2->fecha_vencimiento->toDateString()
        );
        $this->assertSame('2026-04-28', $cuota2->fecha_vencimiento->toDateString());

        $this->actingAs($usuario)
            ->from(route('app.tarjetas.edit', $tarjeta))
            ->put(route('app.tarjetas.update', $tarjeta), [
                'nombre' => 'Visa Editada',
                'entidad' => 'Banco Nuevo',
                'cupo' => '100000',
                'tasa_compras_mensual' => '2.8',
                'tasa_avances_mensual' => '3.8',
                'porcentaje_abono_capital_minimo' => '5',
                'dia_corte' => '10',
                'dia_pago' => '28',
            ])
            ->assertSessionHasErrors(['cupo']);
    }

    public function test_compra_liquidada_se_muestra_y_no_se_anula(): void
    {
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-03-02'));
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)->post(route('app.tarjetas.store'), $this->payloadCrear())->assertRedirect();
        $tarjeta = TarjetaCredito::where('usuario_id', $usuario->id)->firstOrFail();
        $this->actingAs($usuario)->post(route('app.tarjetas.compras.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'tipo' => 'compra',
            'descripcion' => 'Celular',
            'monto' => '90000',
            'fecha' => '2026-03-02',
            'categoria_id' => $cat->id,
            'cuotas' => '1',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $compra = CompraTarjeta::where('tarjeta_credito_id', $tarjeta->id)->firstOrFail();
        $this->assertFalse($compra->esta_liquidada);

        $this->travelTo(Carbon::parse('2026-03-12'));
        $this->actingAs($usuario)->post(route('app.tarjetas.pagos.store'), [
            'tarjeta_credito_id' => $tarjeta->id,
            'cuenta_liquida_id' => $cuenta->id,
            'monto' => '90000',
            'fecha' => '2026-03-12',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $compra->refresh();
        $compra->load('cuotasProgramadas');
        $this->assertTrue($compra->esta_liquidada);
        $this->assertSame(0, $tarjeta->fresh()->saldo_actual_centavos);

        $html = $this->actingAs($usuario)->get(route('app.tarjetas.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Celular', $html);
        $this->assertStringContainsString('liquidada', $html);

        $this->actingAs($usuario)
            ->post(route('app.tarjetas.compras.corregir', $compra), [
                'motivo' => 'No debería',
            ])
            ->assertSessionHasErrors();
    }
}
