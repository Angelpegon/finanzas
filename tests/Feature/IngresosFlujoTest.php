<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\Recurrencia;
use App\Support\AgregadosLibro;
use Illuminate\Support\Str;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class IngresosFlujoTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_ingreso_real_lista_y_corrige_con_reverso(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.ingresos.store'), [
                'monto' => '250000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'descripcion' => 'Nómina prueba',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.ingresos.index'));

        $hecho = HechoTesoreria::where('usuario_id', $usuario->id)
            ->where('tipo', TipoHechoTesoreria::Ingreso)
            ->where('descripcion', 'Nómina prueba')
            ->firstOrFail();

        $this->actingAs($usuario)
            ->get(route('app.ingresos.index'))
            ->assertOk()
            ->assertSee('Nómina prueba')
            ->assertSee('Corregir');

        $this->actingAs($usuario)
            ->post(route('app.ingresos.corregir', $hecho), ['motivo' => 'Monto erróneo'])
            ->assertRedirect(route('app.ingresos.index'));

        $inicio = now()->startOfMonth();
        $fin = now()->endOfMonth();
        $this->assertSame(0, AgregadosLibro::ingresosReales($usuario->id, $inicio, $fin));

        $this->actingAs($usuario)
            ->get(route('app.ingresos.index'))
            ->assertOk()
            ->assertSee('Corregido');
    }

    public function test_recurrente_con_cobro_crea_hecho_y_recurrencia(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.ingresos.store'), [
                'monto' => '1000000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'descripcion' => 'Salario',
                'recurrente' => '1',
                'recibido' => '1',
                'periodicidad' => 'mensual',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.ingresos.index'));

        $this->assertSame(1, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Ingreso)->count());
        $this->assertSame(1, Recurrencia::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->count());
    }

    public function test_recurrente_sin_cobro_solo_proyeccion(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();

        $this->actingAs($usuario)
            ->post(route('app.ingresos.store'), [
                'monto' => '800000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'descripcion' => 'Freelance futuro',
                'recurrente' => '1',
                'periodicidad' => 'mensual',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('app.ingresos.index'));

        $this->assertSame(0, HechoTesoreria::where('usuario_id', $usuario->id)->where('tipo', TipoHechoTesoreria::Ingreso)->count());
        $this->assertSame(1, Recurrencia::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->count());
    }

    public function test_doble_post_con_misma_idempotencia_falla(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();
        $payload = [
            'monto' => '50000',
            'fecha' => now()->toDateString(),
            'categoria_id' => $categoria->id,
            'cuenta_liquida_id' => $cuenta->id,
            'descripcion' => 'Doble',
            'idempotency_key' => 'misma-clave-doble-1',
        ];

        $this->actingAs($usuario)->post(route('app.ingresos.store'), $payload)->assertRedirect(route('app.ingresos.index'));
        $this->actingAs($usuario)
            ->from(route('app.ingresos.create'))
            ->post(route('app.ingresos.store'), $payload)
            ->assertRedirect(route('app.ingresos.create'))
            ->assertSessionHasErrors('idempotency_key');

        $this->assertSame(1, HechoTesoreria::where('usuario_id', $usuario->id)->where('descripcion', 'Doble')->count());
    }

    public function test_recurrente_rechaza_periodicidad_unica(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();

        $this->actingAs($usuario)
            ->from(route('app.ingresos.create'))
            ->post(route('app.ingresos.store'), [
                'monto' => '10000',
                'fecha' => now()->toDateString(),
                'categoria_id' => $categoria->id,
                'cuenta_liquida_id' => $cuenta->id,
                'recurrente' => '1',
                'periodicidad' => 'unico',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('periodicidad');
    }

    public function test_create_sin_cuentas_muestra_cta(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        CuentaLiquida::where('usuario_id', $usuario->id)->update(['activa' => false, 'estado' => 'inactiva']);

        $this->actingAs($usuario)
            ->get(route('app.ingresos.create'))
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertDontSee('name="monto"', false);
    }
}
