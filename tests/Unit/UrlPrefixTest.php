<?php

namespace Tests\Unit;

use App\Support\UrlPrefix;
use Tests\TestCase;

class UrlPrefixTest extends TestCase
{
    public function test_sin_path_en_app_url(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->assertSame('', UrlPrefix::segment());
        $this->assertSame('', UrlPrefix::basePath());
        $this->assertSame('/', UrlPrefix::sessionPath());
        $this->assertSame('/', UrlPrefix::urlPath('/'));
        $this->assertSame('/login', UrlPrefix::urlPath('login'));
    }

    public function test_con_subcarpeta(): void
    {
        config(['app.url' => 'https://ingeer.co/finanzas']);

        $this->assertSame('finanzas', UrlPrefix::segment());
        $this->assertSame('/finanzas', UrlPrefix::basePath());
        $this->assertSame('/finanzas', UrlPrefix::sessionPath());
        $this->assertSame('/finanzas/', UrlPrefix::urlPath('/'));
        $this->assertSame('/finanzas/login', UrlPrefix::urlPath('/login'));
    }
}
