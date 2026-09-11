<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class PresupuestoLinea extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'presupuesto_lineas';

    protected $guarded = ['id'];

    protected $casts = [
        'alertas' => 'array',
        'porcentaje_consumido' => 'float',
    ];

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function presupuesto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }
}
