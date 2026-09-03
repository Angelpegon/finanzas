<?php

namespace App\Http\Controllers;

use App\Enums\TipoHechoTesoreria;
use App\Http\Requests\GastoRequest;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Services\RecurrenciaService;
use App\Services\TesoreriaService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GastoController extends Controller
{
    public function create(): View
    {
        return view('gastos.create', [
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
            'cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(GastoRequest $request, TesoreriaService $tesoreria, RecurrenciaService $recurrencias): RedirectResponse
    {
        $datos = $request->validated();
        $monto = Dinero::pesosACentavos($datos['monto']);
        if ($request->boolean('proyectado')) {
            $recurrencias->crear(
                Auth::id(), 'gasto', $datos['descripcion'] ?: 'Gasto proyectado',
                $monto, (int) date('d', strtotime($datos['fecha'])), $datos['periodicidad'],
                $datos['tipo_gasto'], (int) $datos['categoria_id'], (int) $datos['cuenta_liquida_id']
            );
            return redirect()->route('app.situacion')->with('status', 'Gasto guardado como proyección. No se descontó de tu cuenta.');
        }

        $tesoreria->registrar(
            Auth::id(), TipoHechoTesoreria::Gasto, $datos['fecha'], $monto,
            (int) $datos['cuenta_liquida_id'], (int) $datos['categoria_id'], null,
            $datos['descripcion'] ?? null, $datos['tipo_gasto']
        );
        return redirect()->route('app.situacion')->with('status', 'Gasto real registrado.');
    }
}
