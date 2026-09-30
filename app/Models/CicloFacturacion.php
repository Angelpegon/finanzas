<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CicloFacturacion extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'ciclos_facturacion';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_corte' => 'date',
        'fecha_pago' => 'date',
    ];

    public function tarjetaCredito(): BelongsTo
    {
        return $this->belongsTo(TarjetaCredito::class, 'tarjeta_credito_id');
    }

    public function restanteCentavos(): int
    {
        return max(0, (int) $this->pago_total_centavos - (int) $this->pagado_centavos);
    }

    public function minimoRestanteCentavos(): int
    {
        return max(0, (int) $this->pago_minimo_centavos - (int) $this->pagado_centavos);
    }
}
