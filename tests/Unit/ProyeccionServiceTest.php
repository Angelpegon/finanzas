<?php

namespace Tests\Unit;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Services\PrestamoService;
use App\Services\ProyeccionService;
use App\Services\RecurrenciaService;
use App\Services\TesoreriaService;
use App\Support\RecurrenciaMensual;
use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class ProyeccionServiceTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_horizonte_arrastra_cuota_vencida_de_mes_anterior(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-01-01', 5_000_000_00, $cuenta->id
        );
        $prestamo = app(PrestamoService::class)->crear(
            $user->id, 'Atrasado', 200_000_00, 0.0, 3, '2026-02-10', 15, $cuenta->id
        );
        $cuotaMarzo = CuotaPrestamo::withoutGlobalScopes()
            ->where('prestamo_id', $prestamo->id)
            ->whereDate('fecha_vencimiento', '2026-03-15')
            ->firstOrFail();
        $this->assertFalse((bool) $cuotaMarzo->pagada);

        $h = app(ProyeccionService::class)->horizonteMensual($user->id, Carbon::parse('2026-04-10'));
        $this->assertGreaterThanOrEqual((int) $cuotaMarzo->total_centavos, $h['cuotas']);

        Carbon::setTestNow();
    }

    public function test_unico_y_anual_alineados_a_calendario(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-05', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();

        $unico = app(RecurrenciaService::class)->crear(
            $user->id, 'gasto', 'Matrícula', 500_000_00, 20, 'unico', 'fijo', $cat->id, $cuenta->id
        );
        $anual = app(RecurrenciaService::class)->crear(
            $user->id, 'gasto', 'Seguro', 1_200_000_00, 10, 'anual', 'fijo', $cat->id, $cuenta->id
        );

        $marzo = Carbon::parse('2026-03-01');
        $abril = Carbon::parse('2026-04-01');

        $this->assertSame(500_000_00, RecurrenciaMensual::montoBrutoEnMes($unico, $marzo));
        $this->assertSame(0, RecurrenciaMensual::montoBrutoEnMes($unico, $abril));
        $this->assertSame(1_200_000_00, RecurrenciaMensual::montoBrutoEnMes($anual, $marzo));
        $this->assertSame(0, RecurrenciaMensual::montoBrutoEnMes($anual, $abril));

        $meses = app(ProyeccionService::class)->meses($user->id, $marzo, 2);
        $this->assertSame(500_000_00 + 1_200_000_00, $meses[0]['gastos_centavos']);
        $this->assertSame(0, $meses[1]['gastos_centavos']);

        Carbon::setTestNow();
    }

    public function test_mes_actual_no_doble_cuenta_recurrencia_ya_posteada(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-15', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-03-01', 5_000_000_00, $cuenta->id
        );
        app(RecurrenciaService::class)->crear(
            $user->id, 'gasto', 'Arriendo', 800_000_00, 10, 'mensual', 'fijo', $cat->id, $cuenta->id
        );
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Gasto, '2026-03-10', 800_000_00, $cuenta->id, $cat->id, null, 'Arriendo'
        );

        $meses = app(ProyeccionService::class)->meses($user->id, now(), 1);
        $this->assertSame(800_000_00, $meses[0]['gastos_centavos']);

        $h = app(ProyeccionService::class)->horizonteMensual($user->id, now());
        $this->assertSame(800_000_00, $h['gastos']);
        $this->assertSame(
            $h['ingresos'] - $h['gastos'] - $h['cuotas'] - $h['metas'],
            $h['capacidad_ahorro']
        );

        Carbon::setTestNow();
    }

    public function test_metas_se_agotan_en_horizonte_multi_mes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-01', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-03-01', 5_000_000_00, $cuenta->id
        );
        app(\App\Services\MetaAhorroService::class)->crear(
            $user->id, 'Viaje', 150_000_00, null, 100_000_00, $cuenta->id
        );

        $meses = app(ProyeccionService::class)->meses($user->id, now(), 3);
        $this->assertSame(100_000_00, $meses[0]['metas_centavos']);
        $this->assertSame(50_000_00, $meses[1]['metas_centavos']);
        $this->assertSame(0, $meses[2]['metas_centavos']);

        Carbon::setTestNow();
    }
}
