<?php

namespace App\Http\Controllers;

use App\Enums\TipoHechoTesoreria;
use App\Http\Requests\IngresoRequest;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Services\RecurrenciaService;
use App\Services\TesoreriaService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class IngresoController extends Controller
{
    public function create(\App\Services\MetaAhorroService $metas): View
    {
        $bolsilloIds = $metas->idsBolsillos(Auth::id());

        return view('ingresos.create', [
            'categorias' => Categoria::where('tipo', 'ingreso')->orderBy('nombre')->get(),
            'cuentas' => CuentaLiquida::where('activa', true)
                ->when($bolsilloIds !== [], fn ($q) => $q->whereNotIn('id', $bolsilloIds))
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(IngresoRequest $request, TesoreriaService $tesoreria, RecurrenciaService $recurrencias): RedirectResponse
    {
        $datos = $request->validated();
        $usuarioId = Auth::id();
        $monto = Dinero::pesosACentavos($datos['monto']);

        if ($request->boolean('recurrente')) {
            $recurrencias->crear(
                $usuarioId, 'ingreso', $datos['descripcion'] ?: $this->nombreCategoria($datos['categoria_id']),
                $monto, (int) date('d', strtotime($datos['fecha'])), $datos['periodicidad'],
                null,
                (int) $datos['categoria_id'], (int) $datos['cuenta_liquida_id']
            );
            return redirect()->route('app.situacion')->with('status', 'Ingreso recurrente guardado como proyección. No se registró como recibido.');
        }

        $tesoreria->registrar(
            $usuarioId, TipoHechoTesoreria::Ingreso, $datos['fecha'], $monto,
            (int) $datos['cuenta_liquida_id'], (int) $datos['categoria_id'], null, $datos['descripcion'] ?? null
        );
        return redirect()->route('app.situacion')->with('status', 'Ingreso recibido registrado.');
    }

    private function nombreCategoria(int $id): string
    {
        return Categoria::whereKey($id)->value('nombre') ?? 'Ingreso recurrente';
    }
}
