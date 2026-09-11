<?php

namespace Database\Seeders;

use App\Enums\TipoHechoTesoreria;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\CuotaPrestamo;
use App\Models\CuotaTarjeta;
use App\Models\User;
use App\Services\CuentaLiquidaService;
use App\Services\MetaAhorroService;
use App\Services\PagoService;
use App\Services\PresupuestoService;
use App\Services\PrestamoService;
use App\Services\RecurrenciaService;
use App\Services\TarjetaService;
use App\Services\TesoreriaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Dataset demo realista vía servicios de dominio (libro append-only).
 *
 * Credenciales:
 *   email:    demo@finanzas.test
 *   password: demo1234
 *
 * Reejecutable: borra solo ese usuario (cascade) y lo vuelve a crear.
 */
class DemoDatosSeeder extends Seeder
{
    public const EMAIL = 'demo@finanzas.test';

    public const PASSWORD = 'demo1234';

    public function run(): void
    {
        Carbon::setTestNow(Carbon::now('America/Bogota'));

        if ($existente = User::query()->where('email', self::EMAIL)->first()) {
            $this->command?->warn('Eliminando usuario demo previo (cascade)...');
            $existente->delete();
        }

        $user = User::query()->create([
            'nombre' => 'Ángel Demo',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
        ]);

        $uid = (int) $user->id;
        $cats = Categoria::withoutGlobalScopes()
            ->where('usuario_id', $uid)
            ->get()
            ->keyBy('nombre');

        $cuentas = app(CuentaLiquidaService::class);
        $tesoreria = app(TesoreriaService::class);
        $prestamos = app(PrestamoService::class);
        $tarjetas = app(TarjetaService::class);
        $metas = app(MetaAhorroService::class);
        $presupuestos = app(PresupuestoService::class);
        $recurrencias = app(RecurrenciaService::class);
        $pagos = app(PagoService::class);

        $efectivo = CuentaLiquida::withoutGlobalScopes()
            ->where('usuario_id', $uid)
            ->where('nombre', 'Efectivo')
            ->firstOrFail();

        $banco = $cuentas->crear($uid, [
            'nombre' => 'Bancolombia Ahorros',
            'tipo' => 'ahorros',
            'institucion' => 'Bancolombia',
            'numero_cuenta_enmascarado' => '****4521',
            'saldo_inicial' => '8500000',
        ]);
        $nequi = $cuentas->crear($uid, [
            'nombre' => 'Nequi',
            'tipo' => 'billetera',
            'institucion' => 'Nequi',
            'numero_cuenta_enmascarado' => '****8890',
            'saldo_inicial' => '1200000',
        ]);

        $tesoreria->registrar(
            $uid,
            TipoHechoTesoreria::Apertura,
            now()->subMonths(2)->startOfMonth()->toDateString(),
            600_000_00,
            $efectivo->id,
            null,
            null,
            'Saldo inicial efectivo'
        );

        $this->sembrarFlujoMensual($tesoreria, $uid, $banco->id, $nequi->id, $efectivo->id, $cats, 2);
        $this->sembrarFlujoMensual($tesoreria, $uid, $banco->id, $nequi->id, $efectivo->id, $cats, 1);
        $this->sembrarFlujoMensual($tesoreria, $uid, $banco->id, $nequi->id, $efectivo->id, $cats, 0);

        $tesoreria->registrar(
            $uid,
            TipoHechoTesoreria::Transferencia,
            now()->subDays(12)->toDateString(),
            300_000_00,
            $banco->id,
            null,
            $nequi->id,
            'Recarga Nequi'
        );
        $tesoreria->registrar(
            $uid,
            TipoHechoTesoreria::Transferencia,
            now()->subDays(5)->toDateString(),
            150_000_00,
            $banco->id,
            null,
            $efectivo->id,
            'Retiro cajero'
        );

        $prestamoLibro = $prestamos->crear(
            $uid,
            'Libranza vivienda',
            18_000_000_00,
            18.5,
            36,
            now()->subMonths(4)->startOfMonth()->toDateString(),
            15,
            $banco->id,
            'Bancolombia',
            'libranza',
            'ea',
            'mensual'
        );
        $prestamoConsumo = $prestamos->crear(
            $uid,
            'Crédito libre inversión',
            4_500_000_00,
            28.0,
            24,
            now()->subMonths(2)->day(5)->toDateString(),
            5,
            $banco->id,
            'Davivienda',
            'prestamo_bancario',
            'ea',
            'mensual'
        );

        foreach ([$prestamoLibro, $prestamoConsumo] as $prestamo) {
            $cuotasPagables = CuotaPrestamo::withoutGlobalScopes()
                ->where('prestamo_id', $prestamo->id)
                ->where('pagada', false)
                ->whereDate('fecha_vencimiento', '<=', now()->toDateString())
                ->orderBy('numero')
                ->get();
            foreach ($cuotasPagables as $cuota) {
                $prestamos->registrarPago(
                    $uid,
                    (int) $prestamo->id,
                    (int) $cuota->total_centavos,
                    $cuota->fecha_vencimiento->toDateString()
                );
            }
        }

        $visa = $tarjetas->crear($uid, 'Visa Clásica', 8_000_000_00, 28, 15, 2.8, 3.5, 'Bancolombia');
        $amex = $tarjetas->crear($uid, 'Mastercard Black', 12_000_000_00, 10, 25, 3.1, 3.8, 'Davivienda');

        $tarjetas->registrarCompra(
            $uid,
            $visa->id,
            890_000_00,
            6,
            now()->subMonths(2)->day(18)->toDateString(),
            'compra',
            $cats['Compras']->id,
            null,
            'Portátil'
        );
        $tarjetas->registrarCompra(
            $uid,
            $visa->id,
            185_000_00,
            1,
            now()->subDays(8)->toDateString(),
            'compra',
            $cats['Alimentación']->id,
            null,
            'Supermercado Éxito'
        );
        $tarjetas->registrarCompra(
            $uid,
            $amex->id,
            420_000_00,
            3,
            now()->subMonth()->day(12)->toDateString(),
            'compra',
            $cats['Entretenimiento']->id,
            null,
            'Viaje corto'
        );

        $cuotaVisa = CuotaTarjeta::withoutGlobalScopes()
            ->where('tarjeta_credito_id', $visa->id)
            ->where('pagada', false)
            ->orderBy('fecha_vencimiento')
            ->first();
        if ($cuotaVisa) {
            $tarjetas->registrarPago(
                $uid,
                (int) $visa->id,
                (int) $banco->id,
                (int) $cuotaVisa->id,
                $cuotaVisa->fecha_vencimiento->toDateString()
            );
        }

        $metaViaje = $metas->crear(
            $uid,
            'Viaje Europa',
            8_000_000_00,
            now()->addMonths(10)->endOfMonth()->toDateString(),
            400_000_00,
            $banco->id,
            'alta'
        );
        $metaFondo = $metas->crear(
            $uid,
            'Fondo emergencia',
            5_000_000_00,
            now()->addYear()->toDateString(),
            250_000_00,
            $banco->id,
            'media'
        );
        $metas->aportar($uid, $metaViaje->id, 400_000_00, $banco->id, now()->subMonths(2)->day(20)->toDateString());
        $metas->aportar($uid, $metaViaje->id, 400_000_00, $banco->id, now()->subMonth()->day(20)->toDateString());
        $metas->aportar($uid, $metaViaje->id, 200_000_00, $banco->id, now()->subDays(3)->toDateString());
        $metas->aportar($uid, $metaFondo->id, 250_000_00, $banco->id, now()->subMonth()->day(22)->toDateString());
        $metas->aportar($uid, $metaFondo->id, 250_000_00, $banco->id, now()->subDays(2)->toDateString());

        $presupuestos->guardar($uid, (int) now()->year, (int) now()->month, [
            $cats['Alimentación']->id => 900_000_00,
            $cats['Transporte']->id => 350_000_00,
            $cats['Vivienda']->id => 1_600_000_00,
            $cats['Servicios']->id => 420_000_00,
            $cats['Entretenimiento']->id => 250_000_00,
            $cats['Suscripciones']->id => 120_000_00,
            $cats['Compras']->id => 500_000_00,
            $cats['Salud']->id => 200_000_00,
        ]);

        $mesAnterior = now()->copy()->subMonth();
        $presupuestos->guardar($uid, (int) $mesAnterior->year, (int) $mesAnterior->month, [
            $cats['Alimentación']->id => 850_000_00,
            $cats['Transporte']->id => 320_000_00,
            $cats['Vivienda']->id => 1_600_000_00,
            $cats['Servicios']->id => 400_000_00,
            $cats['Entretenimiento']->id => 200_000_00,
        ]);

        $recurrencias->crear(
            $uid,
            'ingreso',
            'Salario nómina',
            5_800_000_00,
            30,
            'mensual',
            null,
            $cats['Salario']->id,
            $banco->id
        );
        $recurrencias->crear(
            $uid,
            'gasto',
            'Arriendo',
            1_500_000_00,
            5,
            'mensual',
            'fijo',
            $cats['Vivienda']->id,
            $banco->id
        );
        $recurrencias->crear(
            $uid,
            'gasto',
            'Internet + móvil',
            180_000_00,
            12,
            'mensual',
            'fijo',
            $cats['Servicios']->id,
            $banco->id
        );
        $recurrencias->crear(
            $uid,
            'gasto',
            'Netflix / Spotify',
            65_000_00,
            8,
            'mensual',
            'fijo',
            $cats['Suscripciones']->id,
            $nequi->id
        );

        $pagos->registrar(
            $uid,
            'otra_obligacion',
            95_000_00,
            $banco->id,
            now()->subDays(6)->toDateString(),
            'EPM',
            'FAC-EPM-'.now()->format('Ym').'-01',
            'Factura energía',
            $cats['Servicios']->id
        );
        $pagos->registrar(
            $uid,
            'deuda_personal',
            78_000_00,
            $nequi->id,
            now()->subDays(1)->toDateString(),
            'Farmacia Pasteur',
            'FAC-SALUD-'.now()->format('Ymd'),
            'Medicamentos',
            $cats['Salud']->id
        );

        Carbon::setTestNow();

        $this->command?->info('Demo listo.');
        $this->command?->info('Login: '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->info('Cubre: cuentas, ingresos/gastos (3 meses), transferencias, 2 préstamos, 2 tarjetas, metas, presupuestos, recurrencias, pagos.');
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Categoria>  $cats
     */
    private function sembrarFlujoMensual(
        TesoreriaService $tesoreria,
        int $uid,
        int $bancoId,
        int $nequiId,
        int $efectivoId,
        $cats,
        int $mesesAtras
    ): void {
        $base = now()->copy()->subMonths($mesesAtras);
        $factor = $mesesAtras === 0 ? 1.0 : ($mesesAtras === 1 ? 0.92 : 0.88);

        $tesoreria->registrar(
            $uid,
            TipoHechoTesoreria::Ingreso,
            $base->copy()->day(min(28, $base->daysInMonth))->toDateString(),
            (int) round(5_800_000_00 * $factor),
            $bancoId,
            $cats['Salario']->id,
            null,
            'Salario '.$base->locale('es')->isoFormat('MMMM YYYY')
        );

        if ($mesesAtras <= 1) {
            $tesoreria->registrar(
                $uid,
                TipoHechoTesoreria::Ingreso,
                $base->copy()->day(12)->toDateString(),
                (int) round(650_000_00 * $factor),
                $nequiId,
                $cats['Freelance']->id,
                null,
                'Freelance diseño'
            );
        }

        $gastos = [
            [$cats['Vivienda']->id, 1_500_000_00, 5, $bancoId, 'Arriendo'],
            [$cats['Alimentación']->id, 420_000_00, 7, $bancoId, 'Mercado'],
            [$cats['Alimentación']->id, 185_000_00, 18, $efectivoId, 'Restaurantes'],
            [$cats['Transporte']->id, 220_000_00, 9, $nequiId, 'Uber / gasolina'],
            [$cats['Servicios']->id, 160_000_00, 12, $bancoId, 'Servicios públicos'],
            [$cats['Suscripciones']->id, 65_000_00, 8, $nequiId, 'Suscripciones'],
            [$cats['Entretenimiento']->id, 120_000_00, 22, $nequiId, 'Salidas'],
            [$cats['Compras']->id, 95_000_00, 16, $bancoId, 'Compras varias'],
        ];

        foreach ($gastos as [$catId, $monto, $dia, $cuentaId, $desc]) {
            $tesoreria->registrar(
                $uid,
                TipoHechoTesoreria::Gasto,
                $base->copy()->day(min($dia, $base->daysInMonth))->toDateString(),
                (int) round($monto * $factor),
                $cuentaId,
                $catId,
                null,
                $desc.' '.$base->format('m/Y'),
                str_contains($desc, 'Arriendo') || str_contains($desc, 'Servicios') || str_contains($desc, 'Suscripciones')
                    ? 'fijo'
                    : 'variable'
            );
        }
    }
}
