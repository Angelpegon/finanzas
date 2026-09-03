<?php

namespace App\Http\Controllers;

use App\Http\Requests\PagoRequest;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use App\Services\PagoService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PagoController extends Controller
{
    public function index(): View
    {
        return view('pagos.index', [
            'pagos' => Pago::with(['cuentaLiquida', 'categoria'])->latest('fecha')->latest('id')->get(),
            'cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get(),
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
            'prestamos' => Prestamo::orderBy('nombre')->get(),
            'tarjetas' => TarjetaCredito::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(PagoRequest $request, PagoService $service): RedirectResponse
    {
        $d = $request->validated();
        $service->registrar(
            Auth::id(), $d['tipo'], Dinero::pesosACentavos($d['monto']), (int) $d['cuenta_liquida_id'],
            $d['fecha'], $d['destino'], $d['referencia'], $d['observaciones'] ?? null,
            isset($d['categoria_id']) ? (int) $d['categoria_id'] : null,
            isset($d['prestamo_id']) ? (int) $d['prestamo_id'] : null,
            isset($d['tarjeta_credito_id']) ? (int) $d['tarjeta_credito_id'] : null
        );

        return redirect()->route('app.pagos.index')->with('status', 'Pago registrado una sola vez y contabilizado.');
    }
}
