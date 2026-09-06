<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraTarjeta extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'compras_tarjeta';
    protected $guarded = ['id'];
    protected $casts = ['fecha' => 'date', 'tasa_interes_porcentaje' => 'float'];

    public function cuotasProgramadas(): HasMany
    {
        return $this->hasMany(CuotaTarjeta::class, 'compra_tarjeta_id')->orderBy('numero');
    }

    public function tarjetaCredito(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarjetaCredito::class, 'tarjeta_credito_id');
    }
}
