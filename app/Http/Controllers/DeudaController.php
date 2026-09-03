<?php

namespace App\Http\Controllers;

use App\Http\Requests\ObligacionRequest;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use App\Models\CuentaLiquida;
use App\Services\PrestamoService;
use App\Support\Dinero;
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
        $d = $request->validated();
        $service->crear(
            Auth::id(), $d['nombre'], Dinero::pesosACentavos($d['monto_inicial']),
            (float) $d['tasa_interes'], (int) $d['numero_cuotas'], $d['fecha_inicio'],
            (int) date('d', strtotime($d['fecha_vencimiento'] ?: $d['fecha_inicio'])),
            (int) $d['cuenta_liquida_id'], $d['entidad'] ?? null, $d['tipo_obligacion'],
            $d['tipo_tasa'], $d['periodicidad'], $d['fecha_vencimiento'] ?? null
            ,$d['metodo_amortizacion'], Dinero::pesosACentavos($d['seguro'] ?? 0), Dinero::pesosACentavos($d['otros_cargos'] ?? 0)
        );
        return redirect()->route('app.deudas.index')->with('status', 'Obligación registrada correctamente.');
    }
}
