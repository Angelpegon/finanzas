<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class CuotaTarjeta extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'cuotas_tarjeta';
    protected $guarded = ['id'];
    protected $casts = ['fecha_vencimiento' => 'date', 'pagada' => 'boolean'];
    protected $appends = ['total_centavos'];

    public function getTotalCentavosAttribute(): int
    {
        return (int) $this->capital_centavos + (int) $this->interes_centavos;
    }

    public function compra(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CompraTarjeta::class, 'compra_tarjeta_id');
    }

    public function tarjetaCredito(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarjetaCredito::class, 'tarjeta_credito_id');
    }
}
