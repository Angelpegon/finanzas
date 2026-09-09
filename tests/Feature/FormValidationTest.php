<?php

namespace Tests\Feature;

use App\Models\CuentaLiquida;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class FormValidationTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_ingreso_sin_datos_muestra_mensajes_en_espanol(): void
    {
        $usuario = $this->usuarioConCatalogo();

        $response = $this->actingAs($usuario)->from(route('app.ingresos.create'))->post(route('app.ingresos.store'), []);

        $response->assertRedirect(route('app.ingresos.create'));
        $response->assertSessionHasErrors([
            'monto',
            'fecha',
            'categoria_id',
            'cuenta_liquida_id',
            'periodicidad',
        ]);

        $errors = session('errors');
        $this->assertStringContainsString('monto', mb_strtolower($errors->first('monto')));
        $this->assertDoesNotMatchRegularExpression('/\bthe\b|\bfield\b|\brequired\b/i', $errors->first('monto'));
    }

    public function test_gasto_rechaza_monto_cero_con_mensaje_claro(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = \App\Models\Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        $response = $this->actingAs($usuario)->from(route('app.gastos.create'))->post(route('app.gastos.store'), [
            'monto' => '0',
            'fecha' => now()->toDateString(),
            'categoria_id' => $categoria->id,
            'cuenta_liquida_id' => $cuenta->id,
            'tipo_gasto' => 'variable',
            'periodicidad' => 'unico',
        ]);

        $response->assertSessionHasErrors('monto');
        $this->assertStringContainsString('mayor', mb_strtolower(session('errors')->first('monto')));
    }

    public function test_normaliza_monto_colombiano_antes_de_validar(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = \App\Models\Categoria::where('usuario_id', $usuario->id)->where('tipo', 'ingreso')->firstOrFail();

        $response = $this->actingAs($usuario)->post(route('app.ingresos.store'), [
            'monto' => '1.500,50',
            'fecha' => now()->toDateString(),
            'categoria_id' => $categoria->id,
            'cuenta_liquida_id' => $cuenta->id,
            'descripcion' => 'Prueba formato',
            'periodicidad' => 'unico',
        ]);

        $response->assertRedirect(route('app.situacion'));
        $response->assertSessionHasNoErrors();
    }

    public function test_pago_exige_categoria_y_referencia_unica_en_espanol(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();

        $response = $this->actingAs($usuario)->from(route('app.pagos.index'))->post(route('app.pagos.store'), [
            'tipo' => 'gasto',
            'fecha' => now()->toDateString(),
            'monto' => '10000',
            'cuenta_liquida_id' => $cuenta->id,
            'destino' => 'Proveedor',
            'referencia' => 'FAC-1',
        ]);

        $response->assertSessionHasErrorsIn('pago', ['categoria_id']);
        $this->assertStringContainsString('categoría', mb_strtolower(session('errors')->pago->first('categoria_id')));
    }

    public function test_transferencia_exige_cuentas_distintas(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();

        $response = $this->actingAs($usuario)->from(route('app.pagos.index'))->post(route('app.transferencias.store'), [
            'cuenta_liquida_id' => $cuenta->id,
            'cuenta_destino_id' => $cuenta->id,
            'monto' => '1000',
            'fecha' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrorsIn('transferencia', ['cuenta_destino_id']);
        $this->assertFalse(session('errors')->pago->any());
    }

    public function test_transferencia_rechaza_bolsillo_de_meta_por_http(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $origen = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        app(\App\Services\TesoreriaService::class)->registrar(
            $usuario->id,
            \App\Enums\TipoHechoTesoreria::Apertura,
            now()->toDateString(),
            500_000_00,
            $origen->id
        );
        $meta = app(\App\Services\MetaAhorroService::class)->crear(
            $usuario->id, 'Viaje', 1_000_000_00, null, 0, $origen->id
        );
        $bolsilloId = (int) $meta->cuenta_liquida_id;

        $response = $this->actingAs($usuario)->from(route('app.pagos.index'))->post(route('app.transferencias.store'), [
            'cuenta_liquida_id' => $origen->id,
            'cuenta_destino_id' => $bolsilloId,
            'monto' => '10000',
            'fecha' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrorsIn('transferencia', ['cuenta_destino_id']);
    }

    public function test_error_de_dominio_de_pago_se_asocia_al_campo_correcto(): void
    {
        $usuario = $this->usuarioConCatalogo();
        $cuenta = CuentaLiquida::where('usuario_id', $usuario->id)->firstOrFail();
        $categoria = \App\Models\Categoria::where('usuario_id', $usuario->id)->where('tipo', 'gasto')->firstOrFail();

        // Sin saldo suficiente: el mensaje no debe colgarse solo en "monto".
        $response = $this->actingAs($usuario)->from(route('app.pagos.index'))->post(route('app.pagos.store'), [
            'tipo' => 'gasto',
            'fecha' => now()->toDateString(),
            'monto' => '999999999',
            'cuenta_liquida_id' => $cuenta->id,
            'categoria_id' => $categoria->id,
            'destino' => 'Proveedor',
            'referencia' => 'FAC-SALDO',
        ]);

        $response->assertSessionHasErrorsIn('pago', ['cuenta_liquida_id']);
        $this->assertStringContainsString('saldo', mb_strtolower(session('errors')->pago->first('cuenta_liquida_id')));
    }

    public function test_registro_muestra_errores_de_password_en_espanol(): void
    {
        $response = $this->from(route('register'))->post(route('auth.register'), [
            'nombre' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ]);

        $response->assertSessionHasErrors('password');
        $mensaje = session('errors')->first('password');
        $this->assertDoesNotMatchRegularExpression('/\bthe\b|\bconfirmation\b/i', $mensaje);
    }

    public function test_errores_en_vista_no_duplican_lista_ni_rompen_select(): void
    {
        $usuario = $this->usuarioConCatalogo();

        $html = $this->actingAs($usuario)
            ->from(route('app.ingresos.create'))
            ->followingRedirects()
            ->post(route('app.ingresos.store'), [])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('invalid-feedback', $html);
        $this->assertStringContainsString('Revisa los campos marcados', $html);
        // No repetir cada mensaje en un <ul> del alert.
        $this->assertStringNotContainsString('<ul class="mb-0 mt-2 ps-3">', $html);
        // El select inválido debe seguir siendo un <select>, no un contenedor raro.
        $this->assertMatchesRegularExpression('/<select[^>]*is-invalid[^>]*>/i', $html);
    }
}
