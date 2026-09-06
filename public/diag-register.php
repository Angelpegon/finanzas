<?php
/**
 * Reproduce el registro y muestra la excepción real.
 * Subir a: httpdocs/finanzas/public/diag-register.php
 * Abrir:   https://ingeer.co/finanzas/diag-register.php?token=finanzas-diag-2026
 * BORRAR inmediatamente después.
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

if (($_GET['token'] ?? '') !== 'finanzas-diag-2026') {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';

    // Igual que index.php: el request debe existir ANTES del boot de providers.
    $request = Request::create('https://ingeer.co/finanzas/registro', 'POST');
    $app->instance('request', $request);

    $app->make(Kernel::class)->bootstrap();

    echo "APP_URL=".config('app.url')."\n";
    echo "DB=".config('database.connections.mysql.database')." host=".config('database.connections.mysql.host')."\n";

    DB::connection()->getPdo();
    echo "DB_OK\n";

    $email = 'diag_'.time().'@example.com';
    echo "intentando User::create email=$email\n";

    DB::beginTransaction();
    try {
        $user = User::create([
            'nombre' => 'Diag Test',
            'email' => $email,
            'password' => 'password123',
        ]);
        echo "USER_OK id={$user->id}\n";
        echo "cuentas_contables=".DB::table('cuentas_contables')->where('usuario_id', $user->id)->count()."\n";
        echo "categorias=".DB::table('categorias')->where('usuario_id', $user->id)->count()."\n";
        echo "cuentas_liquidas=".DB::table('cuentas_liquidas')->where('usuario_id', $user->id)->count()."\n";
    } finally {
        DB::rollBack();
        echo "rollback hecho (no queda basura)\n";
    }

    echo "SUCCESS\n";
} catch (Throwable $e) {
    echo "FAIL\n";
    echo get_class($e)."\n";
    echo $e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
    if ($e->getPrevious()) {
        echo "PREVIOUS: ".$e->getPrevious()->getMessage()."\n";
    }
    echo "\n--- trace (corto) ---\n";
    echo implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 20))."\n";
}

echo "\nBORRA este archivo.\n";
