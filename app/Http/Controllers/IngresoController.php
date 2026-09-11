<?php

namespace App\Http\Controllers;

use App\Enums\TipoHechoTesoreria;
use App\Http\Requests\IngresoRequest;
use App\Models\Asiento;
use App\Models\Categoria;
use App\Models\HechoTesoreria;
use App\Services\ContabilizacionService;
use App\Services\RecurrenciaService;
use App\Services\TesoreriaService;
use App\Support\CuentasOperativas;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use App\Support\Idempotencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class IngresoController extends Controller
{
    public function index(): View
    {
        $usuarioId = (int) Auth::id();
        $ingresos = HechoTesoreria::query()
            ->where('tipo', TipoHechoTesoreria::Ingreso)
            ->with(['categoria', 'cuentaLiquida'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(20);

        $idsPagina = $ingresos->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsRevertidos = $idsPagina === []
            ? []
            : Asiento::withoutGlobalScopes()
                ->from('asientos as a')
                ->join('asientos as r', 'r.asiento_reversado_id', '=', 'a.id')
                ->where('a.usuario_id', $usuarioId)
                ->where('a.origen_tipo', HechoTesoreria::class)
                ->where('a.es_reverso', false)
                ->whereIn('a.origen_id', $idsPagina)
                ->pluck('a.origen_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        return view('ingresos.index', [
            'ingresos' => $ingresos,
            'idsRevertidos' => $idsRevertidos,
        ]);
    }

    public function create(): View
    {
        $usuarioId = (int) Auth::id();
        $cuentas = CuentasOperativas::queryActivas($usuarioId)->orderBy('nombre')->get();

        return view('ingresos.create', [
            'categorias' => Categoria::where('tipo', 'ingreso')->orderBy('nombre')->get(),
            'cuentas' => $cuentas,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        IngresoRequest $request,
        TesoreriaService $tesoreria,
        RecurrenciaService $recurrencias
    ): RedirectResponse {
        $datos = $request->validated();
        $usuarioId = (int) Auth::id();
        $claveIdem = $datos['idempotency_key'];
        Idempotencia::consumir('ingreso', $usuarioId, $claveIdem);

        $monto = Dinero::pesosACentavos($datos['monto']);
        $recurrente = $request->boolean('recurrente');
        // Cobro real: siempre si no es recurrente; si es recurrente, solo si marcaron "recibido".
        $recibido = $recurrente ? $request->boolean('recibido') : true;

        try {
            DB::transaction(function () use ($recurrencias, $tesoreria, $usuarioId, $datos, $monto, $recurrente, $recibido) {
                if ($recurrente) {
                    $recurrencias->crear(
                        $usuarioId,
                        'ingreso',
                        $datos['descripcion'] ?: $this->nombreCategoria((int) $datos['categoria_id']),
                        $monto,
                        (int) date('d', strtotime($datos['fecha'])),
                        $datos['periodicidad'],
                        null,
                        (int) $datos['categoria_id'],
                        (int) $datos['cuenta_liquida_id']
                    );
                }

                if ($recibido) {
                    $tesoreria->registrar(
                        $usuarioId,
                        TipoHechoTesoreria::Ingreso,
                        $datos['fecha'],
                        $monto,
                        (int) $datos['cuenta_liquida_id'],
                        (int) $datos['categoria_id'],
                        null,
                        $datos['descripcion'] ?? null
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('ingreso', $usuarioId, $claveIdem);

            return back()->withErrors(ErrorDominio::aCampo($e, 'form'))->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('ingreso', $usuarioId, $claveIdem);
            throw $e;
        }

        $status = match (true) {
            $recurrente && $recibido => 'Ingreso recibido y proyección recurrente guardados.',
            $recurrente && ! $recibido => 'Ingreso recurrente guardado como proyección. No se registró como recibido.',
            default => 'Ingreso recibido registrado.',
        };

        return redirect()->route('app.ingresos.index')->with('status', $status);
    }

    public function corregir(Request $request, HechoTesoreria $hecho, ContabilizacionService $contabilizacion): RedirectResponse
    {
        $usuarioId = (int) Auth::id();
        if ((int) $hecho->usuario_id !== $usuarioId || $hecho->tipo !== TipoHechoTesoreria::Ingreso) {
            abort(404);
        }

        $asiento = Asiento::query()
            ->where('usuario_id', $usuarioId)
            ->where('origen_tipo', HechoTesoreria::class)
            ->where('origen_id', $hecho->id)
            ->where('es_reverso', false)
            ->firstOrFail();

        $motivo = trim((string) $request->input('motivo', 'Corrección de ingreso'));
        if ($motivo === '') {
            $motivo = 'Corrección de ingreso';
        }

        try {
            $contabilizacion->revertir($usuarioId, (int) $asiento->id, now()->toDateString(), $motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.ingresos.index')->with('status', 'Ingreso corregido (reverso contable). El historial se conserva.');
    }

    private function nombreCategoria(int $id): string
    {
        return Categoria::whereKey($id)->value('nombre') ?? 'Ingreso recurrente';
    }
}
