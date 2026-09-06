<?php

namespace Tests\Feature;

use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class PwaShellTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_manifesto_describe_una_pwa_instalable(): void
    {
        $respuesta = $this->get('/manifest.json');
        $respuesta->assertOk();

        $manifiesto = json_decode($respuesta->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifiesto['display']);
        $this->assertSame('/', $manifiesto['start_url']);
        $this->assertNotEmpty($manifiesto['icons']);
        $this->assertTrue(collect($manifiesto['icons'])->contains(fn (array $icono) => $icono['purpose'] === 'maskable'));
    }

    public function test_service_worker_cae_a_offline_y_no_cachea_escrituras(): void
    {
        $respuesta = $this->get('/sw.js');
        $respuesta->assertOk();
        $script = $respuesta->getContent();

        $this->assertStringContainsString("event.request.method !== 'GET'", $script);
        $this->assertStringContainsString('caches.match(`${BASE}/offline.html`)', $script);
        $this->assertStringContainsString('skipWaiting', $script);
        $this->assertStringContainsString('pathname.startsWith(`${BASE}/build/`)', $script);
        $this->assertStringContainsString('finanzas-pwa-v4', $script);
        $this->assertStringContainsString('self.location.pathname.replace', $script);
    }

    public function test_offline_explica_que_el_libro_no_se_posta_sin_red(): void
    {
        $this->get('/offline.html')
            ->assertOk()
            ->assertSee('Sin conexión')
            ->assertSee('append-only', false);
    }

    public function test_iconos_pwa_existen(): void
    {
        foreach (['apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'icon-192-maskable.png', 'icon-512-maskable.png'] as $archivo) {
            $this->assertFileExists(public_path('icons/'.$archivo));
        }
    }

    public function test_login_y_dashboard_exponen_el_shell_pwa(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('manifest.json', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('pwa-install', false);

        $usuario = $this->usuarioConCatalogo();

        $this->actingAs($usuario)
            ->get(route('app.situacion'))
            ->assertOk()
            ->assertSee('bottom-nav', false)
            ->assertSee('bottom-nav__fab', false)
            ->assertSee('capture-sheet', false)
            ->assertSee('tablet-nav', false)
            ->assertSee('desktop-sidebar', false)
            ->assertSee('more-sheet', false)
            ->assertSee('Nuevo gasto')
            ->assertSee('Dashboard')
            ->assertSee('Movimientos')
            ->assertSee('Metas')
            ->assertSee('user-menu', false)
            ->assertSee('Cerrar sesión')
            ->assertSee('header-bell', false)
            ->assertSee('alerts-sheet', false)
            ->assertSee('boot-splash', false)
            ->assertSee('data-swal-confirm', false);
    }

    public function test_vistas_internas_comparten_header_con_titulo(): void
    {
        $this->withoutVite();
        $usuario = $this->usuarioConCatalogo();

        foreach ([
            'app.cuentas.index' => 'Mis cuentas',
            'app.ingresos.create' => '¿Cuánto recibiste?',
            'app.gastos.create' => '¿En qué gastaste?',
            'app.pagos.index' => 'Transferencias y pagos',
            'app.metas.index' => 'Mis metas',
            'app.presupuestos.index' => 'Mi presupuesto',
            'app.calendario' => 'Calendario',
            'app.proyecciones' => 'Proyecciones',
            'app.deudas.index' => 'Mis deudas',
            'app.tarjetas.index' => 'Mis tarjetas',
        ] as $ruta => $encabezado) {
            $this->actingAs($usuario)
                ->get(route($ruta))
                ->assertOk()
                ->assertSee('app-header', false)
                ->assertSee('header-bell', false)
                ->assertSee('user-menu', false)
                ->assertSee('app-header__titles', false)
                ->assertSee($encabezado)
                ->assertDontSee('card border-0 shadow-sm', false);
        }

        $this->actingAs($usuario)
            ->get(route('app.presupuestos.index'))
            ->assertSee('budget-line', false)
            ->assertDontSee('input-group-text', false);

        $html = $this->actingAs($usuario)->get(route('app.situacion'))->getContent();
        $this->assertStringContainsString('bottom-nav__fab', $html);
        $this->assertStringContainsString('capture-sheet', $html);
        $this->assertMatchesRegularExpression('/<nav class="bottom-nav"[^>]*>[\s\S]*Dashboard[\s\S]*Movimientos[\s\S]*Metas[\s\S]*Más/u', $html);
    }

    public function test_el_shell_carga_vendor_estatico_como_ambientes(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('assets/css/fonts.css', false)
            ->assertSee('assets/css/fontawesome/css/all.min.css', false)
            ->assertSee('assets/css/bootstrap/css/bootstrap.min.css', false)
            ->assertSee('assets/css/sweetalert2.min.css', false)
            ->assertSee('assets/js/jquery-4.0.0.min.js', false)
            ->assertSee('assets/css/bootstrap/js/bootstrap.bundle.min.js', false)
            ->assertSee('assets/js/sweetalert.js', false);

        foreach ([
            'assets/css/fonts.css',
            'assets/css/fontawesome/css/all.min.css',
            'assets/css/bootstrap/css/bootstrap.min.css',
            'assets/css/bootstrap/js/bootstrap.bundle.min.js',
            'assets/css/sweetalert2.min.css',
            'assets/js/jquery-4.0.0.min.js',
            'assets/js/sweetalert.js',
            'assets/fonts/Nunito/Nunito.woff2',
            'assets/fonts/Fredoka/FredokaOne.woff2',
        ] as $archivo) {
            $this->assertFileExists(public_path($archivo));
        }
    }
}
