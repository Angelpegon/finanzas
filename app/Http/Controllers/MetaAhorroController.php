<?php

namespace App\Http\Controllers;

use App\Enums\TipoHechoTesoreria;
use App\Http\Requests\ActualizarMetaRequest;
use App\Http\Requests\AporteMetaRequest;
use App\Http\Requests\MetaAhorroRequest;
use App\Http\Requests\RetiroMetaRequest;
use App\Models\Asiento;
use App\Models\HechoTesoreria;
use App\Models\MetaAhorro;
use App\Services\MetaAhorroService;
use App\Support\CuentasOperativas;
use App\Support\Dinero;
use App\Support\ErrorDominio;
use App\Support\Idempotencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MetaAhorroController extends Controller
{
    public function index(MetaAhorroService $metas): View
    {
        $usuarioId = (int) Auth::id();
        $lista = MetaAhorro::with('cuentaLiquida')
            ->orderByRaw("CASE prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END")
            ->orderBy('nombre')
            ->get();

        $movimientos = HechoTesoreria::query()
            ->whereIn('tipo', [TipoHechoTesoreria::AporteMeta, TipoHechoTesoreria::RetiroMeta])
            ->with(['cuentaLiquida', 'cuentaDestino', 'metaAhorro'])
            ->latest('fecha')
            ->latest('id')
            ->limit(20)
            ->get();

        $idsHechos = $movimientos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsRevertidos = $idsHechos === []
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

        $ultimoPorMeta = HechoTesoreria::query()
            ->whereIn('tipo', [TipoHechoTesoreria::AporteMeta, TipoHechoTesoreria::RetiroMeta])
            ->whereNotNull('meta_ahorro_id')
            ->orderByDesc('id')
            ->get(['id', 'meta_ahorro_id'])
            ->unique('meta_ahorro_id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return view('metas.index', [
            'metas' => $lista,
            'cuentasOperativas' => CuentasOperativas::queryActivas($usuarioId)->orderBy('nombre')->get(),
            'movimientos' => $movimientos,
            'idsRevertidos' => $idsRevertidos,
            'ultimoPorMeta' => $ultimoPorMeta,
            'idempotencyKeyMeta' => (string) Str::uuid(),
            'idempotencyKeyAporte' => (string) Str::uuid(),
            'idempotencyKeyRetiro' => (string) Str::uuid(),
            'idempotencyKeyEditar' => (string) Str::uuid(),
        ]);
    }

    public function store(MetaAhorroRequest $request, MetaAhorroService $service): RedirectResponse
    {
        $this->authorize('create', MetaAhorro::class);
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('meta_crear', $usuarioId, $clave);

        try {
            $service->crear(
                $usuarioId,
                $d['nombre'],
                Dinero::pesosACentavos($d['objetivo']),
                $d['fecha_objetivo'] ?? null,
                Dinero::pesosACentavos($d['aporte_mensual'] ?? 0),
                (int) $d['cuenta_liquida_id'],
                $d['prioridad']
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('meta_crear', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'objetivo'), 'meta')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('meta_crear', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.metas.index')->with('status', 'Meta creada. El avance solo crece con aportes al bolsillo.');
    }

    public function update(ActualizarMetaRequest $request, MetaAhorro $meta, MetaAhorroService $service): RedirectResponse
    {
        $this->authorize('update', $meta);
        $d = $request->validated();
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('meta_editar_'.$meta->id, $usuarioId, $clave);

        try {
            $service->actualizar(
                $usuarioId,
                (int) $meta->id,
                Dinero::pesosACentavos($d['objetivo']),
                $d['fecha_objetivo'] ?? null,
                array_key_exists('aporte_mensual', $d) ? Dinero::pesosACentavos($d['aporte_mensual'] ?? 0) : null,
                $d['prioridad'] ?? null,
                $d['nombre'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('meta_editar_'.$meta->id, $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'objetivo'), 'editar_meta')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('meta_editar_'.$meta->id, $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.metas.index')->with('status', 'Meta actualizada (objetivo / fecha). El bolsillo no cambia.');
    }

    public function aportar(AporteMetaRequest $request, MetaAhorroService $service): RedirectResponse
    {
        $d = $request->validated();
        $meta = MetaAhorro::findOrFail($d['meta_ahorro_id']);
        $this->authorize('update', $meta);
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('meta_aporte', $usuarioId, $clave);

        try {
            $service->aportar(
                $usuarioId,
                (int) $d['meta_ahorro_id'],
                Dinero::pesosACentavos($d['monto']),
                (int) $d['cuenta_liquida_id'],
                $d['fecha'],
                $d['descripcion'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('meta_aporte', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'aporte')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('meta_aporte', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.metas.index', ['aporte' => 1])->with('status', 'Aporte contabilizado en el libro.');
    }

    public function retirar(RetiroMetaRequest $request, MetaAhorroService $service): RedirectResponse
    {
        $d = $request->validated();
        $meta = MetaAhorro::findOrFail($d['meta_ahorro_id']);
        $this->authorize('update', $meta);
        $usuarioId = (int) Auth::id();
        $clave = $d['idempotency_key'];
        Idempotencia::consumir('meta_retiro', $usuarioId, $clave);

        try {
            $service->retirar(
                $usuarioId,
                (int) $d['meta_ahorro_id'],
                Dinero::pesosACentavos($d['monto']),
                (int) $d['cuenta_destino_id'],
                $d['fecha'],
                $d['descripcion'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            Idempotencia::liberar('meta_retiro', $usuarioId, $clave);

            return back()->withErrors(ErrorDominio::aCampo($e, 'monto'), 'retiro')->withInput();
        } catch (\Throwable $e) {
            Idempotencia::liberar('meta_retiro', $usuarioId, $clave);
            throw $e;
        }

        return redirect()->route('app.metas.index')->with('status', 'Retiro contabilizado. El avance de la meta bajó; el bolsillo sigue reservado.');
    }

    public function corregir(Request $request, HechoTesoreria $hecho, MetaAhorroService $service): RedirectResponse
    {
        $usuarioId = (int) Auth::id();
        if ((int) $hecho->usuario_id !== $usuarioId
            || ! in_array($hecho->tipo, [TipoHechoTesoreria::AporteMeta, TipoHechoTesoreria::RetiroMeta], true)) {
            abort(404);
        }

        $motivo = trim((string) $request->input('motivo', 'Corrección de movimiento de meta'));
        if ($motivo === '') {
            $motivo = 'Corrección de movimiento de meta';
        }

        try {
            $service->corregirMovimiento($usuarioId, (int) $hecho->id, now()->toDateString(), $motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(ErrorDominio::aCampo($e, 'form'));
        }

        return redirect()->route('app.metas.index')->with('status', 'Movimiento de meta corregido (reverso contable).');
    }
}
