<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'pagos';
    protected $guarded = ['id'];
    protected $casts = [
        'fecha' => 'date',
        'extraordinario' => 'boolean',
        'cronograma_snapshot' => 'array',
    ];

    public function cuentaLiquida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaLiquida::class, 'cuenta_liquida_id');
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function prestamo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    public function cuotaPrestamo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuotaPrestamo::class, 'cuota_prestamo_id');
    }

    public function tarjetaCredito(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TarjetaCredito::class, 'tarjeta_credito_id');
    }

    public function cuotaTarjeta(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuotaTarjeta::class, 'cuota_tarjeta_id');
    }
}
