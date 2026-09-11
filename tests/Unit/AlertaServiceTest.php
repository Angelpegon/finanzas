<?php

namespace Tests\Unit;

use App\Models\CuotaPrestamo;
use App\Models\CuentaLiquida;
use App\Services\AlertaService;
use App\Services\PrestamoService;
use App\Services\TesoreriaService;
use App\Enums\TipoHechoTesoreria;
use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class AlertaServiceTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_alerta_vencido_incluye_enlace_a_calendario(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-20', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-01-01', 5_000_000_00, $cuenta->id
        );
        $prestamo = app(PrestamoService::class)->crear(
            $user->id, 'Atrasado', 100_000_00, 0.0, 2, '2026-02-10', 15, $cuenta->id
        );
        $this->assertTrue(
            CuotaPrestamo::withoutGlobalScopes()
                ->where('prestamo_id', $prestamo->id)
                ->where('pagada', false)
                ->whereDate('fecha_vencimiento', '<', '2026-03-20')
                ->exists()
        );

        $alertas = app(AlertaService::class)->evaluar($user->id, [
            'flujo_caja_centavos' => 0,
            'nivel_endeudamiento_porcentaje' => 0,
        ]);

        $vencido = collect($alertas)->firstWhere('titulo', 'Pago vencido');
        $this->assertNotNull($vencido);
        $this->assertNotEmpty($vencido['enlace'] ?? null);
        $this->assertStringContainsString('/calendario', (string) $vencido['enlace']);

        Carbon::setTestNow();
    }
}
