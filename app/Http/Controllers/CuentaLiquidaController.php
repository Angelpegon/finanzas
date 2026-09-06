<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelarCuentaRequest;
use App\Http\Requests\CuentaLiquidaRequest;
use App\Models\CuentaLiquida;
use App\Services\CuentaLiquidaService;
use App\Support\ErrorDominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CuentaLiquidaController extends Controller
{
    public function index(): View
    {
        $cuentas = CuentaLiquida::with('cuentaContable')
            ->where('activa', true)
            ->where('estado', '!=', 'cancelada')
            ->orderBy('nombre')
            ->get();
        $archivadas = CuentaLiquida::with('cuentaContable')
            ->where('activa', false)
            ->where('estado', 'inactiva')
            ->orderBy('nombre')
            ->get();

        return view('cuentas.index', compact('cuentas', 'archivadas'));
    }

    public function create(): View
    {
        return view('cuentas.create');
    }

    public function store(CuentaLiquidaRequest $request, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('create', CuentaLiquida::class);
        $service->crear(Auth::id(), $request->validated());

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta registrada correctamente.');
    }

    public function destroy(CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('delete', $cuenta);
        try {
            $service->archivar(Auth::id(), $cuenta);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta archivada.');
    }

    public function restore(CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('update', $cuenta);
        try {
            $service->restaurar(Auth::id(), $cuenta);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta restaurada.');
    }

    public function cancelar(CancelarCuentaRequest $request, CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('delete', $cuenta);
        $datos = $request->validated();
        try {
            $service->cancelar(
                Auth::id(),
                $cuenta,
                $datos['disposicion'] ?? null,
                isset($datos['cuenta_destino_id']) ? (int) $datos['cuenta_destino_id'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'))->withInput();
        }

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta cancelada.');
    }
}
