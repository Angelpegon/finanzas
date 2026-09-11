<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchivarCuentaRequest;
use App\Http\Requests\CancelarCuentaRequest;
use App\Http\Requests\CuentaLiquidaRequest;
use App\Models\CuentaContable;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Services\CuentaLiquidaService;
use App\Support\CuentasOperativas;
use App\Support\ErrorDominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CuentaLiquidaController extends Controller
{
    public function index(): View
    {
        $usuarioId = (int) Auth::id();
        $cuentas = CuentasOperativas::queryActivas($usuarioId)
            ->with('cuentaContable')
            ->orderBy('nombre')
            ->get();
        $archivadas = CuentasOperativas::queryArchivadas($usuarioId)
            ->with('cuentaContable')
            ->orderBy('nombre')
            ->get();

        $contableIds = $cuentas->concat($archivadas)
            ->pluck('cuenta_contable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $saldos = CuentaContable::saldosCentavosMap($usuarioId, $contableIds);

        return view('cuentas.index', [
            'cuentas' => $cuentas,
            'archivadas' => $archivadas,
            'saldos' => $saldos,
        ]);
    }

    public function create(): View
    {
        return view('cuentas.create', [
            'tipos' => CuentasOperativas::etiquetasTipo(),
        ]);
    }

    public function store(CuentaLiquidaRequest $request, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('create', CuentaLiquida::class);
        $service->crear(Auth::id(), $request->validated());

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta registrada correctamente.');
    }

    public function show(CuentaLiquida $cuenta): View
    {
        $this->authorize('view', $cuenta);
        $this->assertOperativaVisible($cuenta);

        $usuarioId = (int) Auth::id();
        $saldos = CuentaContable::saldosCentavosMap($usuarioId, [(int) $cuenta->cuenta_contable_id]);
        $saldo = (int) ($saldos[(int) $cuenta->cuenta_contable_id] ?? 0);

        $hechos = HechoTesoreria::query()
            ->where(function ($q) use ($cuenta): void {
                $q->where('cuenta_liquida_id', $cuenta->id)
                    ->orWhere('cuenta_destino_id', $cuenta->id);
            })
            ->with('categoria')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $pagos = Pago::query()
            ->where('cuenta_liquida_id', $cuenta->id)
            ->with('categoria')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('cuentas.show', [
            'cuenta' => $cuenta,
            'saldo' => $saldo,
            'hechos' => $hechos,
            'pagos' => $pagos,
            'tipoEtiqueta' => CuentasOperativas::etiquetaTipo($cuenta->tipo),
        ]);
    }

    public function edit(CuentaLiquida $cuenta): View
    {
        $this->authorize('update', $cuenta);
        $this->assertOperativaVisible($cuenta);

        return view('cuentas.edit', [
            'cuenta' => $cuenta,
            'tipos' => CuentasOperativas::etiquetasTipo(),
        ]);
    }

    public function update(CuentaLiquidaRequest $request, CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('update', $cuenta);
        $this->assertOperativaVisible($cuenta);
        try {
            $service->actualizar(Auth::id(), $cuenta, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'))->withInput();
        }

        return redirect()->route('app.cuentas.show', $cuenta)->with('status', 'Cuenta actualizada.');
    }

    public function destroy(ArchivarCuentaRequest $request, CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('delete', $cuenta);
        $this->assertOperativaVisible($cuenta);
        $destino = $request->validated('cuenta_destino_id') ?? null;
        try {
            $service->archivar(
                Auth::id(),
                $cuenta,
                $destino !== null ? (int) $destino : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'))->withInput();
        }

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta archivada.');
    }

    public function restore(CuentaLiquida $cuenta, CuentaLiquidaService $service): RedirectResponse
    {
        $this->authorize('update', $cuenta);
        $this->assertOperativaVisible($cuenta);
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
        $this->assertOperativaVisible($cuenta);
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

        return redirect()->route('app.cuentas.index')->with('status', 'Cuenta cancelada. Ya no aparece en el sistema operativo.');
    }

    private function assertOperativaVisible(CuentaLiquida $cuenta): void
    {
        if ($cuenta->estado === 'cancelada') {
            abort(404);
        }

        $bolsillos = CuentasOperativas::idsBolsillosActivos((int) Auth::id());
        if (in_array((int) $cuenta->id, $bolsillos, true)) {
            abort(404);
        }
    }
}
