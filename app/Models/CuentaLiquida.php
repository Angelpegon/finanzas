<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaLiquida extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'cuentas_liquidas';

    protected $fillable = [
        'usuario_id', 'cuenta_contable_id', 'nombre', 'tipo', 'institucion',
        'numero_cuenta_enmascarado', 'saldo_inicial_centavos', 'moneda', 'estado', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'saldo_inicial_centavos' => 'integer',
    ];

    protected $appends = ['saldo_actual_centavos'];

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    public function saldoCentavos(): int
    {
        return $this->cuentaContable->saldoCentavos();
    }

    public function getSaldoActualCentavosAttribute(): int
    {
        return $this->saldoCentavos();
    }
}
