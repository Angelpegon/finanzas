<?php

namespace App\Http\Middleware;

use App\Support\PerfProbe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        PerfProbe::mark('E_guest_enter');
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                PerfProbe::mark('E_guest_exit');

                return redirect()->route('app.situacion');
            }
        }

        PerfProbe::mark('E_guest_exit');

        return $next($request);
    }
}
