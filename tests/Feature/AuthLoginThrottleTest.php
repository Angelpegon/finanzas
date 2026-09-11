<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class AuthLoginThrottleTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_login_bloquea_tras_cinco_fallos(): void
    {
        $usuario = $this->usuarioConCatalogo([
            'email' => 'auth-throttle@example.com',
            'password' => 'secreto123',
        ]);

        $payload = [
            'email' => $usuario->email,
            'password' => 'incorrecta',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))
                ->post(route('auth.login'), $payload)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->post(route('auth.login'), $payload)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Demasiados intentos',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_login_exitoso_limpia_contador_de_fallos(): void
    {
        $usuario = $this->usuarioConCatalogo([
            'email' => 'auth-clear@example.com',
            'password' => 'secreto123',
        ]);

        $key = mb_strtolower($usuario->email).'|127.0.0.1';

        for ($i = 0; $i < 3; $i++) {
            $this->from(route('login'))
                ->post(route('auth.login'), [
                    'email' => $usuario->email,
                    'password' => 'incorrecta',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->assertSame(3, RateLimiter::attempts($key));

        $this->from(route('login'))
            ->post(route('auth.login'), [
                'email' => $usuario->email,
                'password' => 'secreto123',
            ])
            ->assertRedirect(route('app.situacion'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame(0, RateLimiter::attempts($key));
    }
}
