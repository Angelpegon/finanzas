<?php

namespace App\Http\Middleware;

use App\Support\PerfProbe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * DIAGNÓSTICO TEMPORAL. Separa el costo de abrir sesión + GC del resto de la petición.
 * D_session_start → D_session_ready incluye startSession() y collectGarbage().
 */
class TimedStartSession extends StartSession
{
    public function handle($request, Closure $next): Response
    {
        PerfProbe::mark('D_session_start');

        return parent::handle($request, function ($request) use ($next) {
            PerfProbe::mark('D_session_ready');

            return $next($request);
        });
    }
}
