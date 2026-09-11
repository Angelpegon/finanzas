<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\CuentaLiquida;
use App\Services\CuentaLiquidaService;
use App\Services\MetaAhorroService;
use App\Services\TesoreriaService;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class CuentasGestionTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_index_oculta_bolsillos_y_permite_restaurar(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $meta = app(MetaAhorroService::class)->crear(
            $usuario->id, 'Viaje', 100_000_00, null, 0, $cuenta->id
        );
        $bolsillo = $meta->cuentaLiquida;

        app(CuentaLiquidaService::class)->archivar($usuario->id, $cuenta);

        $html = $this->actingAs($usuario)
            ->get(route('app.cuentas.index'))
            ->assertOk()
            ->assertSee('Archivadas')
            ->assertSee($cuenta->nombre)
            ->assertSee('Restaurar')
            ->assertSee('Nueva cuenta')
            ->assertDontSee($bolsillo->nombre)
            ->getContent();

        $this->assertStringContainsString('page-toolbar', $html);

        $this->actingAs($usuario)
            ->post(route('app.cuentas.restore', $cuenta))
            ->assertRedirect(route('app.cuentas.index'));

        $this->assertTrue($cuenta->fresh()->activa);
        $this->assertSame('activa', $cuenta->fresh()->estado);
    }

    public function test_cancelar_con_baja_desaparece_del_index_y_del_show(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $extra = app(CuentaLiquidaService::class)->crear($usuario->id, [
            'nombre' => 'Temporal',
            'tipo' => 'efectivo',
            'saldo_inicial' => '50000',
        ]);

        $this->actingAs($usuario)
            ->post(route('app.cuentas.cancelar', $extra), ['disposicion' => 'baja'])
            ->assertRedirect(route('app.cuentas.index'))
            ->assertSessionHas('status');

        $extra->refresh();
        $this->assertSame('cancelada', $extra->estado);
        $this->assertSame(0, $extra->saldoCentavos());

        $this->actingAs($usuario)
            ->get(route('app.cuentas.index'))
            ->assertOk()
            ->assertDontSee('Temporal');

        $this->actingAs($usuario)
            ->get(route('app.cuentas.show', $extra))
            ->assertNotFound();
    }

    public function test_archivar_con_saldo_exige_destino_http(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $usuario->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 40_000_00, $cuenta->id
        );

        $this->actingAs($usuario)
            ->from(route('app.cuentas.index'))
            ->delete(route('app.cuentas.destroy', $cuenta))
            ->assertRedirect(route('app.cuentas.index'))
            ->assertSessionHasErrors('cuenta_destino_id');
    }

    public function test_editar_y_ver_detalle(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();

        $this->actingAs($usuario)
            ->put(route('app.cuentas.update', $cuenta), [
                'nombre' => 'Efectivo bolsillo casa',
                'tipo' => 'efectivo',
                'institucion' => 'Hogar',
                'numero_cuenta_enmascarado' => '**** 0001',
            ])
            ->assertRedirect(route('app.cuentas.show', $cuenta));

        $this->assertSame('Efectivo bolsillo casa', $cuenta->fresh()->nombre);

        $this->actingAs($usuario)
            ->get(route('app.cuentas.show', $cuenta))
            ->assertOk()
            ->assertSee('Efectivo bolsillo casa')
            ->assertSee('Movimientos de tesorería')
            ->assertSee('Efectivo');
    }
}
