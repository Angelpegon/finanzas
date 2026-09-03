<?php

namespace App\Http\Controllers;

use App\Services\ProyeccionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProyeccionController extends Controller
{
    public function __invoke(Request $request, ProyeccionService $proyeccion): View
    {
        $meses = max(1, min(12, $request->integer('meses') ?: 6));

        return view('proyecciones.index', [
            'meses' => $proyeccion->meses($request->user()->id, now(), $meses),
            'cantidadMeses' => $meses,
        ]);
    }
}
