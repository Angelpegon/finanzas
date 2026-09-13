<?php

namespace App\Http\Middleware;

use App\Support\PerfProbe;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** DIAGNÓSTICO TEMPORAL: marca un instante sin envolver el resto de la petición. */
class PerfStamp
{
    public function handle(Request $request, Closure $next, string $name): Response
    {
        PerfProbe::mark($name);

        return $next($request);
    }
}
