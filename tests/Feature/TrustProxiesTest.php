<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__proxy-probe', fn (Request $request) => response()->json([
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
        ]));
    }

    public function test_lista_de_proxies_sale_de_config_y_no_de_env(): void
    {
        config(['app.trusted_proxies' => '10.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.1'])
            ->getJson('/__proxy-probe')
            ->assertExactJson(['secure' => false, 'ip' => '203.0.113.9']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '198.51.100.1'])
            ->getJson('/__proxy-probe')
            ->assertExactJson(['secure' => true, 'ip' => '198.51.100.1']);
    }

    public function test_asterisco_confia_en_cualquier_proxy(): void
    {
        config(['app.trusted_proxies' => '*']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->getJson('/__proxy-probe')
            ->assertJson(['secure' => true]);
    }

    public function test_parseo_de_lista(): void
    {
        $this->assertSame('*', TrustProxies::proxiesDesdeConfig(null));
        $this->assertSame('*', TrustProxies::proxiesDesdeConfig(' '));
        $this->assertSame('*', TrustProxies::proxiesDesdeConfig('*'));
        $this->assertSame(['127.0.0.1', '::1'], TrustProxies::proxiesDesdeConfig(' 127.0.0.1, ::1 ,'));
    }
}
