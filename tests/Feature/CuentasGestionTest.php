<?php

namespace Tests\Feature;

use App\Models\CuentaLiquida;
use App\Services\CuentaLiquidaService;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class CuentasGestionTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_index_lista_archivadas_y_permite_restaurar(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        app(CuentaLiquidaService::class)->archivar($usuario->id, $cuenta);

        $this->actingAs($usuario)
            ->get(route('app.cuentas.index'))
            ->assertOk()
            ->assertSee('Archivadas')
            ->assertSee($cuenta->nombre)
            ->assertSee('Restaurar');

        $this->actingAs($usuario)
            ->post(route('app.cuentas.restore', $cuenta))
            ->assertRedirect(route('app.cuentas.index'));

        $this->assertTrue($cuenta->fresh()->activa);
        $this->assertSame('activa', $cuenta->fresh()->estado);
    }

    public function test_cancelar_con_baja_desde_http(): void
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
    }
}
