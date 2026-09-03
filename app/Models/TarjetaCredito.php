<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TarjetaCredito extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'tarjetas_credito';
    protected $guarded = ['id'];
    protected $casts = ['ea_porcentaje' => 'float', 'activa' => 'boolean', 'fecha_inicio' => 'date', 'fecha_vencimiento' => 'date'];

    protected $appends = ['saldo_actual_centavos', 'cupo_disponible_centavos', 'pago_minimo_centavos', 'pago_total_centavos', 'cuotas_pendientes'];

    public function compras(): HasMany
    {
        return $this->hasMany(CompraTarjeta::class, 'tarjeta_credito_id');
    }

    public function getSaldoActualCentavosAttribute(): int
    {
        return (int) $this->compras()->sum('monto_centavos') - (int) \App\Models\Pago::withoutGlobalScopes()
            ->where('usuario_id', $this->usuario_id)->where('tarjeta_credito_id', $this->id)->sum('capital_centavos');
    }

    public function getCuotasPendientesAttribute(): int
    {
        return (int) $this->compras()->with('cuotasProgramadas')->get()
            ->sum(fn (CompraTarjeta $compra) => $compra->cuotasProgramadas->where('pagada', false)->count());
    }

    public function getCupoDisponibleCentavosAttribute(): int
    {
        return max(0, (int) $this->cupo_centavos - $this->saldo_actual_centavos);
    }

    public function getPagoTotalCentavosAttribute(): int
    {
        return (int) $this->compras()->with('cuotasProgramadas')
            ->get()->sum(fn (CompraTarjeta $compra) => $compra->cuotasProgramadas
                ->where('pagada', false)->sum(fn (CuotaTarjeta $cuota) => $cuota->capital_centavos + $cuota->interes_centavos));
    }

    public function getPagoMinimoCentavosAttribute(): int
    {
        $cuota = $this->compras()->with('cuotasProgramadas')->get()
            ->flatMap(fn (CompraTarjeta $compra) => $compra->cuotasProgramadas)
            ->where('pagada', false)->sortBy('fecha_vencimiento')->first();

        return $cuota ? (int) $cuota->capital_centavos + (int) $cuota->interes_centavos : 0;
    }
}
