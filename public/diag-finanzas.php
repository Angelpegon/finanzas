<?php
/**
 * Diagnóstico temporal para Plesk (subcarpeta /finanzas).
 * Súbelo a: httpdocs/finanzas/public/diag-finanzas.php
 * Ábrelo:   https://ingeer.co/finanzas/diag-finanzas.php
 * BORRARLO en cuanto termines.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$root = dirname(__DIR__);
$lines = [];

$lines[] = 'ROOT='.$root;
$lines[] = 'REQUEST_URI='.($_SERVER['REQUEST_URI'] ?? '');
$lines[] = 'SCRIPT_NAME='.($_SERVER['SCRIPT_NAME'] ?? '');
$lines[] = 'PHP_SELF='.($_SERVER['PHP_SELF'] ?? '');

$envPath = $root.'/.env';
$lines[] = 'env_exists='.(is_file($envPath) ? 'yes' : 'NO');

$appUrl = '';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'APP_URL=')) {
            $appUrl = trim(substr($line, strlen('APP_URL=')), " \t\"'");
        }
        if (str_starts_with($line, 'APP_ENV=')) {
            $lines[] = 'APP_ENV='.trim(substr($line, strlen('APP_ENV=')), " \t\"'");
        }
        if (str_starts_with($line, 'APP_DEBUG=')) {
            $lines[] = 'APP_DEBUG='.trim(substr($line, strlen('APP_DEBUG=')), " \t\"'");
        }
        if (str_starts_with($line, 'APP_KEY=')) {
            $key = trim(substr($line, strlen('APP_KEY=')), " \t\"'");
            $lines[] = 'APP_KEY='.($key === '' ? 'VACÍA' : 'ok('.strlen($key).' chars)');
        }
    }
}
$lines[] = 'APP_URL='.($appUrl === '' ? 'NO DEFINIDA' : $appUrl);

$path = (string) (parse_url($appUrl, PHP_URL_PATH) ?: '');
$lines[] = 'APP_URL_path='.($path === '' ? '(vacío → StripUrlPrefix NO actúa)' : $path);

$strip = $root.'/app/Http/Middleware/StripUrlPrefix.php';
$kernel = $root.'/app/Http/Kernel.php';
$lines[] = 'StripUrlPrefix.php='.(is_file($strip) ? 'yes' : 'NO (falta subir código nuevo)');
$lines[] = 'Kernel_tiene_StripUrlPrefix='.(is_file($kernel) && str_contains((string) file_get_contents($kernel), 'StripUrlPrefix') ? 'yes' : 'NO');

$cache = glob($root.'/bootstrap/cache/*.php') ?: [];
$lines[] = 'bootstrap_cache_php='.(count($cache) === 0 ? 'ninguno (ok)' : implode(', ', array_map('basename', $cache)));

$lines[] = '';
$lines[] = 'Esperado producción:';
$lines[] = '  APP_URL=https://ingeer.co/finanzas';
$lines[] = '  StripUrlPrefix.php=yes';
$lines[] = '  Kernel_tiene_StripUrlPrefix=yes';
$lines[] = '  bootstrap_cache_php=ninguno (ok)  O regenerado EN el servidor';
$lines[] = '';
$lines[] = 'BORRA este archivo cuando termines.';

echo implode("\n", $lines)."\n";
