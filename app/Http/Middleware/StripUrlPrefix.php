<?php

namespace App\Http\Middleware;

use App\Support\UrlPrefix;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\Response;

/**
 * En Plesk con docroot en httpdocs y app en /finanzas, Apache suele reportar:
 *   SCRIPT_NAME=/finanzas/public/index.php
 *   REQUEST_URI=/finanzas/login
 * Symfony toma base=/finanzas/public → pathInfo no matchea rutas (/login).
 *
 * Forzamos SCRIPT_NAME={prefix}/index.php para que la base sea /finanzas
 * y pathInfo quede /login (fullUrl conserva el prefijo).
 *
 * /finanzas y /finanzas/ a veces no resuelven a pathInfo "/" (405 Method Not
 * Allowed). Se redirige a situacion/login antes del router.
 */
class StripUrlPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = UrlPrefix::basePath();
        if ($prefix === '') {
            return $next($request);
        }

        $uri = (string) $request->server->get('REQUEST_URI', '');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '');

        if ($path !== $prefix && $path !== $prefix.'/' && ! str_starts_with($path, $prefix.'/')) {
            return $next($request);
        }

        // Raíz de la app bajo subpath: no confiar en el router/pathInfo de Apache.
        // (Auth aún no tiene sesión aquí; login redirige a situacion si ya hay cookie.)
        if ($path === $prefix || $path === $prefix.'/') {
            return redirect()->route('login');
        }

        $server = $request->server->all();
        $desiredScript = $prefix.'/index.php';
        if (($server['SCRIPT_NAME'] ?? '') === $desiredScript) {
            return $next($request);
        }

        $server['SCRIPT_NAME'] = $desiredScript;
        $server['PHP_SELF'] = $desiredScript;
        $server['SCRIPT_FILENAME'] = public_path('index.php');

        $request = $request->duplicate(
            null,
            null,
            null,
            null,
            null,
            $server
        );

        app()->instance('request', $request);
        Facade::clearResolvedInstance('request');
        Facade::clearResolvedInstance('url');

        return $next($request);
    }
}
