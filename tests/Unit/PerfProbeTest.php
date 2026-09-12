<?php

namespace Tests\Unit;

use App\Support\PerfProbe;
use Tests\TestCase;

class PerfProbeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PerfProbe::reset();
    }

    public function test_calcula_duraciones_entre_marcas(): void
    {
        $probe = new \ReflectionClass(PerfProbe::class);
        $marks = $probe->getProperty('marks');
        $marks->setAccessible(true);
        $marks->setValue(null, [
            'A_request_start' => 100.000,
            'A_autoload_done' => 100.120,
            'A_providers_boot_start' => 100.130,
            'A_providers_boot_end' => 103.130,
            'kernel_exit' => 103.200,
        ]);

        $stages = PerfProbe::stages();

        $this->assertEquals(120.0, $stages['A_autoload']);
        $this->assertEquals(3000.0, $stages['A_providers_boot']);
        $this->assertEquals(3200.0, $stages['total_until_response']);
    }
}
