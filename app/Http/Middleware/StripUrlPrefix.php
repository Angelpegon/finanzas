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
        $query = parse_url($uri, PHP_URL_QUERY);

        if ($path !== $prefix && $path !== $prefix.'/' && ! str_starts_with($path, $prefix.'/')) {
            return $next($request);
        }

        $server = $request->server->all();
        $changed = false;

        // /finanzas (sin slash) no alinea con base /finanzas/index.php en Symfony.
        if ($path === $prefix) {
            $server['REQUEST_URI'] = $prefix.'/'.($query !== null && $query !== '' ? '?'.$query : '');
            $changed = true;
        }

        // Aunque SCRIPT_NAME ya tenga /finanzas/..., si incluye /public el pathInfo falla.
        $desiredScript = $prefix.'/index.php';
        if (($server['SCRIPT_NAME'] ?? '') !== $desiredScript) {
            $server['SCRIPT_NAME'] = $desiredScript;
            $server['PHP_SELF'] = $desiredScript;
            $server['SCRIPT_FILENAME'] = public_path('index.php');
            $changed = true;
        }

        if (! $changed) {
            return $next($request);
        }

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
