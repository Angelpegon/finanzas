<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\User;
use App\Services\CuentaLiquidaService;
use App\Services\PrestamoService;
use App\Services\TesoreriaService;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class PagosFlujoTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    private function fondear(User $usuario, CuentaLiquida $cuenta, int $pesos = 5_000_000): void
    {
        app(TesoreriaService::class)->registrar(
            $usuario->id,
            TipoHechoTesoreria::Apertura,
            now()->toDateString(),
            $pesos * 100,
            (int) $cuenta->id
        );
    }

    private function segundaCuenta(User $usuario): CuentaLiquida
    {
        return app(CuentaLiquidaService::class)->crear($usuario->id, [
            'nombre' => 'Bancolombia',
            'tipo' => 'bancaria',
            'institucion' => 'Bancolombia',
            'saldo_inicial' => 0,
        ]);
    }

    public function test_transferencia_mueve_saldos_y_se_corrige(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $destino = $this->segundaCuenta($usuario);
        $this->fondear($usuario, $origen);

        $antesOrigen = $origen->fresh()->saldoCentavos();
        $antesDestino = $destino->fresh()->saldoCentavos();

        $this->actingAs($usuario)
            ->post(route('app.transferencias.store'), [
                'cuenta_liquida_id' => $origen->id,
                'cuenta_destino_id' => $destino->id,
                'monto' => '100000',
                'fecha' => now()->toDateString(),
                'descripcion' => 'Ahorro',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.pagos.index'));

        $this->assertSame($antesOrigen - 100_000_00, $origen->fresh()->saldoCentavos());
        $this->assertSame($antesDestino + 100_000_00, $destino->fresh()->saldoCentavos());

        $hecho = HechoTesoreria::where('usuario_id', $usuario->id)
            ->where('tipo', TipoHechoTesoreria::Transferencia)
            ->firstOrFail();

        $this->actingAs($usuario)
            ->get(route('app.pagos.index'))
            ->assertOk()
            ->assertSee('Bancolombia')
            ->assertSee('Corregir');

        $this->actingAs($usuario)
            ->post(route('app.transferencias.corregir', $hecho), ['motivo' => 'Error'])
            ->assertRedirect(route('app.pagos.index'));

        $this->assertSame($antesOrigen, $origen->fresh()->saldoCentavos());
        $this->assertSame($antesDestino, $destino->fresh()->saldoCentavos());
    }

    public function test_pago_a_tercero_y_corregir(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();
        $liqAntes = $cuenta->fresh()->saldoCentavos();

        $this->actingAs($usuario)
            ->post(route('app.pagos.store'), [
                'tipo' => 'deuda_personal',
                'fecha' => now()->toDateString(),
                'monto' => '50000',
                'cuenta_liquida_id' => $cuenta->id,
                'categoria_id' => $cat->id,
                'destino' => 'Juan Nequi',
                'referencia' => 'TRF-'.Str::random(8),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.pagos.index', ['pago' => 1]));

        $pago = Pago::where('usuario_id', $usuario->id)->where('tipo', 'deuda_personal')->firstOrFail();
        $this->assertSame($liqAntes - 50_000_00, $cuenta->fresh()->saldoCentavos());

        $this->actingAs($usuario)
            ->post(route('app.pagos.corregir', $pago), ['motivo' => 'Mal destinatario'])
            ->assertRedirect(route('app.pagos.index', ['pago' => 1]));

        $this->assertSame($liqAntes, $cuenta->fresh()->saldoCentavos());
        $this->assertTrue(
            Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuario->id)
                ->whereNotNull('asiento_reversado_id')
                ->exists()
        );
    }

    public function test_pago_deuda_rastreada_usa_prestamo_service(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta, 20_000_000);

        $prestamo = app(PrestamoService::class)->crear(
            $usuario->id,
            'Libre inversión',
            1_000_000_00,
            12.0,
            6,
            now()->toDateString(),
            15,
            (int) $cuenta->id,
            'Banco Test',
            'prestamo_bancario',
            'ea',
            'mensual'
        );

        $cuota = $prestamo->cuotas()->where('pagada', false)->orderBy('numero')->firstOrFail();
        $total = (int) ($cuota->total_centavos ?: ($cuota->capital_centavos + $cuota->interes_centavos));

        $response = $this->actingAs($usuario)
            ->from(route('app.pagos.index', ['pago' => 1]))
            ->post(route('app.pagos.store'), [
                'tipo' => 'prestamo',
                'prestamo_id' => $prestamo->id,
                'fecha' => now()->toDateString(),
                'monto' => (string) ($total / 100),
                'cuenta_liquida_id' => $cuenta->id,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('app.pagos.index', ['pago' => 1]));

        $pago = Pago::where('usuario_id', $usuario->id)->where('tipo', 'prestamo')->firstOrFail();
        $this->assertSame((int) $cuota->id, (int) $pago->cuota_prestamo_id);
        $this->assertTrue($cuota->fresh()->pagada);

        $this->actingAs($usuario)
            ->get(route('app.pagos.index', ['pago' => 1]))
            ->assertOk()
            ->assertSee('Libre inversión')
            ->assertDontSee('name="tipo">Gasto', false);
    }

    public function test_destino_otra_registra_gasto_no_transferencia(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $origen);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();
        $liqAntes = $origen->fresh()->saldoCentavos();

        $this->actingAs($usuario)
            ->from(route('app.pagos.index'))
            ->post(route('app.transferencias.store'), [
                'cuenta_liquida_id' => $origen->id,
                'cuenta_destino_id' => 'otra',
                'destino' => 'Ana Nequi',
                'categoria_id' => $cat->id,
                'monto' => '75000',
                'fecha' => now()->toDateString(),
                'descripcion' => 'Regalo',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.pagos.index'));

        $this->assertSame($liqAntes - 75_000_00, $origen->fresh()->saldoCentavos());
        $this->assertSame(0, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Transferencia)->count());
        $gasto = HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Gasto)->firstOrFail();
        $this->assertSame((int) $cat->id, (int) $gasto->categoria_id);
        $this->assertStringContainsString('Ana Nequi', (string) $gasto->descripcion);
    }

    public function test_destino_otra_exige_categoria(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)
            ->from(route('app.pagos.index'))
            ->post(route('app.transferencias.store'), [
                'cuenta_liquida_id' => $origen->id,
                'cuenta_destino_id' => 'otra',
                'destino' => 'X',
                'monto' => '1000',
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrorsIn('transferencia', ['categoria_id']);
    }

    public function test_pago_tarjeta_con_abono_extra_desde_movimientos(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta, 20_000_000);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $tarjeta = app(\App\Services\TarjetaService::class)->crear(
            $usuario->id,
            'Visa Mov',
            5_000_000_00,
            10,
            25,
            2.5,
            3.5,
            'Banco'
        );
        app(\App\Services\TarjetaService::class)->registrarCompra(
            $usuario->id,
            $tarjeta->id,
            300_000_00,
            3,
            now()->toDateString(),
            'compra',
            $cat->id
        );

        $cuotas = \App\Models\CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)
            ->where('pagada', false)
            ->orderBy('numero')
            ->get();
        $this->assertCount(3, $cuotas);
        $proxima = $cuotas->first();
        $minimo = (int) $proxima->capital_centavos + (int) $proxima->interes_centavos;
        $capitalUltima = (int) $cuotas->last()->capital_centavos;
        $extra = intdiv($capitalUltima, 2);
        $monto = $minimo + $extra;

        $this->actingAs($usuario)
            ->get(route('app.pagos.index', ['pago' => 1]))
            ->assertOk()
            ->assertSee('Tarjeta de crédito')
            ->assertSee('Visa Mov');

        $response = $this->actingAs($usuario)
            ->from(route('app.pagos.index', ['pago' => 1]))
            ->post(route('app.pagos.store'), [
                'tipo' => 'tarjeta',
                'tarjeta_credito_id' => $tarjeta->id,
                'fecha' => now()->toDateString(),
                'monto' => (string) ($monto / 100),
                'cuenta_liquida_id' => $cuenta->id,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('app.pagos.index', ['pago' => 1]));

        $pago = Pago::where('usuario_id', $usuario->id)->where('tipo', 'tarjeta')->firstOrFail();
        $this->assertTrue($pago->extraordinario);
        $this->assertTrue($proxima->fresh()->pagada);
        $this->assertSame(2, \App\Models\CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('pagada', false)->count());

        $ultima = \App\Models\CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)
            ->where('pagada', false)
            ->orderByDesc('numero')
            ->firstOrFail();
        $this->assertSame($capitalUltima - $extra, (int) $ultima->capital_centavos);

        $this->actingAs($usuario)
            ->post(route('app.pagos.corregir', $pago), ['motivo' => 'Error abono'])
            ->assertRedirect(route('app.pagos.index', ['pago' => 1]));

        $this->assertFalse($proxima->fresh()->pagada);
        $this->assertSame(3, \App\Models\CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->where('pagada', false)->count());
        $restaurada = \App\Models\CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)
            ->where('pagada', false)
            ->orderByDesc('numero')
            ->firstOrFail();
        $this->assertSame($capitalUltima, (int) $restaurada->capital_centavos);
    }

    public function test_corregir_abono_tarjeta_no_borra_compra_posterior(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta, 20_000_000);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();
        $svc = app(\App\Services\TarjetaService::class);

        $tarjeta = $svc->crear($usuario->id, 'Visa Corr', 5_000_000_00, 10, 25, 2.5, 3.5);
        $svc->registrarCompra($usuario->id, $tarjeta->id, 300_000_00, 3, now()->toDateString(), 'compra', $cat->id);

        $cuotas = \App\Models\CuotaTarjeta::where('tarjeta_credito_id', $tarjeta->id)->orderBy('numero')->get();
        $proxima = $cuotas->first();
        $minimo = (int) $proxima->capital_centavos + (int) $proxima->interes_centavos;
        $extra = (int) $cuotas->last()->capital_centavos;

        $pago = $svc->registrarPago(
            $usuario->id,
            $tarjeta->id,
            $cuenta->id,
            null,
            now()->toDateString(),
            $minimo + $extra
        );
        $this->assertTrue($pago->extraordinario);

        $compraNueva = $svc->registrarCompra(
            $usuario->id,
            $tarjeta->id,
            50_000_00,
            1,
            now()->toDateString(),
            'compra',
            $cat->id
        );
        $cuotaNuevaId = (int) $compraNueva->cuotasProgramadas->first()->id;

        $svc->corregirPago($usuario->id, (int) $pago->id, now()->toDateString(), 'Deshacer abono');

        $this->assertNotNull(\App\Models\CuotaTarjeta::find($cuotaNuevaId));
        $this->assertFalse(\App\Models\CuotaTarjeta::findOrFail($cuotaNuevaId)->pagada);
        $this->assertSame(
            3,
            \App\Models\CuotaTarjeta::where('compra_tarjeta_id', $cuotas->first()->compra_tarjeta_id)->count()
        );
    }

    public function test_idempotencia_transferencia(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $destino = $this->segundaCuenta($usuario);
        $this->fondear($usuario, $origen);
        $clave = (string) Str::uuid();
        $payload = [
            'cuenta_liquida_id' => $origen->id,
            'cuenta_destino_id' => $destino->id,
            'monto' => '10000',
            'fecha' => now()->toDateString(),
            'idempotency_key' => $clave,
        ];

        $this->actingAs($usuario)->post(route('app.transferencias.store'), $payload)->assertRedirect();
        $this->actingAs($usuario)->post(route('app.transferencias.store'), $payload)
            ->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Transferencia)->count());
    }

    public function test_rechaza_tipo_gasto_en_movimientos(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)
            ->from(route('app.pagos.index', ['pago' => 1]))
            ->post(route('app.pagos.store'), [
                'tipo' => 'gasto',
                'fecha' => now()->toDateString(),
                'monto' => '10000',
                'cuenta_liquida_id' => $cuenta->id,
                'categoria_id' => $cat->id,
                'destino' => 'X',
                'referencia' => 'G-1',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrorsIn('pago', ['tipo']);
    }

    public function test_index_muestra_deudas_activas_en_formulario(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);

        app(PrestamoService::class)->crear(
            $usuario->id,
            'Crédito visible',
            500_000_00,
            0,
            3,
            now()->toDateString(),
            10,
            (int) $cuenta->id
        );

        $this->actingAs($usuario)
            ->get(route('app.pagos.index', ['pago' => 1]))
            ->assertOk()
            ->assertSee('Crédito visible')
            ->assertSee('Pagar a terceros')
            ->assertSee('Entre mis cuentas');
    }
}
