<?php

namespace Tests\Feature;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Services\SituacionFinancieraService;
use App\Services\TesoreriaService;
use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class DashboardSituacionTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_dashboard_expone_widgets_sin_graficas_y_campana(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo(['nombre' => 'Andrés']);

        $this->actingAs($usuario)
            ->get(route('app.situacion'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Ingresos del mes')
            ->assertSee('Próximos pagos')
            ->assertSee('Presupuesto')
            ->assertSee('Metas de ahorro')
            ->assertSee('Calendario financiero')
            ->assertSee('Cuentas')
            ->assertSee('Movimientos recientes')
            ->assertSee('Acciones rápidas')
            ->assertSee('header-bell', false)
            ->assertSee('fa-bell', false)
            ->assertSee('data-bs-toggle="dropdown"', false)
            ->assertSee('data-bs-toggle="offcanvas"', false)
            ->assertDontSee('Flujo de caja')
            ->assertDontSee('Gastos por categoría');
    }

    public function test_variacion_mom_de_ingresos_es_real(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $usuario->id)->firstOrFail();
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();
        $tesoreria = app(TesoreriaService::class);

        Carbon::setTestNow(Carbon::parse('2026-03-15', 'America/Bogota'));

        $tesoreria->registrar(
            $usuario->id,
            TipoHechoTesoreria::Ingreso,
            '2026-02-10',
            1_000_000_00,
            $cuenta->id,
            $cat->id,
            null,
            'Salario febrero'
        );
        $tesoreria->registrar(
            $usuario->id,
            TipoHechoTesoreria::Ingreso,
            '2026-03-10',
            1_200_000_00,
            $cuenta->id,
            $cat->id,
            null,
            'Salario marzo'
        );

        $datos = app(SituacionFinancieraService::class)->responder($usuario->id, Carbon::parse('2026-03-15'), true);

        $this->assertSame(1_200_000_00, $datos['ingresos_mes_centavos']);
        $this->assertSame(20.0, $datos['variaciones']['ingresos_porcentaje']);
        $this->assertArrayHasKey('calendario_grilla', $datos);
        $this->assertNotEmpty($datos['calendario_grilla']['celdas']);

        Carbon::setTestNow();
    }
}
