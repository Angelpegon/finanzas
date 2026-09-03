<?php

namespace App\Http\Controllers;

use App\Services\SituacionFinancieraService;
use App\Services\AlertaService;
use Illuminate\Support\Facades\Auth;

class SituacionController extends Controller
{
    public function __construct(
        private readonly SituacionFinancieraService $situacion,
        private readonly AlertaService $alertas
    ) {}

    public function __invoke()
    {
        $datos = $this->situacion->responder(Auth::id());
        $datos['alertas'] = $this->alertas->evaluar(Auth::id(), $datos);

        return view('situacion', ['situacion' => $datos]);
    }
}
