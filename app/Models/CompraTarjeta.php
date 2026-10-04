<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompraTarjeta extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'compras_tarjeta';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha' => 'date',
        'tasa_interes_porcentaje' => 'float',
        'anulada' => 'boolean',
    ];

    public function cuotasProgramadas(): HasMany
    {
        return $this->hasMany(CuotaTarjeta::class, 'compra_tarjeta_id')->orderBy('numero');
    }

    public function getEstaLiquidadaAttribute(): bool
    {
        if ($this->anulada) {
            return false;
        }

        $cuotas = $this->relationLoaded('cuotasProgramadas')
            ? $this->cuotasProgramadas
            : $this->cuotasProgramadas()->get();

        return $cuotas->isNotEmpty() && $cuotas->every(fn (CuotaTarjeta $c) => (bool) $c->pagada);
    }

    public function tarjetaCredito(): BelongsTo
    {
        return $this->belongsTo(TarjetaCredito::class, 'tarjeta_credito_id');
    }

    public function cuentaLiquida(): BelongsTo
    {
        return $this->belongsTo(CuentaLiquida::class, 'cuenta_liquida_id');
    }
}
