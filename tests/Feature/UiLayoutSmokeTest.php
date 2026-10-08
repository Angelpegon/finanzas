<?php

namespace Tests\Feature;

use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class UiLayoutSmokeTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_login_y_registro_usan_layout_auth_moderno(): void
    {
        $login = $this->get(route('login'));
        $login->assertOk();
        $login->assertSee('auth-shell', false);
        $login->assertSee('auth-actions', false);
        $login->assertSee('Bienvenido de nuevo');

        $registro = $this->get(route('register'));
        $registro->assertOk();
        $registro->assertSee('auth-actions', false);
        $registro->assertSee('Crea tu espacio');
        $registro->assertSee('password_confirmation', false);
    }

    public function test_formularios_de_captura_usan_patron_money_hero_y_acciones(): void
    {
        $user = $this->usuarioConCatalogo();

        $rutas = [
            'app.ingresos.create',
            'app.gastos.create',
            'app.cuentas.create',
            'app.deudas.create',
            'app.tarjetas.create',
        ];

        foreach ($rutas as $nombre) {
            $response = $this->actingAs($user)->get(route($nombre));
            $response->assertOk();
            $response->assertSee('capture-flow', false);
            $response->assertSee('money-hero', false);
            $response->assertSee('form-actions', false);
        }
    }

    public function test_indices_con_tabs_y_listas_modernas_responden(): void
    {
        $user = $this->usuarioConCatalogo();

        $pagos = $this->actingAs($user)->get(route('app.pagos.index'));
        $pagos->assertOk();
        $pagos->assertSee('flow-tabs', false);
        $pagos->assertSee('list-block', false);
        $pagos->assertSee('Entre mis cuentas');

        $pagosPago = $this->actingAs($user)->get(route('app.pagos.index', ['pago' => 1]));
        $pagosPago->assertOk();
        $pagosPago->assertSee('Pagar a un tercero');

        $metas = $this->actingAs($user)->get(route('app.metas.index'));
        $metas->assertOk();
        $metas->assertSee('flow-tabs', false);
        $metas->assertSee('money-hero', false);
        $metas->assertSee('list-block', false);

        $metasAporte = $this->actingAs($user)->get(route('app.metas.index', ['aporte' => 1]));
        $metasAporte->assertOk();

        $presupuestos = $this->actingAs($user)->get(route('app.presupuestos.index'));
        $presupuestos->assertOk();
        $presupuestos->assertSee('form-actions', false);
        $presupuestos->assertSee('capture-flow', false);

        $cuentas = $this->actingAs($user)->get(route('app.cuentas.index'));
        $cuentas->assertOk();
        $cuentas->assertSee('list-block', false);
        $cuentas->assertSee('Activas');

        $ingresos = $this->actingAs($user)->get(route('app.ingresos.index'));
        $ingresos->assertOk();
        $ingresos->assertSee('list-block', false);
        $ingresos->assertSee('Nuevo ingreso');

        $gastos = $this->actingAs($user)->get(route('app.gastos.index'));
        $gastos->assertOk();
        $gastos->assertSee('list-block', false);
        $gastos->assertSee('Nuevo gasto');

        $deudas = $this->actingAs($user)->get(route('app.deudas.index'));
        $deudas->assertOk();
        $deudas->assertSee('list-block', false);

        $tarjetas = $this->actingAs($user)->get(route('app.tarjetas.index'));
        $tarjetas->assertOk();
        $tarjetas->assertSee('list-block', false);
        $tarjetas->assertSee('Nueva tarjeta');
        $tarjetas->assertSee('page-toolbar', false);
    }

    /**
     * Lee la fuente y no public/build: el build está en .gitignore y en local
     * suele estar desactualizado (npm run dev no lo regenera).
     */
    public function test_css_fuente_incluye_utilidades_responsive(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        foreach ([
            '.auth-actions',
            '.money-hero',
            '.form-actions',
            '.capture-flow',
            '.flow-tabs',
            '.list-block',
        ] as $needle) {
            $this->assertStringContainsString($needle, $css, "Falta {$needle} en resources/css/app.css");
        }

        $this->assertMatchesRegularExpression('/\.auth-stage\s*\{\s*display:\s*none/', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(min-width:\s*992px\)\s*\{[^@]*\.auth-stage\s*\{\s*display:\s*flex/s',
            $css
        );
    }
}
