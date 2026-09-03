<?php

namespace App\Http\Controllers;

use App\Http\Requests\AporteMetaRequest;
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
            'metas' => MetaAhorro::with('cuentaLiquida')
                ->orderByRaw("CASE prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END")
                ->get(),
            'cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(MetaAhorroRequest $request, MetaAhorroService $service): RedirectResponse
    {
        $this->authorize('create', MetaAhorro::class);
        $d = $request->validated();
        $service->crear(
            Auth::id(),
            $d['nombre'],
            Dinero::pesosACentavos($d['objetivo']),
            $d['fecha_objetivo'] ?? null,
            Dinero::pesosACentavos($d['aporte_mensual'] ?? 0),
            (int) $d['cuenta_liquida_id'],
            $d['prioridad'],
            $d['estado']
        );

        return redirect()->route('app.metas.index')->with('status', 'Meta creada. El avance solo crece con aportes contabilizados.');
    }

    public function aportar(AporteMetaRequest $request, MetaAhorroService $service): RedirectResponse
    {
        $d = $request->validated();
        $meta = MetaAhorro::findOrFail($d['meta_ahorro_id']);
        $this->authorize('update', $meta);
        try {
            $service->aportar(
                Auth::id(),
                (int) $d['meta_ahorro_id'],
                Dinero::pesosACentavos($d['monto']),
                (int) $d['cuenta_liquida_id'],
                $d['fecha'],
                $d['descripcion'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['monto' => $e->getMessage()]);
        }

        return redirect()->route('app.metas.index')->with('status', 'Aporte contabilizado en el libro.');
    }
}
