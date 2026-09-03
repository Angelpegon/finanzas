<?php

namespace App\Models;

use App\Enums\NaturalezaCuenta;
use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuentaContable extends Model
{
    use PerteneceAlUsuario;

    protected $table = 'cuentas_contables';

    protected $fillable = ['usuario_id', 'codigo', 'nombre', 'naturaleza'];

    protected $casts = [
        'naturaleza' => NaturalezaCuenta::class,
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoLibro::class, 'cuenta_contable_id');
    }

    public function saldoCentavos(): int
    {
        $debe = (int) $this->movimientos()->sum('debe_centavos');
        $haber = (int) $this->movimientos()->sum('haber_centavos');

        return match ($this->naturaleza) {
            NaturalezaCuenta::Activo, NaturalezaCuenta::Gasto => $debe - $haber,
            default => $haber - $debe,
        };
    }
}
