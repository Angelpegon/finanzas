<?php

namespace App\Http\Controllers;

use App\Services\AlertaService;
use App\Services\SituacionFinancieraService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SituacionController extends Controller
{
    public function __construct(
        private readonly SituacionFinancieraService $situacion,
        private readonly AlertaService $alertas
    ) {}

    public function __invoke(Request $request): View
    {
        $anio = (int) ($request->integer('anio') ?: now()->year);
        $mes = max(1, min(12, (int) ($request->integer('mes') ?: now()->month)));
        $esMesActual = $anio === (int) now()->year && $mes === (int) now()->month;
        $fecha = $esMesActual
            ? now()
            : now()->copy()->setDate($anio, $mes, 1)->startOfMonth();

        $datos = $this->situacion->responder(Auth::id(), $fecha, true);
        $alertas = $this->alertas->evaluar(Auth::id(), $datos, $fecha);
        $datos['alertas'] = $alertas;

        $mesRef = Carbon::create($anio, $mes, 1)->startOfMonth();

        return view('situacion', [
            'situacion' => $datos,
            'alertas' => $alertas,
            'mesRef' => $mesRef,
            'mesAnterior' => $mesRef->copy()->subMonth(),
            'mesSiguiente' => $mesRef->copy()->addMonth(),
            'esMesActual' => $esMesActual,
        ]);
    }
}
