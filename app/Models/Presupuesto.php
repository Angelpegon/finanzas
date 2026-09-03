<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Presupuesto extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'presupuestos';
    protected $guarded = ['id'];
    protected $casts = ['umbrales_alerta' => 'array'];

    public function lineas(): HasMany
    {
        return $this->hasMany(PresupuestoLinea::class, 'presupuesto_id');
    }

    public function gastoRealParaCategoria(int $categoriaId): int
    {
        return app(\App\Services\PresupuestoService::class)->consumo($this->usuario_id, $categoriaId, $this->anio, $this->mes);
    }

    public function proyeccionParaCategoria(int $categoriaId): int
    {
        return app(\App\Services\PresupuestoService::class)->proyeccion($this->usuario_id, $categoriaId, $this->anio, $this->mes);
    }
}
