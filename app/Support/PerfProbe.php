<?php

namespace App\Support;

/**
 * DIAGNÓSTICO TEMPORAL de rendimiento. No es lógica de negocio.
 *
 * Cómo apagar sin redeploy de código: crear el archivo
 *   storage/framework/perf.off
 *
 * Cómo quitar del todo:
 *   1. PerfProbe::ACTIVE = false  (o borrar este archivo)
 *   2. Revertir public/index.php, Kernel, AuthController, RedirectIfAuthenticated
 *   3. Borrar middleware RecordPerformance, PerfStamp, TimedStartSession
 *   4. Borrar public/perf-opcache.php y el script extra en boot-splash
 *   5. Quitar el canal 'performance' de config/logging.php
 */
final class PerfProbe
{
    /** @var bool Ponlo en false para desactivar la emisión (marcas siguen siendo baratas). */
    public const ACTIVE = true;

    /** @var array<string, float> */
    private static array $marks = [];

    /** @var array<string, float> duraciones explícitas en ms */
    private static array $durations = [];

    public static function reset(): void
    {
        self::$marks = [];
        self::$durations = [];
    }

    public static function hydrateFromGlobals(): void
    {
        $fromGlobals = $GLOBALS['__perf_marks'] ?? [];
        if (is_array($fromGlobals)) {
            foreach ($fromGlobals as $name => $ts) {
                if (is_numeric($ts)) {
                    self::$marks[$name] = (float) $ts;
                }
            }
        }

        if (! isset(self::$marks['A_request_start']) && defined('LARAVEL_START')) {
            self::$marks['A_request_start'] = (float) LARAVEL_START;
        }
    }

    public static function mark(string $name): void
    {
        self::$marks[$name] = microtime(true);
    }

    public static function markIfAbsent(string $name): void
    {
        if (! isset(self::$marks[$name])) {
            self::$marks[$name] = microtime(true);
        }
    }

    public static function durationMs(string $name, float $startedAt): float
    {
        $ms = (microtime(true) - $startedAt) * 1000;
        self::$durations[$name] = $ms;

        return $ms;
    }

    public static function enabled(): bool
    {
        if (! self::ACTIVE) {
            return false;
        }

        $off = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'perf.off';
        if (is_file($off)) {
            return false;
        }

        try {
            if (function_exists('app')) {
                $app = app();
                if ($app->bound('env') && $app->environment('testing')) {
                    return false;
                }
            }
        } catch (\Throwable) {
            // Sin contenedor todavía: seguir activo.
        }

        return true;
    }

    /** @return array<string, float> */
    public static function marks(): array
    {
        return self::$marks;
    }

    /** @return array<string, float> milisegundos entre marcas conocidas */
    public static function stages(): array
    {
        $ms = static function (string $from, string $to): ?float {
            if (! isset(self::$marks[$from], self::$marks[$to])) {
                return null;
            }

            return round((self::$marks[$to] - self::$marks[$from]) * 1000, 2);
        };

        $start = self::$marks['A_request_start'] ?? null;
        $fromStart = static function (string $to) use ($start): ?float {
            if ($start === null || ! isset(self::$marks[$to])) {
                return null;
            }

            return round((self::$marks[$to] - $start) * 1000, 2);
        };

        $stages = [
            'A_autoload' => $ms('A_request_start', 'A_autoload_done'),
            'A_app_created' => $ms('A_autoload_done', 'A_app_created'),
            'A_kernel_resolved' => $ms('A_app_created', 'A_kernel_resolved'),
            'A_request_captured' => $ms('A_kernel_resolved', 'A_request_captured'),
            'A_providers_boot' => $ms('A_providers_boot_start', 'A_providers_boot_end'),
            'B_global_until_router' => $ms('B_global_enter', 'B_global_done'),
            'C_web_enter_from_start' => $fromStart('C_web_enter'),
            'D_session_start_and_gc' => $ms('D_session_start', 'D_session_ready'),
            'E_guest' => $ms('E_guest_enter', 'E_guest_exit'),
            'F_controller' => $ms('F_controller_enter', 'F_controller_exit'),
            'G_view_render_and_unwind' => $ms('F_controller_exit', 'kernel_exit') ?? $ms('G_view_start', 'kernel_exit'),
            'total_until_response' => $ms('A_request_start', 'kernel_exit'),
        ];

        foreach (self::$durations as $name => $value) {
            $stages[$name] = round($value, 2);
        }

        return array_filter($stages, static fn ($v) => $v !== null);
    }

    public static function runtimeContext(): array
    {
        $root = dirname(__DIR__, 2);
        $opcache = [
            'enabled' => function_exists('opcache_get_status') && (bool) ini_get('opcache.enable'),
        ];

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status)) {
                $mem = $status['memory_usage'] ?? [];
                $stats = $status['opcache_statistics'] ?? [];
                $opcache += [
                    'cache_full' => $status['cache_full'] ?? null,
                    'restart_pending' => $status['restart_pending'] ?? null,
                    'num_cached_scripts' => $stats['num_cached_scripts'] ?? null,
                    'hits' => $stats['hits'] ?? null,
                    'misses' => $stats['misses'] ?? null,
                    'oom_restarts' => $stats['oom_restarts'] ?? null,
                    'hash_restarts' => $stats['hash_restarts'] ?? null,
                    'last_restart' => isset($stats['last_restart_time']) ? date('c', (int) $stats['last_restart_time']) : null,
                    'used_memory_mb' => isset($mem['used_memory']) ? round($mem['used_memory'] / 1048576, 2) : null,
                    'free_memory_mb' => isset($mem['free_memory']) ? round($mem['free_memory'] / 1048576, 2) : null,
                ];
            }
        }

        $sessionDir = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'sessions';
        $sessionFiles = null;
        if (is_dir($sessionDir)) {
            $sessionFiles = 0;
            foreach (new \DirectoryIterator($sessionDir) as $file) {
                if ($file->isFile()) {
                    $sessionFiles++;
                    if ($sessionFiles >= 500) {
                        $sessionFiles = '500+';
                        break;
                    }
                }
            }
        }

        return [
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'config_cached' => is_file($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php'),
            'routes_cached' => is_file($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'routes-v7.php')
                || is_file($root.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'routes.php'),
            'views_cached' => is_dir($root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views')
                && count(glob($root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'*') ?: []) > 1,
            'session_files' => $sessionFiles,
            'opcache' => $opcache,
        ];
    }

    public static function serverTimingHeader(): string
    {
        $parts = [];
        foreach (self::stages() as $name => $ms) {
            $token = preg_replace('/[^A-Za-z0-9_-]/', '', $name) ?: 'stage';
            $parts[] = $token.';dur='.number_format((float) $ms, 2, '.', '');
        }

        return implode(', ', $parts);
    }

    public static function summaryLine(string $method, string $path): string
    {
        $stages = self::stages();
        $bits = [];
        foreach ($stages as $name => $ms) {
            $bits[] = $name.': '.number_format((float) $ms, 2, '.', '').' ms';
        }

        return '[PERFORMANCE] '.$method.' '.$path.' | '.implode(' | ', $bits);
    }
}
