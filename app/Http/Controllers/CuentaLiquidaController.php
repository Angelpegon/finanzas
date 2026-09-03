<?php

namespace App\Http\Controllers;

use App\Http\Requests\CuentaLiquidaRequest;
use App\Models\CuentaLiquida;
use App\Services\CuentaLiquidaService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CuentaLiquidaController extends Controller
{
    public function index(): View
    {
        $cuentas = CuentaLiquida::with('cuentaContable')->where('activa', true)->get();
        return view('cuentas.index', compact('cuentas'));
    }

    public function create(): View
    {
        return view('cuentas.create');
    }

    public function store(CuentaLiquidaRequest $request, CuentaLiquidaService $service): RedirectResponse
    {
        $service->crear(Auth::id(), $request->validated());
        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta registrada correctamente.');
    }

    public function destroy(CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('delete', $cuenta);
        $service->archivar(Auth::id(), $cuenta);
        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta archivada.');
    }
}
