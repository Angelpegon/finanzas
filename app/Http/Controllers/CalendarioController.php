<?php

namespace App\Http\Controllers;

use App\Services\CalendarioFinancieroService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarioController extends Controller
{
    public function __invoke(Request $request, CalendarioFinancieroService $calendario): View
    {
        $anio = (int) ($request->integer('anio') ?: now()->year);
        $mes = max(1, min(12, (int) ($request->integer('mes') ?: now()->month)));
        $fecha = now()->setDate($anio, $mes, 1);

        return view('calendario.index', [
            'fecha' => $fecha,
            'eventos' => $calendario->mensual($request->user()->id, $fecha->year, $fecha->month),
        ]);
    }
}
