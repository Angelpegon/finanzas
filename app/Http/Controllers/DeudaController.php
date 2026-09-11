<?php

namespace App\Http\Controllers;

use App\Http\Requests\ObligacionRequest;
use App\Http\Requests\PagoPrestamoRequest;
use App\Models\Asiento;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use App\Services\PrestamoService;
use App\Support\CuentasOperativas;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use App\Support\Idempotencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeudaController extends Controller
{
    public function index(): View
    {
        $usuarioId = (int) Auth::id();
        $prestamos = Prestamo::with('cuotas')->orderByDesc('id')->get();
        $pagosRecientes = Pago::query()
            ->where('tipo', 'prestamo')
            ->with(['prestamo', 'cuentaLiquida'])
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

        $ultimoPagoPorPrestamo = Pago::query()
            ->where('tipo', 'prestamo')
            ->whereNotNull('prestamo_id')
            ->orderByDesc('id')
            ->get(['id', 'prestamo_id'])
            ->unique('prestamo_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('deudas.index', [
            'prestamos' => $prestamos,
            'tarjetas' => TarjetaCredito::with('compras.cuotasProgramadas')->get(),
            'cuentasPago' => CuentasOperativas::queryActivas($usuarioId)->orderBy('nombre')->get(),
            'pagosRecientes' => $pagosRecientes,
            'idsRevertidos' => $idsRevertidos,
            'ultimoPagoPorPrestamo' => $ultimoPagoPorPrestamo,
            'idempotencyKeysPago' => $prestamos->mapWithKeys(
                fn (Prestamo $p) => [$p->id => (string) Str::uuid()]
            )->all(),
        ]);
    }

    public function create(): View
    {
        $usuarioId = (int) Auth::id();
        $cuentas = CuentasOperativas::queryActivas($usuarioId)->orderBy('nombre')->get();

        return view('deudas.create', [
            'cuentas' => $cuentas,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(ObligacionRequest $request, PrestamoService $service): RedirectResponse
    {
        $this->authorize('create', Prestamo::class);
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('deuda', $usuarioId, $clave);

        try {
            $service->crear(
                $usuarioId,
                $d['nombre'],
                Dinero::pesosACentavos($d['monto_inicial']),
                (float) $d['tasa_interes'],
                (int) $d['numero_cuotas'],
                $d['fecha_inicio'],
                (int) $d['dia_pago'],
                (int) $d['cuenta_liquida_id'],
                $d['entidad'] ?? null,
                $d['tipo_obligacion'],
                $d['tipo_tasa'],
                $d['periodicidad'],
                $d['fecha_vencimiento'] ?? null,
                $d['metodo_amortizacion'],
                Dinero::pesosACentavos($d['seguro'] ?? 0),
                Dinero::pesosACentavos($d['otros_cargos'] ?? 0)
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('deuda', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto_inicial'))->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('deuda', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.deudas.index')->with('status', 'Obligación registrada correctamente.');
    }

    public function pagar(PagoPrestamoRequest $request, PrestamoService $service): RedirectResponse
    {
        $d = $request->validated();
        $prestamo = Prestamo::findOrFail($d['prestamo_id']);
        $this->authorize('update', $prestamo);
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('pago_prestamo', $usuarioId, $clave);

        try {
            $service->registrarPago(
                $usuarioId,
                (int) $d['prestamo_id'],
                Dinero::pesosACentavos($d['monto']),
                $d['fecha'],
                isset($d['cuenta_liquida_id']) ? (int) $d['cuenta_liquida_id'] : null
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('pago_prestamo', $usuarioId, $clave);
            $errores = ErrorDominio::aCampo($e, 'monto');

            return back()->withErrors($errores, 'pago_prestamo')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('pago_prestamo', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.deudas.index')->with('status', 'Pago de préstamo registrado.');
    }

    public function corregirPago(Request $request, Pago $pago, PrestamoService $service): RedirectResponse
    {
        $usuarioId = (int) Auth::id();
        if ((int) $pago->usuario_id !== $usuarioId || $pago->tipo !== 'prestamo') {
            abort(404);
        }

        $motivo = trim((string) $request->input('motivo', 'Corrección de pago de préstamo'));
        if ($motivo === '') {
            $motivo = 'Corrección de pago de préstamo';
        }

        try {
            $service->corregirPago($usuarioId, (int) $pago->id, now()->toDateString(), $motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.deudas.index')->with('status', 'Pago corregido (reverso contable). El cronograma se restauró.');
    }
}
