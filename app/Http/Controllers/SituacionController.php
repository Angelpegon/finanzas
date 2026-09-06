<?php

namespace App\Http\Controllers;

use App\Services\AlertaService;
use App\Services\SituacionFinancieraService;
use Illuminate\Support\Facades\Auth;

class SituacionController extends Controller
{
    public function __construct(
        private readonly SituacionFinancieraService $situacion,
        private readonly AlertaService $alertas
    ) {}

    public function __invoke()
    {
        $datos = $this->situacion->responder(Auth::id(), null, true);
        $alertas = $this->alertas->evaluar(Auth::id(), $datos);
        $datos['alertas'] = $alertas;

        return view('situacion', [
            'situacion' => $datos,
            'alertas' => $alertas,
        ]);
    }
}
