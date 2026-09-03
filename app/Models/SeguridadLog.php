<?php

namespace App\Models;

use App\Enums\SeguridadAccion;
use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class SeguridadLog extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'seguridad_logs';

    protected $fillable = [
        'usuario_id', 'actor_user_id', 'accion', 'descripcion',
        'registro_afectado', 'ip', 'user_agent',
    ];

    protected $casts = [
        'accion' => SeguridadAccion::class,
    ];
}
