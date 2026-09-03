<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use PerteneceAlUsuario;
    protected $table = 'pagos';
    protected $guarded = ['id'];
    protected $casts = ['fecha' => 'date', 'extraordinario' => 'boolean'];

    public function cuentaLiquida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaLiquida::class, 'cuenta_liquida_id');
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
