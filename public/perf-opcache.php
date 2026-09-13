<?php
/**
 * DIAGNÓSTICO TEMPORAL. NO arranca Laravel.
 *
 * Abrir ESTE archivo PRIMERO después de ≥30 min de inactividad:
 *   https://ingeer.co/finanzas/perf-opcache.php
 *
 * Después abre /login y compara Server-Timing.
 * BORRAR cuando termine el diagnóstico.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$t0 = microtime(true);

$lines = [];
$lines[] = 'php='.PHP_VERSION;
$lines[] = 'sapi='.PHP_SAPI;
$lines[] = 'os='.PHP_OS;
$lines[] = 'script_ms='.number_format((microtime(true) - $t0) * 1000, 2, '.', '');
$lines[] = 'opcache.enable='.var_export((bool) ini_get('opcache.enable'), true);
$lines[] = 'opcache.enable_cli='.var_export((bool) ini_get('opcache.enable_cli'), true);
$lines[] = 'opcache.memory_consumption='.(string) ini_get('opcache.memory_consumption');
$lines[] = 'opcache.max_accelerated_files='.(string) ini_get('opcache.max_accelerated_files');
$lines[] = 'opcache.validate_timestamps='.(string) ini_get('opcache.validate_timestamps');
$lines[] = 'opcache.revalidate_freq='.(string) ini_get('opcache.revalidate_freq');
$lines[] = 'opcache.interned_strings_buffer='.(string) ini_get('opcache.interned_strings_buffer');
$lines[] = 'realpath_cache_size='.(string) ini_get('realpath_cache_size');
$lines[] = 'realpath_cache_ttl='.(string) ini_get('realpath_cache_ttl');
$lines[] = 'max_execution_time='.(string) ini_get('max_execution_time');

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    if ($status === false) {
        $lines[] = 'opcache_status=false (deshabilitado para este SAPI o sin permiso)';
    } else {
        $mem = $status['memory_usage'] ?? [];
        $stats = $status['opcache_statistics'] ?? [];
        $lines[] = 'cache_full='.var_export($status['cache_full'] ?? null, true);
        $lines[] = 'restart_pending='.var_export($status['restart_pending'] ?? null, true);
        $lines[] = 'num_cached_scripts='.($stats['num_cached_scripts'] ?? 'n/a');
        $lines[] = 'hits='.($stats['hits'] ?? 'n/a');
        $lines[] = 'misses='.($stats['misses'] ?? 'n/a');
        $lines[] = 'oom_restarts='.($stats['oom_restarts'] ?? 'n/a');
        $lines[] = 'hash_restarts='.($stats['hash_restarts'] ?? 'n/a');
        $lines[] = 'last_restart_time='.(isset($stats['last_restart_time']) && $stats['last_restart_time'] ? date('c', (int) $stats['last_restart_time']) : 'never');
        $lines[] = 'used_memory_mb='.(isset($mem['used_memory']) ? round($mem['used_memory'] / 1048576, 2) : 'n/a');
        $lines[] = 'free_memory_mb='.(isset($mem['free_memory']) ? round($mem['free_memory'] / 1048576, 2) : 'n/a');
        $lines[] = 'wasted_memory_mb='.(isset($mem['wasted_memory']) ? round($mem['wasted_memory'] / 1048576, 2) : 'n/a');
    }
} else {
    $lines[] = 'opcache_get_status=NO EXISTE';
}

$lines[] = '';
$lines[] = 'Interpretación:';
$lines[] = '- Este script debe ser tan rápido como test.php (~1-20ms).';
$lines[] = '- Si num_cached_scripts es bajo (<100) tras 30 min idle, OPcache se enfrió/reinició.';
$lines[] = '- Luego abre /login UNA vez y mira el header Server-Timing y storage/logs/performance.log';
$lines[] = '';
$lines[] = 'script_ms_final='.number_format((microtime(true) - $t0) * 1000, 2, '.', '');
$lines[] = 'BORRA este archivo cuando termines.';

echo implode("\n", $lines)."\n";
