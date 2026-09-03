<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoLibro extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'movimientos';
    protected $fillable = [
        'usuario_id', 'asiento_id', 'cuenta_contable_id',
        'debe_centavos', 'haber_centavos',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class, 'asiento_id');
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }
}
