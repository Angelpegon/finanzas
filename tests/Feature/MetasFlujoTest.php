<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\MetaAhorro;
use App\Models\User;
use App\Services\MetaAhorroService;
use App\Services\TesoreriaService;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class MetasFlujoTest extends TestCase
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

    public function test_crear_aportar_retirar_editar_y_corregir(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);

        $this->actingAs($usuario)
            ->post(route('app.metas.store'), [
                'nombre' => 'Viaje',
                'objetivo' => '500000',
                'aporte_mensual' => '50000',
                'fecha_objetivo' => now()->addMonths(6)->toDateString(),
                'cuenta_liquida_id' => $cuenta->id,
                'prioridad' => 'alta',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.metas.index'));

        $meta = MetaAhorro::where('usuario_id', $usuario->id)->where('nombre', 'Viaje')->firstOrFail();
        $this->assertSame('activa', $meta->estado);
        $this->assertNotSame((int) $cuenta->id, (int) $meta->cuenta_liquida_id);

        $this->actingAs($usuario)
            ->post(route('app.metas.aportes.store'), [
                'meta_ahorro_id' => $meta->id,
                'cuenta_liquida_id' => $cuenta->id,
                'monto' => '100000',
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.metas.index', ['aporte' => 1]));

        $this->assertSame(100_000_00, $meta->fresh()->monto_actual_centavos);

        $this->actingAs($usuario)
            ->put(route('app.metas.update', $meta), [
                'nombre' => 'Viaje EU',
                'objetivo' => '800000',
                'fecha_objetivo' => now()->addYear()->toDateString(),
                'aporte_mensual' => '60000',
                'prioridad' => 'media',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.metas.index'));

        $this->assertSame('Viaje EU', $meta->fresh()->nombre);
        $this->assertSame(800_000_00, (int) $meta->fresh()->objetivo_centavos);

        $this->actingAs($usuario)
            ->post(route('app.metas.retiros.store'), [
                'meta_ahorro_id' => $meta->id,
                'cuenta_destino_id' => $cuenta->id,
                'monto' => '30000',
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('app.metas.index'));

        $this->assertSame(70_000_00, $meta->fresh()->monto_actual_centavos);

        $retiro = HechoTesoreria::where('usuario_id', $usuario->id)
            ->where('tipo', TipoHechoTesoreria::RetiroMeta)
            ->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.metas.movimientos.corregir', $retiro), ['motivo' => 'Error'])
            ->assertRedirect(route('app.metas.index'));

        $this->assertSame(100_000_00, $meta->fresh()->monto_actual_centavos);
    }

    public function test_rechaza_aporte_desde_bolsillo_via_http(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $svc = app(MetaAhorroService::class);
        $meta1 = $svc->crear($usuario->id, 'A', 100_000_00, null, 0, $cuenta->id);
        $meta2 = $svc->crear($usuario->id, 'B', 100_000_00, null, 0, $cuenta->id);
        $svc->aportar($usuario->id, $meta1->id, 50_000_00, $cuenta->id, now()->toDateString());

        $this->actingAs($usuario)
            ->from(route('app.metas.index', ['aporte' => 1]))
            ->post(route('app.metas.aportes.store'), [
                'meta_ahorro_id' => $meta2->id,
                'cuenta_liquida_id' => $meta1->cuenta_liquida_id,
                'monto' => '10000',
                'fecha' => now()->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrorsIn('aporte', ['cuenta_liquida_id']);
    }

    public function test_idempotencia_aporte(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $meta = app(MetaAhorroService::class)->crear($usuario->id, 'X', 100_000_00, null, 0, $cuenta->id);
        $clave = (string) Str::uuid();
        $payload = [
            'meta_ahorro_id' => $meta->id,
            'cuenta_liquida_id' => $cuenta->id,
            'monto' => '10000',
            'fecha' => now()->toDateString(),
            'idempotency_key' => $clave,
        ];

        $this->actingAs($usuario)->post(route('app.metas.aportes.store'), $payload)->assertRedirect();
        $this->actingAs($usuario)->post(route('app.metas.aportes.store'), $payload)
            ->assertSessionHasErrors('idempotency_key');
        $this->assertSame(1, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::AporteMeta)->count());
    }

    public function test_index_muestra_tabs_y_metas(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        app(MetaAhorroService::class)->crear($usuario->id, 'Visible', 100_000_00, null, 0, $cuenta->id);

        $this->actingAs($usuario)
            ->get(route('app.metas.index'))
            ->assertOk()
            ->assertSee('Visible')
            ->assertSee('Nueva meta')
            ->assertSee('Aportar')
            ->assertSee('Retirar')
            ->assertSee('Editar')
            ->assertDontSee('Cancelada');
    }
}
