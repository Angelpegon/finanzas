<?php

namespace Tests\Feature;

use App\Models\CuentaLiquida;
use App\Models\Prestamo;
use Illuminate\Support\Facades\Auth;
use Tests\CreaUsuarioConCatalogo;
use Tests\TestCase;

class AislamientoMultiUsuarioTest extends TestCase
{
    use CreaUsuarioConCatalogo;

    public function test_usuario_no_ve_cuentas_ni_deudas_de_otro(): void
    {
        $a = $this->usuarioConCatalogo(['email' => 'a@test.com']);
        $b = $this->usuarioConCatalogo(['email' => 'b@test.com']);
        $cuentaB = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $b->id)->firstOrFail();

        $this->actingAs($a);
        $this->assertNull(CuentaLiquida::find($cuentaB->id));

        $prestamoB = Prestamo::withoutGlobalScopes()->create([
            'usuario_id' => $b->id,
            'cuenta_contable_id' => \App\Models\CuentaContable::withoutGlobalScopes()->where('usuario_id', $b->id)->where('codigo', '2100')->firstOrFail()->id,
            'cuenta_liquida_id' => $cuentaB->id,
            'nombre' => 'Ajeno',
            'principal_centavos' => 1000,
            'ea_porcentaje' => 10,
            'plazo_meses' => 6,
            'fecha_desembolso' => now()->toDateString(),
            'dia_pago' => 1,
            'cuota_centavos' => 200,
        ]);

        $this->assertNull(Prestamo::find($prestamoB->id));
        $this->assertTrue(Auth::id() === $a->id);
    }

    public function test_destroy_cuenta_ajena_retorna_404(): void
    {
        $a = $this->usuarioConCatalogo(['email' => 'a2@test.com']);
        $b = $this->usuarioConCatalogo(['email' => 'b2@test.com']);
        $cuentaB = CuentaLiquida::withoutGlobalScopes()->where('usuario_id', $b->id)->firstOrFail();

        $this->actingAs($a)
            ->delete(route('app.cuentas.destroy', $cuentaB))
            ->assertNotFound();
    }
}
