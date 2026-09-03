<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class CuotaPrestamo extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'cuotas_prestamo';
    protected $guarded = ['id'];
    protected $casts = ['fecha_vencimiento' => 'date', 'pagada' => 'boolean'];

    protected $appends = ['total_centavos'];

    public function getTotalCentavosAttribute(): int
    {
        return (int) ($this->capital_centavos + $this->interes_centavos + $this->seguro_centavos + $this->otros_cargos_centavos);
    }

    public function prestamo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }
}
