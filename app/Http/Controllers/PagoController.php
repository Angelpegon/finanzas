<?php

namespace App\Http\Controllers;

use App\Enums\TipoHechoTesoreria;
use App\Http\Requests\PagoRequest;
use App\Http\Requests\TransferenciaRequest;
use App\Models\Categoria;
use App\Models\CuentaLiquida;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use App\Services\PagoService;
use App\Services\TesoreriaService;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PagoController extends Controller
{
    public function index(): View
    {
        return view('pagos.index', [
            'pagos' => Pago::with(['cuentaLiquida', 'categoria'])->latest('fecha')->latest('id')->get(),
            'transferencias' => HechoTesoreria::with(['cuentaLiquida'])
                ->whereIn('tipo', [TipoHechoTesoreria::Transferencia->value, TipoHechoTesoreria::AporteMeta->value])
                ->latest('fecha')->latest('id')->limit(20)->get(),
            'cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get(),
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
            'prestamos' => Prestamo::orderBy('nombre')->get(),
            'tarjetas' => TarjetaCredito::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(PagoRequest $request, PagoService $service): RedirectResponse
    {
        $d = $request->validated();
        try {
            $service->registrar(
                Auth::id(), $d['tipo'], Dinero::pesosACentavos($d['monto']), (int) $d['cuenta_liquida_id'],
                $d['fecha'], $d['destino'], $d['referencia'], $d['observaciones'] ?? null,
                isset($d['categoria_id']) ? (int) $d['categoria_id'] : null,
                isset($d['prestamo_id']) ? (int) $d['prestamo_id'] : null,
                isset($d['tarjeta_credito_id']) ? (int) $d['tarjeta_credito_id'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'pago')->withInput();
        }

        return redirect()->route('app.pagos.index')->with('status', 'Pago registrado una sola vez y contabilizado.');
    }

    public function transferir(TransferenciaRequest $request, TesoreriaService $tesoreria): RedirectResponse
    {
        $d = $request->validated();
        try {
            $tesoreria->registrar(
                Auth::id(),
                TipoHechoTesoreria::Transferencia,
                $d['fecha'],
                Dinero::pesosACentavos($d['monto']),
                (int) $d['cuenta_liquida_id'],
                null,
                (int) $d['cuenta_destino_id'],
                $d['descripcion'] ?? 'Transferencia entre cuentas'
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'transferencia')->withInput();
        }

        return redirect()->route('app.pagos.index')->with('status', 'Transferencia contabilizada.');
    }
}
