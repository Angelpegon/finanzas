<?php

namespace App\Http\Middleware;

use App\Support\PerfProbe;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * DIAGNÓSTICO TEMPORAL. Envuelve toda la petición HTTP y emite Server-Timing + log.
 */
class RecordPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        PerfProbe::reset();
        PerfProbe::hydrateFromGlobals();
        PerfProbe::mark('B_global_enter');

        if (PerfProbe::enabled()) {
            View::share('perfTrace', true);
            View::composer('*', static function (): void {
                PerfProbe::markIfAbsent('G_view_start');
            });
        }

        $response = $next($request);
        PerfProbe::mark('kernel_exit');

        if (! PerfProbe::enabled()) {
            return $response;
        }

        $timing = PerfProbe::serverTimingHeader();
        if ($timing !== '') {
            $response->headers->set('Server-Timing', $timing);
            $response->headers->set('X-Perf-Total-Ms', (string) (PerfProbe::stages()['total_until_response'] ?? ''));
        }

        $this->appendHtmlComment($response);
        $this->writeLog($request);

        return $response;
    }

    private function appendHtmlComment(Response $response): void
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return;
        }

        $stages = PerfProbe::stages();
        $total = $stages['total_until_response'] ?? null;
        $hint = ($total !== null && $total < 400)
            ? 'PHP termino en <400ms: si el navegador espera 3-5s, el retraso es cliente (splash/assets/SW), no el kernel.'
            : 'Si A_providers_boot o A_autoload dominan, es arranque frio (OPcache/disco), no el controlador.';

        $response->setContent($content."\n<!--PERF ".e(PerfProbe::summaryLine('', '')).' | '.$hint.' -->');
    }

    private function writeLog(Request $request): void
    {
        $line = PerfProbe::summaryLine($request->getMethod(), $request->getPathInfo());
        $context = json_encode(PerfProbe::runtimeContext(), JSON_UNESCAPED_SLASHES);
        $payload = $line."\n".'  context: '.$context."\n".'  marks: '.json_encode(PerfProbe::marks(), JSON_UNESCAPED_SLASHES)."\n";

        $path = storage_path('logs/performance.log');
        @file_put_contents($path, '['.date('c')."] ".$payload, FILE_APPEND | LOCK_EX);
    }
}
