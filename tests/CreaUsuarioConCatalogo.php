<?php

namespace Tests;

use App\Models\User;
use App\Services\CatalogoInicialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait CreaUsuarioConCatalogo
{
    use RefreshDatabase;

    protected function usuarioConCatalogo(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        // User::created ya siembra catálogo; evitar doble siembra
        if (\App\Models\CuentaContable::withoutGlobalScopes()->where('usuario_id', $user->id)->doesntExist()) {
            app(CatalogoInicialService::class)->sembrar($user);
        }

        return $user->fresh();
    }
}
