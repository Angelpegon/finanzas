<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompraTarjetaRequest;
use App\Http\Requests\PagoTarjetaRequest;
use App\Http\Requests\TarjetaRequest;
use App\Models\Categoria;
use App\Models\CompraTarjeta;
use App\Models\CuentaLiquida;
use App\Models\CuotaTarjeta;
use App\Models\TarjetaCredito;
use App\Services\TarjetaService;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TarjetaController extends Controller
{
    public function index(): View
    {
        return view('tarjetas.index', [
            'tarjetas' => TarjetaCredito::with('compras.cuotasProgramadas')->get(),
            'cuentas' => CuentaLiquida::where('activa', true)->orderBy('nombre')->get(),
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('tarjetas.create');
    }

    public function store(TarjetaRequest $request, TarjetaService $service): RedirectResponse
    {
        $d = $request->validated();
        $service->crear(Auth::id(), $d['nombre'], Dinero::pesosACentavos($d['cupo']), (int) $d['dia_corte'], (int) $d['dia_pago'], (float) $d['tasa']);
        $tarjeta = TarjetaCredito::withoutGlobalScopes()->where('usuario_id', Auth::id())->latest('id')->firstOrFail();
        $tarjeta->update(['entidad' => $d['entidad']]);

        return redirect()->route('app.tarjetas.index')->with('status', 'Tarjeta registrada correctamente.');
    }

    public function compra(CompraTarjetaRequest $request, TarjetaService $service): RedirectResponse
    {
        $d = $request->validated();
        $service->registrarCompra(Auth::id(), (int) $d['tarjeta_credito_id'], Dinero::pesosACentavos($d['monto']), (int) $d['cuotas'], $d['fecha'], (int) $d['categoria_id'], $d['descripcion'], (float) ($d['interes'] ?? 0));

        return redirect()->route('app.tarjetas.index')->with('status', 'Compra financiada registrada con su cronograma.');
    }

    public function pagar(PagoTarjetaRequest $request, TarjetaService $service): RedirectResponse
    {
        $d = $request->validated();
        $service->registrarPago(Auth::id(), (int) $d['tarjeta_credito_id'], (int) $d['cuenta_liquida_id'], (int) $d['cuota_tarjeta_id'], $d['fecha']);

        return redirect()->route('app.tarjetas.index')->with('status', 'Pago de cuota registrado.');
    }
}
