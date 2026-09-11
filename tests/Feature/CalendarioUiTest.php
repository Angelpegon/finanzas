<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class CalendarioUiTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_calendario_muestra_grilla_y_agenda(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-06', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();

        $response = $this->actingAs($user)->get(route('app.calendario'));
        $response->assertOk();
        $response->assertSee('cal-month', false);
        $response->assertSee('cal-agenda', false);
        $response->assertSee('cal-day', false);
        $response->assertSee('Septiembre 2026');
        $response->assertSee('Vencidos');
        $response->assertSee('Proyectados');
        $response->assertSee('Reales');
        $response->assertSee('Ingresos reales');
        $response->assertSee('Salidas reales');
        $response->assertSee('Compromisos');

        Carbon::setTestNow();
    }

    public function test_calendario_respeta_dia_seleccionado_en_query(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-06', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();

        $response = $this->actingAs($user)->get(route('app.calendario', [
            'anio' => 2026,
            'mes' => 9,
            'dia' => '2026-09-15',
        ]));
        $response->assertOk();
        $response->assertSee('dia=2026-09-15', false);
        $response->assertSee('is-selected', false);
        $response->assertSee('15 de septiembre', false);

        Carbon::setTestNow();
    }

    public function test_calendario_ignora_dia_fuera_del_mes(): void
    {
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-06', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();

        $response = $this->actingAs($user)->get(route('app.calendario', [
            'anio' => 2026,
            'mes' => 9,
            'dia' => '2026-08-01',
        ]));
        $response->assertOk();
        // Mes actual → cae en hoy
        $response->assertSee('dia=2026-09-06', false);

        Carbon::setTestNow();
    }
}
