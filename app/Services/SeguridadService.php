<?php

namespace App\Services;

use App\Enums\SeguridadAccion;
use App\Models\SeguridadLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class SeguridadService
{
    public function registrar(
        SeguridadAccion $accion,
        string $descripcion,
        ?int $usuarioAfectadoId = null,
        ?string $registroAfectado = null
    ): SeguridadLog {
        $actorId = Auth::id();
        $usuarioId = $usuarioAfectadoId ?? $actorId;
        if ($usuarioId === null) {
            throw new \InvalidArgumentException('La auditoría requiere un usuario afectado.');
        }

        return SeguridadLog::withoutGlobalScopes()->create([
            'usuario_id' => $usuarioId,
            'actor_user_id' => $actorId,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'registro_afectado' => $registroAfectado,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
