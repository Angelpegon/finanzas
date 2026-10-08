<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Plesk (nginx → Apache/PHP-FPM) termina TLS delante de la app.
     * Por defecto '*'. En producción conviene TRUSTED_PROXIES con IPs del proxy
     * (p. ej. 127.0.0.1,::1 o la red interna del nodo). Se lee vía config()
     * porque env() devuelve null con config:cache.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function handle(Request $request, \Closure $next)
    {
        $this->proxies = self::proxiesDesdeConfig(config('app.trusted_proxies'));

        return parent::handle($request, $next);
    }

    /**
     * @return array<int, string>|string
     */
    public static function proxiesDesdeConfig(mixed $raw): array|string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
