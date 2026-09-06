<?php
/**
 * Muestra el final de storage/logs/laravel.log (temporal).
 * Subir a: httpdocs/finanzas/public/diag-log.php
 * Abrir:   https://ingeer.co/finanzas/diag-log.php?token=TU_TOKEN
 * BORRAR después.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$tokenEsperado = 'finanzas-diag-2026';
if (($_GET['token'] ?? '') !== $tokenEsperado) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$log = dirname(__DIR__).'/storage/logs/laravel.log';
if (! is_file($log)) {
    echo "No existe laravel.log en $log\n";
    echo "Revisa permisos de storage/logs/\n";
    exit;
}

$size = filesize($log);
echo "log=$log bytes=$size\n\n";
$fp = fopen($log, 'rb');
if ($fp === false) {
    echo "No se pudo leer el log (permisos).\n";
    exit;
}
$read = 12000;
if ($size > $read) {
    fseek($fp, -$read, SEEK_END);
}
echo stream_get_contents($fp);
fclose($fp);
echo "\n\nBORRA este archivo.\n";
