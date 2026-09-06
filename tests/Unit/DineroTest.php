<?php

namespace Tests\Unit;

use App\Support\Dinero;
use PHPUnit\Framework\TestCase;

class DineroTest extends TestCase
{
    public function test_acepta_formato_colombiano_y_el_que_envia_el_formulario(): void
    {
        $this->assertSame(10_050, Dinero::pesosACentavos('100,50'));
        $this->assertSame(10_050, Dinero::pesosACentavos('100.50'));
        $this->assertSame(1_500_50, Dinero::pesosACentavos('1.500,50'));
        $this->assertSame(1_500_50, Dinero::pesosACentavos('1,500.50'));
        $this->assertSame(150_000_00, Dinero::pesosACentavos('150000'));
        $this->assertSame(150_000_00, Dinero::pesosACentavos('150.000'));
    }
}
