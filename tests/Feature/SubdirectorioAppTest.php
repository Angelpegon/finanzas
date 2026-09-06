<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SubdirectorioAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://ingeer.co/finanzas']);
        config(['session.path' => '/finanzas']);
        URL::forceRootUrl('https://ingeer.co/finanzas');
        URL::forceScheme('https');
    }

    public function test_raiz_de_subcarpeta_redirige_a_login(): void
    {
        // MakesHttpRequests antepone APP_URL: get('/') → https://ingeer.co/finanzas
        $this->get('/')
            ->assertRedirect('https://ingeer.co/finanzas/login');

        $this->assertSame('https://ingeer.co/finanzas/login', route('login'));
    }

    public function test_login_responde_bajo_el_prefijo(): void
    {
        $this->withoutVite();

        $this->assertSame('/finanzas', \App\Support\UrlPrefix::basePath());

        // get('/login') → https://ingeer.co/finanzas/login
        $this->get('/login')
            ->assertOk()
            ->assertSee('app-base-path', false)
            ->assertSee('content="/finanzas"', false);
    }

    public function test_manifest_y_sw_usan_el_prefijo(): void
    {
        $manifiesto = $this->get('/manifest.json')->assertOk()->json();

        $this->assertSame('/finanzas/', $manifiesto['start_url']);
        $this->assertSame('/finanzas/', $manifiesto['scope']);
        $this->assertStringStartsWith('/finanzas/icons/', $manifiesto['icons'][0]['src']);

        $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Service-Worker-Allowed', '/finanzas/');

        $this->get('/offline.html')
            ->assertOk()
            ->assertSee('href="/finanzas/"', false);
    }

    public function test_full_url_e_intended_conservan_el_prefijo(): void
    {
        $this->get('/situacion')->assertRedirect('https://ingeer.co/finanzas/login');

        $intended = session('url.intended');
        $this->assertNotNull($intended);
        $this->assertStringContainsString('/finanzas/situacion', $intended);
        $this->assertStringNotContainsString('/finanzas/finanzas/', $intended);
    }

    public function test_asset_y_vite_usan_app_url_con_path(): void
    {
        $this->assertSame('https://ingeer.co/finanzas/build/x.js', asset('build/x.js'));
        $this->assertSame('https://ingeer.co/finanzas/assets/css/fonts.css', asset('assets/css/fonts.css'));
    }
}
