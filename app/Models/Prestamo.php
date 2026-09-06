<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prestamo extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'prestamos';
    protected $guarded = ['id'];
    protected $casts = ['fecha_desembolso' => 'date', 'fecha_vencimiento' => 'date', 'ea_porcentaje' => 'float', 'seguro_centavos' => 'integer', 'otros_cargos_centavos' => 'integer'];

    protected $appends = ['saldo_actual_centavos', 'cuotas_pendientes', 'cuotas_pagadas'];

    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaPrestamo::class, 'prestamo_id')->orderBy('numero');
    }

    public function getSaldoActualCentavosAttribute(): int
    {
        return (int) $this->cuotas()->where('pagada', false)->sum('capital_centavos');
    }

    public function getCuotasPendientesAttribute(): int
    {
        return (int) $this->cuotas()->where('pagada', false)->count();
    }

    public function getCuotasPagadasAttribute(): int
    {
        return (int) $this->cuotas()->where('pagada', true)->count();
    }

    public function getEstadoAttribute($valor): string
    {
        if ($valor === 'cancelada') {
            return $valor;
        }
        if ($this->cuotas()->where('pagada', false)->count() === 0) {
            return 'pagada';
        }
        if ($this->cuotas()->where('pagada', false)->where('fecha_vencimiento', '<', now()->toDateString())->exists()) {
            return 'vencida';
        }
        return $valor ?: 'activa';
    }
}
