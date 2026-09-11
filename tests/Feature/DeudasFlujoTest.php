<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use App\Services\TesoreriaService;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class DeudasFlujoTest extends TestCase
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

    private function payloadCrear(User $usuario, CuentaLiquida $cuenta, array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Crédito prueba',
            'entidad' => 'Banco Test',
            'tipo_obligacion' => 'prestamo_bancario',
            'monto_inicial' => '1000000',
            'tasa_interes' => '12',
            'tipo_tasa' => 'ea',
            'metodo_amortizacion' => 'frances',
            'seguro' => '0',
            'otros_cargos' => '0',
            'fecha_inicio' => now()->toDateString(),
            'periodicidad' => 'mensual',
            'numero_cuotas' => '6',
            'dia_pago' => '15',
            'cuenta_liquida_id' => $cuenta->id,
            'idempotency_key' => (string) Str::uuid(),
        ], $extra);
    }

    public function test_crear_lista_pagar_y_corregir_pago(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);

        $this->actingAs($usuario)
            ->post(route('app.deudas.store'), $this->payloadCrear($usuario, $cuenta))
            ->assertRedirect(route('app.deudas.index'));

        $prestamo = Prestamo::where('usuario_id', $usuario->id)->where('nombre', 'Crédito prueba')->firstOrFail();
        $this->assertSame(15, (int) $prestamo->dia_pago);
        $this->assertGreaterThan(0, $prestamo->cuotas()->count());

        $this->actingAs($usuario)
            ->get(route('app.deudas.index'))
            ->assertOk()
            ->assertSee('Crédito prueba')
            ->assertSee('Registrar pago');

        $cuota = $prestamo->cuotas()->where('pagada', false)->orderBy('numero')->firstOrFail();
        $total = (int) $cuota->total_centavos;

        $this->actingAs($usuario)
            ->post(route('app.deudas.pagos.store'), [
                'prestamo_id' => $prestamo->id,
                'monto' => (string) ($total / 100),
                'fecha' => now()->toDateString(),
                'cuenta_liquida_id' => $cuenta->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.deudas.index'));

        $pago = Pago::where('usuario_id', $usuario->id)->where('prestamo_id', $prestamo->id)->firstOrFail();
        $this->assertSame((int) $cuota->id, (int) $pago->cuota_prestamo_id);
        $this->assertTrue($cuota->fresh()->pagada);

        $this->actingAs($usuario)
            ->get(route('app.deudas.index'))
            ->assertOk()
            ->assertSee('Pagos recientes')
            ->assertSee('Corregir');

        $this->actingAs($usuario)
            ->post(route('app.deudas.pagos.corregir', $pago), ['motivo' => 'Monto erróneo'])
            ->assertRedirect(route('app.deudas.index'));

        $this->assertFalse($cuota->fresh()->pagada);
        $this->assertTrue(
            Asiento::withoutGlobalScopes()
                ->where('usuario_id', $usuario->id)
                ->where('es_reverso', true)
                ->exists()
        );
    }

    public function test_abono_extra_y_correccion_restaura_cronograma(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta, 50_000_000);

        $this->actingAs($usuario)
            ->post(route('app.deudas.store'), $this->payloadCrear($usuario, $cuenta, [
                'monto_inicial' => '5000000',
                'numero_cuotas' => '12',
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertRedirect(route('app.deudas.index'));

        $prestamo = Prestamo::where('usuario_id', $usuario->id)->latest('id')->firstOrFail();
        $pendientesAntes = $prestamo->cuotas()->where('pagada', false)->count();
        $cuota = $prestamo->cuotas()->orderBy('numero')->firstOrFail();
        $total = (int) $cuota->total_centavos;
        $extra = 500_000_00;

        $this->actingAs($usuario)
            ->post(route('app.deudas.pagos.store'), [
                'prestamo_id' => $prestamo->id,
                'monto' => (string) (($total + $extra) / 100),
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.deudas.index'));

        $pago = Pago::where('prestamo_id', $prestamo->id)->firstOrFail();
        $this->assertTrue($pago->extraordinario);
        $this->assertNotEmpty($pago->cronograma_snapshot);
        $pendientesDespues = CuotaPrestamo::where('prestamo_id', $prestamo->id)->where('pagada', false)->count();
        $this->assertLessThan($pendientesAntes - 1, $pendientesDespues);

        $this->actingAs($usuario)
            ->post(route('app.deudas.pagos.corregir', $pago))
            ->assertRedirect(route('app.deudas.index'));

        $this->assertFalse($cuota->fresh()->pagada);
        $this->assertSame(
            $pendientesAntes,
            CuotaPrestamo::where('prestamo_id', $prestamo->id)->where('pagada', false)->count()
        );
    }

    public function test_doble_pago_con_misma_idempotencia_falla(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);

        $this->actingAs($usuario)->post(route('app.deudas.store'), $this->payloadCrear($usuario, $cuenta, [
            'idempotency_key' => 'deuda-doble-1',
        ]))->assertRedirect(route('app.deudas.index'));

        $this->actingAs($usuario)
            ->from(route('app.deudas.create'))
            ->post(route('app.deudas.store'), $this->payloadCrear($usuario, $cuenta, [
                'idempotency_key' => 'deuda-doble-1',
            ]))
            ->assertRedirect(route('app.deudas.create'))
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Prestamo::where('usuario_id', $usuario->id)->count());

        $prestamo = Prestamo::where('usuario_id', $usuario->id)->firstOrFail();
        $total = (int) $prestamo->cuotas()->orderBy('numero')->firstOrFail()->total_centavos;
        $payload = [
            'prestamo_id' => $prestamo->id,
            'monto' => (string) ($total / 100),
            'fecha' => now()->toDateString(),
            'idempotency_key' => 'pago-doble-1',
        ];

        $this->actingAs($usuario)->post(route('app.deudas.pagos.store'), $payload)->assertRedirect(route('app.deudas.index'));
        $this->actingAs($usuario)
            ->from(route('app.deudas.index'))
            ->post(route('app.deudas.pagos.store'), $payload)
            ->assertRedirect(route('app.deudas.index'))
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, Pago::where('prestamo_id', $prestamo->id)->count());
        $this->assertSame(1, $prestamo->cuotas()->where('pagada', true)->count());
    }

    public function test_rechaza_tipo_tarjeta_credito_en_prestamo(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)
            ->from(route('app.deudas.create'))
            ->post(route('app.deudas.store'), $this->payloadCrear($usuario, $cuenta, [
                'tipo_obligacion' => 'tarjeta_credito',
            ]))
            ->assertSessionHasErrors('tipo_obligacion');
    }

    public function test_create_sin_cuentas_muestra_cta(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        CuentaLiquida::where('usuario_id', $usuario->id)->update(['activa' => false, 'estado' => 'inactiva']);

        $this->actingAs($usuario)
            ->get(route('app.deudas.create'))
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertDontSee('name="monto_inicial"', false);
    }

    public function test_pago_desde_otra_cuenta_operativa(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuentaA = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuentaA, 5_000_000);

        $cuentaB = CuentaLiquida::withoutGlobalScopes()->create([
            'usuario_id' => $usuario->id,
            'cuenta_contable_id' => $cuentaA->cuenta_contable_id,
            'nombre' => 'Nequi pago',
            'tipo' => 'billetera',
            'activa' => true,
            'estado' => 'activa',
        ]);
        // Separate contable for balance
        $pasivoLike = \App\Models\CuentaContable::withoutGlobalScopes()->create([
            'usuario_id' => $usuario->id,
            'codigo' => '1101',
            'nombre' => 'Nequi contable',
            'naturaleza' => 'activo',
        ]);
        $cuentaB->update(['cuenta_contable_id' => $pasivoLike->id]);
        $this->fondear($usuario, $cuentaB->fresh(), 5_000_000);

        $this->actingAs($usuario)->post(route('app.deudas.store'), $this->payloadCrear($usuario, $cuentaA, [
            'monto_inicial' => '500000',
            'numero_cuotas' => '3',
            'idempotency_key' => (string) Str::uuid(),
        ]))->assertRedirect(route('app.deudas.index'));

        $prestamo = Prestamo::where('usuario_id', $usuario->id)->latest('id')->firstOrFail();
        $total = (int) $prestamo->cuotas()->orderBy('numero')->firstOrFail()->total_centavos;

        $this->actingAs($usuario)
            ->post(route('app.deudas.pagos.store'), [
                'prestamo_id' => $prestamo->id,
                'monto' => (string) ($total / 100),
                'fecha' => now()->toDateString(),
                'cuenta_liquida_id' => $cuentaB->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.deudas.index'));

        $pago = Pago::where('prestamo_id', $prestamo->id)->firstOrFail();
        $this->assertSame((int) $cuentaB->id, (int) $pago->cuenta_liquida_id);
    }
}
