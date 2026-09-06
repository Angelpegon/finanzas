<?php

namespace App\Support;

/**
 * Prefijo de URL cuando la app vive en subcarpeta (p. ej. https://ingeer.co/finanzas).
 * Se deriva del path de APP_URL; vacío en local (http://localhost).
 */
final class UrlPrefix
{
    /** Segmento sin barras: "finanzas" o "". */
    public static function segment(): string
    {
        $path = parse_url((string) config('app.url'), PHP_URL_PATH);

        return trim((string) $path, '/');
    }

    /** Path absoluto sin slash final: "/finanzas" o "". */
    public static function basePath(): string
    {
        $segment = self::segment();

        return $segment === '' ? '' : '/'.$segment;
    }

    /** Path de cookie de sesión: "/finanzas" o "/". */
    public static function sessionPath(): string
    {
        $base = self::basePath();

        return $base === '' ? '/' : $base;
    }

    /** Une base + path absoluto de la app ("/login" → "/finanzas/login"). */
    public static function urlPath(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/') {
            $base = self::basePath();

            return $base === '' ? '/' : $base.'/';
        }

        return self::basePath().$path;
    }
}
