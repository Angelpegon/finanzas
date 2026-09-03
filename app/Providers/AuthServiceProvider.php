<?php

namespace App\Providers;

use App\Models\CuentaLiquida;
use App\Models\MetaAhorro;
use App\Models\Prestamo;
use App\Models\Presupuesto;
use App\Models\TarjetaCredito;
use App\Policies\ModeloUsuarioPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CuentaLiquida::class => ModeloUsuarioPolicy::class,
        Prestamo::class => ModeloUsuarioPolicy::class,
        TarjetaCredito::class => ModeloUsuarioPolicy::class,
        Presupuesto::class => ModeloUsuarioPolicy::class,
        MetaAhorro::class => ModeloUsuarioPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
