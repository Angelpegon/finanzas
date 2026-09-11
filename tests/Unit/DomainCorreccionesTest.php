<?php

namespace Tests\Unit;

use App\Enums\TipoHechoTesoreria;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Services\CalendarioFinancieroService;
use App\Services\ContabilizacionService;
use App\Services\MetaAhorroService;
use App\Services\PresupuestoService;
use App\Services\PrestamoService;
use App\Services\ProyeccionService;
use App\Services\RecurrenciaService;
use App\Services\TarjetaService;
use App\Services\TesoreriaService;
use App\Support\AgregadosLibro;
use Illuminate\Support\Carbon;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class DomainCorreccionesTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_presupuesto_incluye_compra_tarjeta_y_pago_de_gasto(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 5_000_000_00, $cuenta->id
        );
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('nombre', 'Compras')->firstOrFail();
        $presupuesto = app(PresupuestoService::class)->guardar($user->id, (int) now()->year, (int) now()->month, [
            $cat->id => 1_000_000_00,
        ]);

        $tarjeta = app(TarjetaService::class)->crear($user->id, 'Visa', 2_000_000_00, 5, 20, 2.5, 2.5, 'Banco');
        app(TarjetaService::class)->registrarCompra(
            $user->id, $tarjeta->id, 200_000_00, 1, now()->toDateString(), 'compra', $cat->id, null, 'TV'
        );
        app(TesoreriaService::class)->registrar(
            $user->id,
            TipoHechoTesoreria::Gasto,
            now()->toDateString(),
            50_000_00,
            $cuenta->id,
            $cat->id,
            null,
            'Tienda'
        );

        $consumo = app(PresupuestoService::class)->consumo($user->id, $cat->id, (int) now()->year, (int) now()->month);
        $this->assertSame(250_000_00, $consumo);
        $this->assertSame(250_000_00, $presupuesto->fresh()->gastoRealParaCategoria($cat->id));
        $this->assertSame('Banco', $tarjeta->fresh()->entidad);
    }

    public function test_horizonte_no_duplica_recurrencia_ya_posteada(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 5_000_000_00, $cuenta->id
        );
        app(RecurrenciaService::class)->crear(
            $user->id, 'gasto', 'Arriendo', 1_000_000_00, (int) now()->day, 'mensual', 'fijo', $cat->id, $cuenta->id
        );
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Gasto, now()->toDateString(), 1_000_000_00, $cuenta->id, $cat->id
        );

        $h = app(ProyeccionService::class)->horizonteMensual($user->id, now());
        $this->assertSame(1_000_000_00, $h['gastos']);
    }

    public function test_reverso_de_aporte_anula_avance_de_meta(): void
    {
        $user = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, now()->toDateString(), 500_000_00, $origen->id
        );
        $meta = app(MetaAhorroService::class)->crear($user->id, 'Viaje', 50_000_00, null, 0, $origen->id);
        $hecho = app(MetaAhorroService::class)->aportar($user->id, $meta->id, 80_000_00, $origen->id, now()->toDateString());
        $this->assertSame(80_000_00, $meta->fresh()->progreso_centavos);
        $this->assertSame('cumplida', $meta->fresh()->estado);

        $asiento = Asiento::withoutGlobalScopes()
            ->where('usuario_id', $user->id)
            ->where('origen_tipo', HechoTesoreria::class)
            ->where('origen_id', $hecho->id)
            ->firstOrFail();
        app(ContabilizacionService::class)->revertir($user->id, $asiento->id, now()->toDateString(), 'error');

        $this->assertSame(0, $meta->fresh()->progreso_centavos);
        $this->assertSame('activa', $meta->fresh()->estado);
        $this->assertSame(0, AgregadosLibro::gastosReales($user->id, now()->startOfMonth(), now()->endOfMonth()));
    }

    public function test_tasa_mensual_no_se_trata_como_ea(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-01-01', 20_000_000_00, $cuenta->id
        );

        $ea = app(PrestamoService::class)->crear(
            $user->id, 'EA', 1_000_000_00, 24.0, 12, '2026-01-10', 15, $cuenta->id, null, 'prestamo_bancario', 'ea', 'mensual'
        );
        $mensual = app(PrestamoService::class)->crear(
            $user->id, 'Mensual', 1_000_000_00, 24.0, 12, '2026-01-10', 15, $cuenta->id, null, 'prestamo_bancario', 'mensual', 'mensual'
        );

        $this->assertNotEquals(
            (int) $ea->cuotas->first()->interes_centavos,
            (int) $mensual->cuotas->first()->interes_centavos
        );
    }

    public function test_abono_extra_mayor_al_capital_pendiente_se_rechaza(): void
    {
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-01-01', 50_000_000_00, $cuenta->id
        );
        $prestamo = app(PrestamoService::class)->crear(
            $user->id, 'Corto', 100_000_00, 0.0, 2, '2026-01-10', 15, $cuenta->id
        );
        $total = (int) $prestamo->cuotas->first()->total_centavos;

        $this->expectException(\InvalidArgumentException::class);
        app(PrestamoService::class)->registrarPago($user->id, $prestamo->id, $total + 5_000_000_00, '2026-02-15');
    }

    public function test_calendario_no_repite_cuota_pagada_ni_recurrencia_cubierta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10', 'America/Bogota'));
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

        $eventos = app(CalendarioFinancieroService::class)->mensual($user->id, 2026, 3);
        $arriendosProyectados = collect($eventos)->where('descripcion', 'Arriendo')->where('estado', 'proyectado');
        $this->assertTrue($arriendosProyectados->isEmpty());

        Carbon::setTestNow();
    }

    public function test_calendario_oculta_hecho_revertido_y_muestra_pago_de_prestamo(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-03-01', 5_000_000_00, $cuenta->id
        );
        $gasto = app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Gasto, '2026-04-05', 50_000_00, $cuenta->id,
            Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->value('id'),
            null,
            'Gasto a corregir'
        );
        $asiento = Asiento::withoutGlobalScopes()
            ->where('usuario_id', $user->id)
            ->where('origen_tipo', HechoTesoreria::class)
            ->where('origen_id', $gasto->id)
            ->firstOrFail();
        app(ContabilizacionService::class)->revertir($user->id, (int) $asiento->id, '2026-04-06', 'Corrección');

        $prestamo = app(PrestamoService::class)->crear(
            $user->id, 'Libre', 200_000_00, 0.0, 2, '2026-03-10', 15, $cuenta->id
        );
        $cuota = $prestamo->cuotas()->orderBy('numero')->firstOrFail();
        $this->assertSame('2026-04-15', $cuota->fecha_vencimiento->toDateString());
        app(PrestamoService::class)->registrarPago(
            $user->id, $prestamo->id, (int) $cuota->total_centavos, '2026-04-15'
        );

        $eventos = collect(app(CalendarioFinancieroService::class)->mensual($user->id, 2026, 4));
        $this->assertTrue($eventos->where('descripcion', 'Gasto a corregir')->isEmpty());
        $this->assertTrue($eventos->where('tipo', 'pago')->contains(
            fn (array $e) => str_contains($e['descripcion'], 'Libre') && $e['estado'] === 'real'
        ));
        $this->assertTrue(
            $eventos->where('tipo', 'cuota')->filter(
                fn (array $e) => str_contains($e['descripcion'], 'Libre #'.$cuota->numero)
            )->isEmpty()
        );
        $this->assertTrue($eventos->where('tipo', 'limite_tarjeta')->isEmpty());

        $resumen = app(CalendarioFinancieroService::class)->resumenMensual($eventos->all());
        $this->assertArrayHasKey('salidas_proyectadas_centavos', $resumen);
        $this->assertGreaterThan(0, $resumen['salidas_centavos']);

        Carbon::setTestNow();
    }

    public function test_calendario_recurrencia_unico_solo_mes_creacion_y_umbral_monto(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-01', 'America/Bogota'));
        $user = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $user->id)->firstOrFail();
        $cat = Categoria::withoutGlobalScopes()->where('usuario_id', $user->id)->where('tipo', 'gasto')->firstOrFail();
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Apertura, '2026-03-01', 5_000_000_00, $cuenta->id
        );
        app(RecurrenciaService::class)->crear(
            $user->id, 'gasto', 'Matrícula', 1_000_000_00, 20, 'unico', 'fijo', $cat->id, $cuenta->id
        );

        $marzo = collect(app(CalendarioFinancieroService::class)->mensual($user->id, 2026, 3));
        $this->assertCount(1, $marzo->where('descripcion', 'Matrícula')->where('estado', 'proyectado'));

        $abril = collect(app(CalendarioFinancieroService::class)->mensual($user->id, 2026, 4));
        $this->assertTrue($abril->where('descripcion', 'Matrícula')->isEmpty());

        app(RecurrenciaService::class)->crear(
            $user->id, 'gasto', 'Arriendo', 800_000_00, 10, 'mensual', 'fijo', $cat->id, $cuenta->id
        );
        app(TesoreriaService::class)->registrar(
            $user->id, TipoHechoTesoreria::Gasto, '2026-03-10', 1_000_00, $cuenta->id, $cat->id, null, 'Abono parcial'
        );
        $conParcial = collect(app(CalendarioFinancieroService::class)->mensual($user->id, 2026, 3));
        $this->assertFalse($conParcial->where('descripcion', 'Arriendo')->where('estado', 'proyectado')->isEmpty());

        Carbon::setTestNow();
    }
}