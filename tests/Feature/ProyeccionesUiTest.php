<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class ProyeccionesUiTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_proyecciones_muestra_residual_y_sin_fila_pagos(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-10', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();

        $response = $this->actingAs($user)->get(route('app.proyecciones', ['meses' => 3]));
        $response->assertOk();
        $response->assertSee('Residual del mes');
        $response->assertSee('Ingresos esperados');
        $response->assertDontSee('Pagos programados');
        $response->assertDontSee('Disponible proyectado');
        $response->assertSee('Septiembre 2026');

        Carbon::setTestNow();
    }
}
