<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompraTarjetaRequest;
use App\Http\Requests\PagoTarjetaRequest;
use App\Http\Requests\TarjetaRequest;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\CompraTarjeta;
use App\Models\Pago;
use App\Models\TarjetaCredito;
use App\Services\TarjetaService;
use App\Support\CuentasOperativas;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use App\Support\Idempotencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TarjetaController extends Controller
{
    public function index(): View
    {
        $usuarioId = (int) Auth::id();
        $tarjetas = TarjetaCredito::query()
            ->where('activa', true)
            ->with(['cuentaContable', 'compras.cuotasProgramadas'])
            ->orderBy('nombre')
            ->get();

        $pagosRecientes = Pago::query()
            ->where('tipo', 'tarjeta')
            ->with(['tarjetaCredito', 'cuentaLiquida'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $idsPagos = $pagosRecientes->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsRevertidos = $idsPagos === []
            ? []
            : Asiento::withoutGlobalScopes()
                ->from('asientos as a')
                ->join('asientos as r', 'r.asiento_reversado_id', '=', 'a.id')
                ->where('a.usuario_id', $usuarioId)
                ->where('a.origen_tipo', Pago::class)
                ->where('a.es_reverso', false)
                ->whereIn('a.origen_id', $idsPagos)
                ->pluck('a.origen_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $ultimoPagoPorTarjeta = Pago::query()
            ->where('tipo', 'tarjeta')
            ->whereNotNull('tarjeta_credito_id')
            ->orderByDesc('id')
            ->get(['id', 'tarjeta_credito_id'])
            ->unique('tarjeta_credito_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('tarjetas.index', [
            'tarjetas' => $tarjetas,
            'cuentas' => CuentasOperativas::queryActivas($usuarioId)->orderBy('nombre')->get(),
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
            'pagosRecientes' => $pagosRecientes,
            'idsRevertidos' => $idsRevertidos,
            'ultimoPagoPorTarjeta' => $ultimoPagoPorTarjeta,
            'idempotencyKeyCompra' => (string) Str::uuid(),
            'idempotencyKeysPago' => $tarjetas->mapWithKeys(
                fn (TarjetaCredito $t) => [$t->id => (string) Str::uuid()]
            )->all(),
        ]);
    }

    public function create(): View
    {
        return view('tarjetas.create', [
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(TarjetaRequest $request, TarjetaService $service): RedirectResponse
    {
        $this->authorize('create', TarjetaCredito::class);
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('tarjeta', $usuarioId, $clave);

        try {
            $service->crear(
                $usuarioId,
                $d['nombre'],
                Dinero::pesosACentavos($d['cupo']),
                (int) $d['dia_corte'],
                (int) $d['dia_pago'],
                (float) $d['tasa_compras_mensual'],
                (float) $d['tasa_avances_mensual'],
                $d['entidad']
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('tarjeta', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'cupo'))->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('tarjeta', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.tarjetas.index')->with('status', 'Tarjeta registrada correctamente.');
    }

    public function compra(CompraTarjetaRequest $request, TarjetaService $service): RedirectResponse
    {
        $d = $request->validated();
        $tarjeta = TarjetaCredito::findOrFail($d['tarjeta_credito_id']);
        $this->authorize('update', $tarjeta);
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('compra_tarjeta', $usuarioId, $clave);

        try {
            $service->registrarCompra(
                $usuarioId,
                (int) $d['tarjeta_credito_id'],
                Dinero::pesosACentavos($d['monto']),
                (int) $d['cuotas'],
                $d['fecha'],
                $d['tipo'],
                isset($d['categoria_id']) ? (int) $d['categoria_id'] : null,
                isset($d['cuenta_liquida_id']) ? (int) $d['cuenta_liquida_id'] : null,
                $d['descripcion']
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('compra_tarjeta', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'compra')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('compra_tarjeta', $usuarioId, $clave);
            throw $e;
        }

        $msg = $d['tipo'] === 'avance'
            ? 'Avance registrado con su cronograma.'
            : 'Compra financiada registrada con su cronograma.';

        return redirect()->route('app.tarjetas.index')->with('status', $msg);
    }

    public function pagar(PagoTarjetaRequest $request, TarjetaService $service): RedirectResponse
    {
        $d = $request->validated();
        $tarjeta = TarjetaCredito::findOrFail($d['tarjeta_credito_id']);
        $this->authorize('update', $tarjeta);
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('pago_tarjeta', $usuarioId, $clave);

        try {
            $monto = array_key_exists('monto', $d) && $d['monto'] !== null && $d['monto'] !== ''
                ? Dinero::pesosACentavos($d['monto'])
                : null;
            $service->registrarPago(
                $usuarioId,
                (int) $d['tarjeta_credito_id'],
                (int) $d['cuenta_liquida_id'],
                (int) $d['cuota_tarjeta_id'],
                $d['fecha'],
                $monto
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('pago_tarjeta', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'pago_tarjeta')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('pago_tarjeta', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.tarjetas.index')->with('status', 'Pago de tarjeta registrado.');
    }

    public function corregirPago(Request $request, Pago $pago, TarjetaService $service): RedirectResponse
    {
        $usuarioId = (int) Auth::id();
        if ((int) $pago->usuario_id !== $usuarioId || $pago->tipo !== 'tarjeta') {
            abort(404);
        }

        $motivo = trim((string) $request->input('motivo', 'Corrección de pago de tarjeta'));
        if ($motivo === '') {
            $motivo = 'Corrección de pago de tarjeta';
        }

        try {
            $service->corregirPago($usuarioId, (int) $pago->id, now()->toDateString(), $motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.tarjetas.index')->with('status', 'Pago corregido (reverso contable). La cuota quedó pendiente.');
    }

    public function corregirCompra(Request $request, CompraTarjeta $compra, TarjetaService $service): RedirectResponse
    {
        $usuarioId = (int) Auth::id();
        if ((int) $compra->usuario_id !== $usuarioId) {
            abort(404);
        }

        $this->authorize('update', TarjetaCredito::findOrFail($compra->tarjeta_credito_id));

        $motivo = trim((string) $request->input('motivo', 'Corrección de compra con tarjeta'));
        if ($motivo === '') {
            $motivo = 'Corrección de compra con tarjeta';
        }

        try {
            $service->corregirCompra($usuarioId, (int) $compra->id, now()->toDateString(), $motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'), 'compra');
        }

        return redirect()->route('app.tarjetas.index')->with('status', 'Operación anulada (reverso contable).');
    }
}
