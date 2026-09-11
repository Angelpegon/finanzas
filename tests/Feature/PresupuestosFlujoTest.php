<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\Presupuesto;
use App\Models\User;
use App\Services\PagoService;
use App\Services\TesoreriaService;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class PresupuestosFlujoTest extends TestCase
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

    public function test_guardar_y_seguimiento_del_mes_elegido(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->where('nombre', 'Compras')->firstOrFail();
        $anio = (int) now()->year;
        $mes = 3;

        $payload = [
            'anio' => $anio,
            'mes' => $mes,
            'umbrales' => [80, 100],
            'categoria_ids' => [$cat->id],
            'lineas' => ['500000'],
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($usuario)
            ->post(route('app.presupuestos.store'), $payload)
            ->assertRedirect(route('app.presupuestos.index', ['anio' => $anio, 'mes' => $mes]));

        $presupuesto = Presupuesto::where('usuario_id', $usuario->id)->where('anio', $anio)->where('mes', $mes)->firstOrFail();
        $this->assertSame(500_000_00, (int) $presupuesto->lineas->first()->tope_centavos);

        $this->actingAs($usuario)
            ->get(route('app.presupuestos.index', ['anio' => $anio, 'mes' => $mes]))
            ->assertOk()
            ->assertSee('500.000', false)
            ->assertSee('Seguimiento');
    }

    public function test_consumo_incluye_pago_generico_y_excluye_reverso(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->where('nombre', 'Compras')->firstOrFail();

        $this->actingAs($usuario)->post(route('app.presupuestos.store'), [
            'anio' => now()->year,
            'mes' => now()->month,
            'umbrales' => [100],
            'categoria_ids' => [$cat->id],
            'lineas' => ['1000000'],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        app(PagoService::class)->registrar(
            $usuario->id,
            'deuda_personal',
            120_000_00,
            (int) $cuenta->id,
            now()->toDateString(),
            'Tienda',
            'REF-PRES-1',
            null,
            (int) $cat->id
        );

        $html = $this->actingAs($usuario)
            ->get(route('app.presupuestos.index', ['anio' => now()->year, 'mes' => now()->month]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('120.000', $html);

        $pago = \App\Models\Pago::where('referencia', 'REF-PRES-1')->firstOrFail();
        app(PagoService::class)->corregir($usuario->id, (int) $pago->id, now()->toDateString(), 'error');

        $consumo = app(\App\Services\PresupuestoService::class)->consumo(
            $usuario->id,
            (int) $cat->id,
            (int) now()->year,
            (int) now()->month
        );
        $this->assertSame(0, $consumo);
    }

    public function test_crear_y_editar_categoria(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();

        $this->actingAs($usuario)
            ->post(route('app.categorias.store'), [
                'nombre' => 'Mascotas',
                'tipo' => 'gasto',
                'idempotency_key' => (string) Str::uuid(),
                'anio' => now()->year,
                'mes' => now()->month,
            ])
            ->assertRedirect();

        $cat = Categoria::where('usuario_id', $usuario->id)->where('nombre', 'Mascotas')->firstOrFail();
        $this->assertSame('gasto', $cat->tipo);

        $this->actingAs($usuario)
            ->put(route('app.categorias.update', $cat), [
                'nombre' => 'Mascotas y vet',
                'anio' => now()->year,
                'mes' => now()->month,
            ])
            ->assertRedirect();

        $this->assertSame('Mascotas y vet', $cat->fresh()->nombre);
    }

    public function test_index_respeta_query_de_mes(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cat = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        app(\App\Services\PresupuestoService::class)->guardar(
            $usuario->id,
            (int) now()->year,
            1,
            [$cat->id => 200_000_00],
            [100]
        );

        $this->actingAs($usuario)
            ->get(route('app.presupuestos.index', ['anio' => now()->year, 'mes' => 1]))
            ->assertOk()
            ->assertSee('200.000', false);

        $this->actingAs($usuario)
            ->get(route('app.presupuestos.index', ['anio' => now()->year, 'mes' => 2]))
            ->assertOk()
            ->assertSee('Aún no hay presupuesto');
    }
}
