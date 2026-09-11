<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\Recurrencia;
use App\Models\User;
use App\Support\AgregadosLibro;
use App\Services\TesoreriaService;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class GastosFlujoTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    private function fondear(User $usuario, CuentaLiquida $cuenta, int $pesos = 500000): void
    {
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $usuario->id,
            TipoHechoTesoreria::Ingreso,
            now()->toDateString(),
            $pesos * 100,
            (int) $cuenta->id,
            (int) $categoria->id,
            null,
            'Fondeo test'
        );
    }

    public function test_gasto_real_lista_y_corrige_con_reverso(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();
        $this->fondear($usuario, $cuenta);

        $this->actingAs($usuario)
            ->post(route('app.gastos.store'), [
                'monto' => '45000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'tipo_gasto' => 'variable',
                'descripcion' => 'Mercado prueba',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.gastos.index'));

        $hecho = HechoTesoreria::where('usuario_id', $usuario->id)
            ->where('tipo', TipoHechoTesoreria::Gasto)
            ->where('descripcion', 'Mercado prueba')
            ->firstOrFail();

        $this->actingAs($usuario)
            ->get(route('app.gastos.index'))
            ->assertOk()
            ->assertSee('Mercado prueba')
            ->assertSee('Corregir');

        $this->actingAs($usuario)
            ->post(route('app.gastos.corregir', $hecho), ['motivo' => 'Monto erróneo'])
            ->assertRedirect(route('app.gastos.index'));

        $inicio = now()->startOfMonth();
        $fin = now()->endOfMonth();
        $this->assertSame(0, AgregadosLibro::gastosReales($usuario->id, $inicio, $fin));

        $this->actingAs($usuario)
            ->get(route('app.gastos.index'))
            ->assertOk()
            ->assertSee('Corregido');
    }

    public function test_recurrente_con_ejecucion_crea_hecho_y_recurrencia(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();
        $this->fondear($usuario, $cuenta);

        $this->actingAs($usuario)
            ->post(route('app.gastos.store'), [
                'monto' => '120000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'tipo_gasto' => 'fijo',
                'descripcion' => 'Arriendo',
                'recurrente' => '1',
                'ejecutado' => '1',
                'periodicidad' => 'mensual',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.gastos.index'));

        $this->assertSame(1, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Gasto)->count());
        $this->assertSame(1, Recurrencia::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->count());
    }

    public function test_recurrente_sin_ejecucion_solo_proyeccion(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.gastos.store'), [
                'monto' => '80000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'tipo_gasto' => 'fijo',
                'descripcion' => 'Netflix',
                'recurrente' => '1',
                'periodicidad' => 'mensual',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.gastos.index'));

        $this->assertSame(0, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Gasto)->count());
        $this->assertSame(1, Recurrencia::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->count());
    }

    public function test_doble_post_con_misma_idempotencia_falla(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();
        $this->fondear($usuario, $cuenta);
        $payload = [
            'monto' => '10000',
            'fecha' => now()->toDateString(),
            'categoria_id' => $categoria->id,
            'cuenta_liquida_id' => $cuenta->id,
            'tipo_gasto' => 'variable',
            'descripcion' => 'Doble gasto',
            'idempotency_key' => 'misma-clave-gasto-1',
        ];

        $this->actingAs($usuario)->post(route('app.gastos.store'), $payload)->assertRedirect(route('app.gastos.index'));
        $this->actingAs($usuario)
            ->from(route('app.gastos.create'))
            ->post(route('app.gastos.store'), $payload)
            ->assertRedirect(route('app.gastos.create'))
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, HechoTesoreria::where('usuario_id', $usuario->id)->where('descripcion', 'Doble gasto')->count());
    }

    public function test_recurrente_rechaza_periodicidad_unica(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)
            ->from(route('app.gastos.create'))
            ->post(route('app.gastos.store'), [
                'monto' => '10000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'tipo_gasto' => 'variable',
                'recurrente' => '1',
                'periodicidad' => 'unico',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('periodicidad');
    }

    public function test_saldo_insuficiente_muestra_error_de_formulario(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $this->actingAs($usuario)
            ->from(route('app.gastos.create'))
            ->post(route('app.gastos.store'), [
                'monto' => '999999',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'tipo_gasto' => 'variable',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.gastos.create'))
            ->assertSessionHasErrors('cuenta_liquida_id');

        $this->assertStringContainsString('saldo', mb_strtolower(session('errors')->first('cuenta_liquida_id')));
        $this->assertSame(0, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Gasto)->count());
    }

    public function test_create_sin_cuentas_muestra_cta(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        CuentaLiquida::where('usuario_id', $usuario->id)->update(['activa' => false, 'estado' => 'inactiva']);

        $this->actingAs($usuario)
            ->get(route('app.gastos.create'))
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertDontSee('name="monto"', false);
    }
}
