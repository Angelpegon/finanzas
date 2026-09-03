<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetaAhorroRequest;
use App\Models\CuentaLiquida;
use App\Models\MetaAhorro;
use App\Services\MetaAhorroService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MetaAhorroController extends Controller
{
    public function index(): View
    {
        return view('metas.index', [
            'metas' => MetaAhorro::with('cuentaLiquida')->orderByRaw("CASE prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END")->get(),
            'cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(MetaAhorroRequest $request, MetaAhorroService $service): RedirectResponse
    {
        $d = $request->validated();
        $service->crear(
            Auth::id(), $d['nombre'], Dinero::pesosACentavos($d['objetivo']),
            Dinero::pesosACentavos($d['monto_actual'] ?? 0), $d['fecha_objetivo'] ?? null,
            Dinero::pesosACentavos($d['aporte_mensual'] ?? 0), isset($d['cuenta_liquida_id']) ? (int) $d['cuenta_liquida_id'] : null,
            $d['prioridad'], $d['estado']
        );

        return redirect()->route('app.metas.index')->with('status', 'Meta de ahorro creada correctamente.');
    }
}
