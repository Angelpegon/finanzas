<?php

namespace App\Models;

use App\Enums\NaturalezaCuenta;
use App\Models\Concerns\PerteneceAlUsuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Saldos de muchas cuentas contables en una sola agregación.
     *
     * @param  list<int>|null  $cuentaContableIds
     * @return array<int, int> cuenta_contable_id => saldo_centavos
     */
    public static function saldosCentavosMap(int $usuarioId, ?array $cuentaContableIds = null): array
    {
        $query = DB::table('movimientos')
            ->join('cuentas_contables', 'cuentas_contables.id', '=', 'movimientos.cuenta_contable_id')
            ->where('movimientos.usuario_id', $usuarioId)
            ->when(
                $cuentaContableIds !== null,
                fn ($q) => $q->whereIn(
                    'movimientos.cuenta_contable_id',
                    $cuentaContableIds === [] ? [0] : $cuentaContableIds
                )
            )
            ->groupBy('movimientos.cuenta_contable_id', 'cuentas_contables.naturaleza')
            ->selectRaw(
                'movimientos.cuenta_contable_id as id, cuentas_contables.naturaleza as naturaleza, '.
                'SUM(movimientos.debe_centavos) as debe, SUM(movimientos.haber_centavos) as haber'
            );

        $map = [];
        foreach ($query->get() as $row) {
            $debe = (int) $row->debe;
            $haber = (int) $row->haber;
            $naturaleza = NaturalezaCuenta::from((string) $row->naturaleza);
            $map[(int) $row->id] = match ($naturaleza) {
                NaturalezaCuenta::Activo, NaturalezaCuenta::Gasto => $debe - $haber,
                default => $haber - $debe,
            };
        }

        return $map;
    }
}
