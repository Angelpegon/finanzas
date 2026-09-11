<?php

namespace App\Http\Controllers;

use App\Enums\TipoHechoTesoreria;
use App\Http\Requests\PagoRequest;
use App\Http\Requests\TransferenciaRequest;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\HechoTesoreria;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\TarjetaCredito;
use App\Services\PagoService;
use App\Services\PrestamoService;
use App\Services\TarjetaService;
use App\Services\TesoreriaService;
use App\Support\CuentasOperativas;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use App\Support\Idempotencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PagoController extends Controller
{
    public function index(): View
    {
        $usuarioId = (int) Auth::id();
        $cuentas = CuentasOperativas::queryActivas($usuarioId)->orderBy('nombre')->get();

        $pagos = Pago::query()
            ->whereIn('tipo', array_merge(PagoService::TIPOS_GENERICOS, ['prestamo', 'tarjeta']))
            ->with(['cuentaLiquida', 'categoria', 'prestamo', 'tarjetaCredito'])
            ->latest('fecha')
            ->latest('id')
            ->paginate(20, ['*'], 'pagos_page');

        $transferencias = HechoTesoreria::query()
            ->where('tipo', TipoHechoTesoreria::Transferencia)
            ->with(['cuentaLiquida', 'cuentaDestino'])
            ->latest('fecha')
            ->latest('id')
            ->paginate(20, ['*'], 'xfer_page');

        $idsPagos = $pagos->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsHechos = $transferencias->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $idsPagosRevertidos = $idsPagos === []
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

        $idsXferRevertidas = $idsHechos === []
            ? []
            : Asiento::withoutGlobalScopes()
                ->from('asientos as a')
                ->join('asientos as r', 'r.asiento_reversado_id', '=', 'a.id')
                ->where('a.usuario_id', $usuarioId)
                ->where('a.origen_tipo', HechoTesoreria::class)
                ->where('a.es_reverso', false)
                ->whereIn('a.origen_id', $idsHechos)
                ->pluck('a.origen_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $ultimoPagoPorPrestamo = Pago::query()
            ->where('tipo', 'prestamo')
            ->whereNotNull('prestamo_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('prestamo_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $ultimoPagoPorTarjeta = Pago::query()
            ->where('tipo', 'tarjeta')
            ->whereNotNull('tarjeta_credito_id')
            ->selectRaw('MAX(id) as id')
            ->groupBy('tarjeta_credito_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $prestamos = Prestamo::query()
            ->whereIn('estado', ['activa', 'vigente'])
            ->with(['cuotas' => fn ($q) => $q->where('pagada', false)->orderBy('numero')])
            ->orderBy('nombre')
            ->get();

        $tarjetas = TarjetaCredito::query()
            ->where('activa', true)
            ->whereHas('cuotasProgramadas', fn ($q) => $q->where('pagada', false))
            ->orderBy('nombre')
            ->get()
            ->map(function (TarjetaCredito $tarjeta) {
                $proxima = $tarjeta->cuotasProgramadas()
                    ->where('pagada', false)
                    ->orderBy('fecha_vencimiento')
                    ->orderBy('numero')
                    ->orderBy('id')
                    ->first();
                $minimo = $proxima
                    ? (int) $proxima->capital_centavos + (int) $proxima->interes_centavos
                    : 0;

                return (object) [
                    'id' => (int) $tarjeta->id,
                    'nombre' => (string) $tarjeta->nombre,
                    'entidad' => $tarjeta->entidad,
                    'minimo_centavos' => $minimo,
                    'proxima_numero' => $proxima ? (int) $proxima->numero : null,
                ];
            })
            ->values();

        return view('pagos.index', [
            'pagos' => $pagos,
            'transferencias' => $transferencias,
            'cuentas' => $cuentas,
            'categorias' => Categoria::where('tipo', 'gasto')->orderBy('nombre')->get(),
            'prestamos' => $prestamos,
            'tarjetas' => $tarjetas,
            'idsPagosRevertidos' => $idsPagosRevertidos,
            'idsXferRevertidas' => $idsXferRevertidas,
            'ultimoPagoPorPrestamo' => $ultimoPagoPorPrestamo,
            'ultimoPagoPorTarjeta' => $ultimoPagoPorTarjeta,
            'idempotencyKeyPago' => (string) Str::uuid(),
            'idempotencyKeyXfer' => (string) Str::uuid(),
        ]);
    }

    public function store(
        PagoRequest $request,
        PagoService $pagos,
        PrestamoService $prestamos,
        TarjetaService $tarjetas
    ): RedirectResponse {
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        $ambito = match ($d['tipo']) {
            'prestamo' => 'pago_prestamo_movimientos',
            'tarjeta' => 'pago_tarjeta_movimientos',
            default => 'pago_generico',
        };
        Idempotencia::consumir($ambito, $usuarioId, $clave);

        try {
            if ($d['tipo'] === 'prestamo') {
                $prestamo = Prestamo::findOrFail((int) $d['prestamo_id']);
                $this->authorize('update', $prestamo);
                $prestamos->registrarPago(
                    $usuarioId,
                    (int) $d['prestamo_id'],
                    Dinero::pesosACentavos($d['monto']),
                    $d['fecha'],
                    (int) $d['cuenta_liquida_id']
                );
                $mensaje = 'Pago de obligación registrado (cronograma actualizado).';
            } elseif ($d['tipo'] === 'tarjeta') {
                $tarjeta = TarjetaCredito::findOrFail((int) $d['tarjeta_credito_id']);
                $this->authorize('update', $tarjeta);
                $tarjetas->registrarPago(
                    $usuarioId,
                    (int) $d['tarjeta_credito_id'],
                    (int) $d['cuenta_liquida_id'],
                    null,
                    $d['fecha'],
                    Dinero::pesosACentavos($d['monto'])
                );
                $mensaje = 'Pago de tarjeta registrado (cuota y abono extra si aplica).';
            } else {
                $pagos->registrar(
                    $usuarioId,
                    $d['tipo'],
                    Dinero::pesosACentavos($d['monto']),
                    (int) $d['cuenta_liquida_id'],
                    $d['fecha'],
                    $d['destino'],
                    $d['referencia'],
                    $d['observaciones'] ?? null,
                    isset($d['categoria_id']) ? (int) $d['categoria_id'] : null
                );
                $mensaje = 'Pago a tercero registrado y contabilizado.';
            }
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar($ambito, $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'pago')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar($ambito, $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.pagos.index', ['pago' => 1])->with('status', $mensaje);
    }

    public function transferir(TransferenciaRequest $request, TesoreriaService $tesoreria): RedirectResponse
    {
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        $externo = $request->esDestinoExterno();
        $ambito = $externo ? 'transferencia_externa' : 'transferencia';
        Idempotencia::consumir($ambito, $usuarioId, $clave);

        try {
            if ($externo) {
                $etiqueta = trim((string) $d['destino']);
                $detalle = trim((string) ($d['descripcion'] ?? ''));
                $descripcion = $detalle !== ''
                    ? 'Envío a '.$etiqueta.' — '.$detalle
                    : 'Envío a '.$etiqueta;

                $tesoreria->registrar(
                    $usuarioId,
                    TipoHechoTesoreria::Gasto,
                    $d['fecha'],
                    Dinero::pesosACentavos($d['monto']),
                    (int) $d['cuenta_liquida_id'],
                    (int) $d['categoria_id'],
                    null,
                    $descripcion,
                    'variable'
                );

                return redirect()->route('app.pagos.index')->with(
                    'status',
                    'Envío a cuenta externa registrado como gasto. También lo verás en Gastos.'
                );
            }

            $tesoreria->registrar(
                $usuarioId,
                TipoHechoTesoreria::Transferencia,
                $d['fecha'],
                Dinero::pesosACentavos($d['monto']),
                (int) $d['cuenta_liquida_id'],
                null,
                (int) $d['cuenta_destino_id'],
                $d['descripcion'] ?? 'Transferencia entre cuentas propias'
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar($ambito, $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'transferencia')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar($ambito, $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.pagos.index')->with('status', 'Transferencia entre tus cuentas contabilizada.');
    }

    public function corregirPago(
        Request $request,
        Pago $pago,
        PagoService $pagos,
        PrestamoService $prestamos,
        TarjetaService $tarjetas
    ): RedirectResponse {
        $usuarioId = (int) Auth::id();
        $this->authorize('update', $pago);

        $motivo = trim((string) $request->input('motivo', 'Corrección de pago'));
        if ($motivo === '') {
            $motivo = 'Corrección de pago';
        }

        try {
            if ($pago->tipo === 'prestamo') {
                $prestamos->corregirPago($usuarioId, (int) $pago->id, now()->toDateString(), $motivo);
            } elseif ($pago->tipo === 'tarjeta') {
                $tarjetas->corregirPago($usuarioId, (int) $pago->id, now()->toDateString(), $motivo);
            } elseif (in_array($pago->tipo, PagoService::TIPOS_GENERICOS, true)) {
                $pagos->corregir($usuarioId, (int) $pago->id, now()->toDateString(), $motivo);
            } else {
                abort(404);
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.pagos.index', ['pago' => 1])->with('status', 'Pago corregido (reverso contable).');
    }

    public function corregirTransferencia(Request $request, HechoTesoreria $hecho, TesoreriaService $tesoreria): RedirectResponse
    {
        $this->authorize('update', $hecho);
        if ($hecho->tipo !== TipoHechoTesoreria::Transferencia) {
            abort(404);
        }

        $usuarioId = (int) Auth::id();

        $motivo = trim((string) $request->input('motivo', 'Corrección de transferencia'));
        if ($motivo === '') {
            $motivo = 'Corrección de transferencia';
        }

        try {
            $tesoreria->corregirTransferencia($usuarioId, (int) $hecho->id, now()->toDateString(), $motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.pagos.index')->with('status', 'Transferencia corregida (reverso contable).');
    }
}
