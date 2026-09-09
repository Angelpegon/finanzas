<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Plesk (nginx → Apache/PHP-FPM) termina TLS delante de la app.
     * Por defecto '*'. En producción conviene TRUSTED_PROXIES con IPs del proxy
     * (p. ej. 127.0.0.1,::1 o la red interna del nodo).
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

    public function __construct()
    {
        $raw = trim((string) env('TRUSTED_PROXIES', '*'));
        if ($raw === '' || $raw === '*') {
            $this->proxies = '*';

            return;
        }

        $this->proxies = array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
