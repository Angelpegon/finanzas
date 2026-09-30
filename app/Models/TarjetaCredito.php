<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TarjetaCredito extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'tarjetas_credito';

    protected $guarded = ['id'];

    protected $casts = [
        'ea_porcentaje' => 'float',
        'tasa_compras_mensual' => 'float',
        'tasa_avances_mensual' => 'float',
        'activa' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_vencimiento' => 'date',
        'porcentaje_abono_capital_minimo' => 'float',
        'tasa_mora_mensual' => 'float',
    ];

    protected $appends = [
        'saldo_actual_centavos',
        'cupo_disponible_centavos',
        'pago_minimo_centavos',
        'pago_total_centavos',
        'cuotas_pendientes',
    ];

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(CompraTarjeta::class, 'tarjeta_credito_id');
    }

    /**
     * Cronograma de cuotas (todas las compras). No llamar al atributo `cuotas`:
     * históricamente hubo una columna scalar homónima que sombreaba esta relación.
     */
    public function cuotasProgramadas(): HasMany
    {
        return $this->hasMany(CuotaTarjeta::class, 'tarjeta_credito_id');
    }

    public function cicloAbierto(): HasOne
    {
        return $this->hasOne(CicloFacturacion::class, 'tarjeta_credito_id')
            ->where('estado', 'abierto');
    }

    public function getSaldoActualCentavosAttribute(): int
    {
        $cuenta = $this->relationLoaded('cuentaContable')
            ? $this->cuentaContable
            : $this->cuentaContable()->first();

        return $cuenta ? max(0, (int) $cuenta->saldoCentavos()) : 0;
    }

    /** @return \Illuminate\Support\Collection<int, CuotaTarjeta> */
    private function cuotasCargadas()
    {
        if ($this->relationLoaded('compras')) {
            return $this->compras
                ->filter(fn (CompraTarjeta $c) => ! $c->anulada)
                ->flatMap(function (CompraTarjeta $compra) {
                    return $compra->relationLoaded('cuotasProgramadas')
                        ? $compra->cuotasProgramadas
                        : $compra->cuotasProgramadas()->get();
                });
        }

        return $this->cuotasProgramadas()
            ->whereHas('compra', fn ($q) => $q->where('anulada', false))
            ->get();
    }

    public function getCuotasPendientesAttribute(): int
    {
        return $this->cuotasCargadas()->where('pagada', false)->count();
    }

    public function getCupoDisponibleCentavosAttribute(): int
    {
        return max(0, (int) $this->cupo_centavos - $this->saldo_actual_centavos);
    }

    public function getPagoTotalCentavosAttribute(): int
    {
        $ciclo = $this->cicloAbierto;

        return $ciclo ? $ciclo->restanteCentavos() : 0;
    }

    public function getPagoMinimoCentavosAttribute(): int
    {
        $ciclo = $this->cicloAbierto;

        return $ciclo ? $ciclo->minimoRestanteCentavos() : 0;
    }
}
