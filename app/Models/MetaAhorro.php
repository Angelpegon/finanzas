<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class MetaAhorro extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'metas_ahorro';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_objetivo' => 'date',
        'monto_actual_centavos' => 'integer',
        'aporte_mensual_centavos' => 'integer',
        'objetivo_centavos' => 'integer',
    ];

    /** Porcentaje y fechas usan la caché sincronizada (sin N+1 al listar). */
    protected $appends = ['progreso_centavos', 'porcentaje_completado', 'ahorro_mensual_necesario_centavos', 'fecha_estimada_cumplimiento'];

    public function cuentaLiquida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaLiquida::class, 'cuenta_liquida_id');
    }

    /** Alias de la caché `monto_actual_centavos` (sincronizada tras aportes/retiros). */
    public function getProgresoCentavosAttribute(): int
    {
        return (int) $this->monto_actual_centavos;
    }

    public function getPorcentajeCompletadoAttribute(): float
    {
        $objetivo = (int) $this->objetivo_centavos;
        if ($objetivo <= 0) {
            return 0.0;
        }

        return round(((int) $this->monto_actual_centavos / $objetivo) * 100, 1);
    }

    public function getAhorroMensualNecesarioCentavosAttribute(): int
    {
        $faltante = max(0, (int) $this->objetivo_centavos - (int) $this->monto_actual_centavos);
        if ($faltante === 0 || ! $this->fecha_objetivo) {
            return 0;
        }

        $meses = max(1, now()->startOfMonth()->diffInMonths($this->fecha_objetivo->copy()->startOfMonth()));

        return (int) ceil($faltante / $meses);
    }

    public function getFechaEstimadaCumplimientoAttribute(): ?string
    {
        $faltante = max(0, (int) $this->objetivo_centavos - (int) $this->monto_actual_centavos);
        if ($faltante === 0) {
            return now()->toDateString();
        }
        if ((int) $this->aporte_mensual_centavos <= 0) {
            return null;
        }

        return now()->startOfMonth()->addMonths((int) ceil($faltante / $this->aporte_mensual_centavos))->toDateString();
    }
}
