<?php

namespace App\Models;

use App\Enums\TipoHechoTesoreria;
use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class HechoTesoreria extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'hechos_tesoreria';
    protected $fillable = [
        'usuario_id', 'tipo', 'tipo_gasto', 'cuenta_liquida_id', 'cuenta_destino_id',
        'categoria_id', 'meta_ahorro_id', 'fecha', 'monto_centavos',
        'descripcion', 'hash_fila',
    ];
    protected $casts = [
        'tipo' => TipoHechoTesoreria::class,
        'fecha' => 'date',
    ];

    public function metaAhorro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MetaAhorro::class, 'meta_ahorro_id');
    }

    public function cuentaLiquida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaLiquida::class, 'cuenta_liquida_id');
    }

    public function cuentaDestino(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaLiquida::class, 'cuenta_destino_id');
    }
}
