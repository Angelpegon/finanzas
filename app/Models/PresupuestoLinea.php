<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class PresupuestoLinea extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'presupuesto_lineas';
    protected $guarded = ['id'];
    protected $appends = ['gasto_real_centavos', 'proyeccion_centavos', 'porcentaje_consumido', 'alertas'];

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function presupuesto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    public function getGastoRealCentavosAttribute(): int
    {
        return $this->presupuesto?->gastoRealParaCategoria($this->categoria_id) ?? 0;
    }

    public function getProyeccionCentavosAttribute(): int
    {
        return $this->presupuesto?->proyeccionParaCategoria($this->categoria_id) ?? 0;
    }

    public function getPorcentajeConsumidoAttribute(): float
    {
        return $this->tope_centavos > 0 ? round(($this->gasto_real_centavos / $this->tope_centavos) * 100, 1) : 0;
    }

    public function getAlertasAttribute(): array
    {
        return collect($this->presupuesto?->umbrales_alerta ?? [70, 80, 90, 100])
            ->map(fn ($umbral) => (int) $umbral)
            ->filter(fn (int $umbral) => $this->porcentaje_consumido >= $umbral)
            ->values()->all();
    }
}
