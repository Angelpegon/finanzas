<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoriaRequest;
use App\Http\Requests\PresupuestoRequest;
use App\Models\Categoria;
use App\Models\Presupuesto;
use App\Services\CategoriaService;
use App\Services\PresupuestoService;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use App\Support\Idempotencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PresupuestoController extends Controller
{
    public function index(Request $request, PresupuestoService $service): View
    {
        $usuarioId = (int) Auth::id();
        $anio = (int) $request->integer('anio', (int) now()->year);
        $mes = (int) $request->integer('mes', (int) now()->month);
        if ($anio < 2020 || $anio > 2100) {
            $anio = (int) now()->year;
        }
        if ($mes < 1 || $mes > 12) {
            $mes = (int) now()->month;
        }

        $presupuesto = Presupuesto::with('lineas.categoria')
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();
        $service->enriquecer($presupuesto);

        $categorias = Categoria::where('tipo', 'gasto')->orderBy('nombre')->get();
        $categoriasIngreso = Categoria::where('tipo', 'ingreso')->orderBy('nombre')->get();

        return view('presupuestos.index', [
            'presupuesto' => $presupuesto,
            'categorias' => $categorias,
            'categoriasIngreso' => $categoriasIngreso,
            'anio' => $anio,
            'mes' => $mes,
            'idempotencyKey' => (string) Str::uuid(),
            'idempotencyKeyCategoria' => (string) Str::uuid(),
        ]);
    }

    public function store(PresupuestoRequest $request, PresupuestoService $service): RedirectResponse
    {
        $this->authorize('create', Presupuesto::class);
        $datos = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $datos['idempotency_key'];
        Idempotencia::consumir('presupuesto', $usuarioId, $clave);

        $lineas = [];
        foreach ($datos['categoria_ids'] as $indice => $categoriaId) {
            $monto = trim((string) ($datos['lineas'][$indice] ?? ''));
            if ($monto !== '') {
                $lineas[(int) $categoriaId] = Dinero::pesosACentavos($monto);
            }
        }

        if ($lineas === []) {
            Idempotencia::liberar('presupuesto', $usuarioId, $clave);

            return back()->withErrors(['lineas' => 'Ingresa al menos un monto presupuestado.'])->withInput();
        }

        try {
            $service->guardar($usuarioId, (int) $datos['anio'], (int) $datos['mes'], $lineas, $datos['umbrales']);
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('presupuesto', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'lineas'))->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('presupuesto', $usuarioId, $clave);
            throw $e;
        }

        return redirect()
            ->route('app.presupuestos.index', ['anio' => $datos['anio'], 'mes' => $datos['mes']])
            ->with('status', 'Presupuesto guardado correctamente.');
    }

    public function storeCategoria(CategoriaRequest $request, CategoriaService $service): RedirectResponse
    {
        $this->authorize('create', Categoria::class);
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('categoria', $usuarioId, $clave);

        try {
            $service->crear($usuarioId, $d['nombre'], $d['tipo']);
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('categoria', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'nombre'), 'categoria')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('categoria', $usuarioId, $clave);
            throw $e;
        }

        return redirect()
            ->route('app.presupuestos.index', [
                'anio' => $request->integer('anio', (int) now()->year),
                'mes' => $request->integer('mes', (int) now()->month),
            ])
            ->with('status', 'Categoría creada.');
    }

    public function updateCategoria(CategoriaRequest $request, Categoria $categoria, CategoriaService $service): RedirectResponse
    {
        $this->authorize('update', $categoria);
        $d = $request->validated();

        try {
            $service->actualizar((int) Auth::id(), (int) $categoria->id, $d['nombre']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'nombre'), 'categoria')->withInput();
        }

        return redirect()
            ->route('app.presupuestos.index', [
                'anio' => $request->integer('anio', (int) now()->year),
                'mes' => $request->integer('mes', (int) now()->month),
            ])
            ->with('status', 'Categoría actualizada.');
    }
}
