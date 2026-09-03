<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asiento extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'asientos';
    protected $fillable = [
        'usuario_id', 'fecha', 'descripcion', 'origen_tipo', 'origen_id',
        'es_reverso', 'asiento_reversado_id', 'hash_integridad',
    ];
    protected $casts = ['fecha' => 'date', 'es_reverso' => 'boolean'];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoLibro::class, 'asiento_id');
    }
}
