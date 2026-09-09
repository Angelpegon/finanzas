<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PwaAssetController;
use App\Http\Controllers\SituacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CuentaLiquidaController;
use App\Http\Controllers\IngresoController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\DeudaController;
use App\Http\Controllers\TarjetaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\MetaAhorroController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ProyeccionController;

Route::get('/manifest.json', [PwaAssetController::class, 'manifest']);
Route::get('/sw.js', [PwaAssetController::class, 'serviceWorker']);
Route::get('/offline.html', [PwaAssetController::class, 'offline']);

Route::get('/', fn () => redirect()->route(Auth::check() ? 'app.situacion' : 'login'));
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::get('/registro', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
});
Route::middleware('auth')->group(function (): void {
    Route::get('/situacion', SituacionController::class)->name('app.situacion');
    Route::get('/cuentas', [CuentaLiquidaController::class, 'index'])->name('app.cuentas.index');
    Route::get('/cuentas/crear', [CuentaLiquidaController::class, 'create'])->name('app.cuentas.create');
    Route::post('/cuentas', [CuentaLiquidaController::class, 'store'])->name('app.cuentas.store');
    Route::delete('/cuentas/{cuenta}', [CuentaLiquidaController::class, 'destroy'])->name('app.cuentas.destroy');
    Route::post('/cuentas/{cuenta}/restaurar', [CuentaLiquidaController::class, 'restore'])->name('app.cuentas.restore');
    Route::post('/cuentas/{cuenta}/cancelar', [CuentaLiquidaController::class, 'cancelar'])->name('app.cuentas.cancelar');
    Route::get('/ingresos/crear', [IngresoController::class, 'create'])->name('app.ingresos.create');
    Route::post('/ingresos', [IngresoController::class, 'store'])->name('app.ingresos.store');
    Route::get('/gastos/crear', [GastoController::class, 'create'])->name('app.gastos.create');
    Route::post('/gastos', [GastoController::class, 'store'])->name('app.gastos.store');
    Route::get('/deudas', [DeudaController::class, 'index'])->name('app.deudas.index');
    Route::get('/deudas/crear', [DeudaController::class, 'create'])->name('app.deudas.create');
    Route::post('/deudas', [DeudaController::class, 'store'])->name('app.deudas.store');
    Route::post('/deudas/pagos', [DeudaController::class, 'pagar'])->name('app.deudas.pagos.store');
    Route::get('/tarjetas', [TarjetaController::class, 'index'])->name('app.tarjetas.index');
    Route::get('/tarjetas/crear', [TarjetaController::class, 'create'])->name('app.tarjetas.create');
    Route::post('/tarjetas', [TarjetaController::class, 'store'])->name('app.tarjetas.store');
    Route::post('/tarjetas/compras', [TarjetaController::class, 'compra'])->name('app.tarjetas.compras.store');
    Route::post('/tarjetas/pagos', [TarjetaController::class, 'pagar'])->name('app.tarjetas.pagos.store');
    Route::get('/pagos', [PagoController::class, 'index'])->name('app.pagos.index');
    Route::post('/pagos', [PagoController::class, 'store'])->name('app.pagos.store');
    Route::post('/transferencias', [PagoController::class, 'transferir'])->name('app.transferencias.store');
    Route::get('/presupuestos', [PresupuestoController::class, 'index'])->name('app.presupuestos.index');
    Route::post('/presupuestos', [PresupuestoController::class, 'store'])->name('app.presupuestos.store');
    Route::get('/metas', [MetaAhorroController::class, 'index'])->name('app.metas.index');
    Route::post('/metas', [MetaAhorroController::class, 'store'])->name('app.metas.store');
    Route::post('/metas/aportes', [MetaAhorroController::class, 'aportar'])->name('app.metas.aportes.store');
    Route::get('/calendario', CalendarioController::class)->name('app.calendario');
    Route::get('/proyecciones', ProyeccionController::class)->name('app.proyecciones');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
});
