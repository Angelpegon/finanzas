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
    protected $casts = [
        'fecha_desembolso' => 'date',
        'fecha_vencimiento' => 'date',
        'ea_porcentaje' => 'float',
        'seguro_centavos' => 'integer',
        'otros_cargos_centavos' => 'integer',
    ];

    protected $appends = ['saldo_actual_centavos', 'cuotas_pendientes', 'cuotas_pagadas'];

    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaPrestamo::class, 'prestamo_id')->orderBy('numero');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'prestamo_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    /** @return \Illuminate\Support\Collection<int, CuotaPrestamo> */
    private function cuotasColeccion()
    {
        if ($this->relationLoaded('cuotas')) {
            $cargadas = $this->getRelation('cuotas');

            return $cargadas instanceof \Illuminate\Support\Collection
                ? $cargadas
                : collect($cargadas);
        }

        return $this->cuotas()->get();
    }

    public function getSaldoActualCentavosAttribute(): int
    {
        return (int) $this->cuotasColeccion()->where('pagada', false)->sum('capital_centavos');
    }

    public function getCuotasPendientesAttribute(): int
    {
        return (int) $this->cuotasColeccion()->where('pagada', false)->count();
    }

    public function getCuotasPagadasAttribute(): int
    {
        return (int) $this->cuotasColeccion()->where('pagada', true)->count();
    }

    public function getEstadoAttribute($valor): string
    {
        if ($valor === 'cancelada') {
            return $valor;
        }
        $cuotas = $this->cuotasColeccion();
        if ($cuotas->where('pagada', false)->count() === 0) {
            return 'pagada';
        }
        if ($cuotas->where('pagada', false)->where('fecha_vencimiento', '<', now()->toDateString())->isNotEmpty()) {
            return 'vencida';
        }

        return $valor ?: 'activa';
    }
}
