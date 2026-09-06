<?php

namespace App\Http\Controllers;

use App\Http\Requests\ObligacionRequest;
use App\Http\Requests\PagoPrestamoRequest;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use App\Models\CuentaLiquida;
use App\Services\PrestamoService;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DeudaController extends Controller
{
    public function index(): View
    {
        return view('deudas.index', [
            'prestamos' => Prestamo::with('cuotas')->get(),
            'tarjetas' => TarjetaCredito::with('compras.cuotasProgramadas')->get(),
        ]);
    }

    public function create(): View
    {
        return view('deudas.create', ['cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get()]);
    }

    public function store(ObligacionRequest $request, PrestamoService $service): RedirectResponse
    {
        $this->authorize('create', Prestamo::class);
        $d = $request->validated();
        try {
            $service->crear(
                Auth::id(), $d['nombre'], Dinero::pesosACentavos($d['monto_inicial']),
                (float) $d['tasa_interes'], (int) $d['numero_cuotas'], $d['fecha_inicio'],
                (int) date('d', strtotime($d['fecha_vencimiento'] ?: $d['fecha_inicio'])),
                (int) $d['cuenta_liquida_id'], $d['entidad'] ?? null, $d['tipo_obligacion'],
                $d['tipo_tasa'], $d['periodicidad'], $d['fecha_vencimiento'] ?? null,
                $d['metodo_amortizacion'], Dinero::pesosACentavos($d['seguro'] ?? 0), Dinero::pesosACentavos($d['otros_cargos'] ?? 0)
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'monto_inicial'))->withInput();
        }

        return redirect()->route('app.deudas.index')->with('status', 'Obligación registrada correctamente.');
    }

    public function pagar(PagoPrestamoRequest $request, PrestamoService $service): RedirectResponse
    {
        $d = $request->validated();
        $prestamo = Prestamo::findOrFail($d['prestamo_id']);
        $this->authorize('update', $prestamo);
        try {
            $service->registrarPago(
                Auth::id(),
                (int) $d['prestamo_id'],
                Dinero::pesosACentavos($d['monto']),
                $d['fecha']
            );
        } catch (\InvalidArgumentException $e) {
            $errores = ErrorDominio::aCampo($e, 'monto');
            // El formulario de pago no elige cuenta: sale del préstamo.
            if (isset($errores['cuenta_liquida_id'])) {
                $errores = ['form' => $errores['cuenta_liquida_id']];
            }

            return back()->withErrors($errores, 'pago_prestamo')->withInput();
        }

        return redirect()->route('app.deudas.index')->with('status', 'Pago de préstamo registrado.');
    }
}
