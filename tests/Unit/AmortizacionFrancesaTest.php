<?php

namespace Tests\Unit;

use App\Support\AmortizacionFrancesa;
use App\Support\Tasa;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class AmortizacionFrancesaTest extends TestCase
{
    public function test_calendario_cuadra_y_saldo_final_cero(): void
    {
        $tasa = Tasa::mensualDesdeEA(24);
        $filas = AmortizacionFrancesa::calendario(10_000_00, $tasa, 12, Carbon::parse('2026-02-15'));

        $this->assertCount(12, $filas);
        foreach ($filas as $fila) {
            $this->assertSame($fila['cuota'], $fila['capital'] + $fila['interes']);
        }
        $this->assertSame(0, end($filas)['saldo']);
        $this->assertSame(10_000_00, array_sum(array_column($filas, 'capital')));
    }

    public function test_abono_extra_reduce_plazo_mantiene_capacidad_de_cuota(): void
    {
        $tasa = Tasa::mensualDesdeEA(20);
        $cuota = AmortizacionFrancesa::cuota(5_000_00, $tasa, 24);
        $plazos = AmortizacionFrancesa::plazosConCuotaFija(3_000_00, $tasa, $cuota);

        $this->assertLessThan(24, $plazos);
        $this->assertGreaterThanOrEqual(1, $plazos);
    }
}
