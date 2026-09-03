<?php

namespace App\Http\Controllers;

use App\Http\Requests\PresupuestoRequest;
use App\Models\Categoria;
use App\Models\Presupuesto;
use App\Services\PresupuestoService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PresupuestoController extends Controller
{
    public function index(): View
    {
        $presupuesto = Presupuesto::with('lineas.categoria')
            ->where('anio', now()->year)->where('mes', now()->month)->first();

        return view('presupuestos.index', [
            'presupuesto' => $presupuesto,
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
        ]);
    }

    public function store(PresupuestoRequest $request, PresupuestoService $service): RedirectResponse
    {
        $datos = $request->validated();
        $lineas = [];
        foreach ($datos['categoria_ids'] as $indice => $categoriaId) {
            $monto = trim((string) ($datos['lineas'][$indice] ?? ''));
            if ($monto !== '') {
                $lineas[(int) $categoriaId] = Dinero::pesosACentavos($monto);
            }
        }
        if ($lineas === []) {
            return back()->withErrors(['lineas' => 'Ingresa al menos un monto presupuestado.'])->withInput();
        }
        $service->guardar(Auth::id(), (int) $datos['anio'], (int) $datos['mes'], $lineas, $datos['umbrales']);

        return redirect()->route('app.presupuestos.index')->with('status', 'Presupuesto guardado correctamente.');
    }
}
