<?php

namespace App\Providers;

use App\Services\AlertaService;
use App\Services\SituacionFinancieraService;
use App\Support\UrlPrefix;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $root = rtrim((string) config('app.url'), '/');
        $forceHttps = $this->app->environment('production') || (bool) config('app.force_https');

        $aplicarUrl = function () use ($root, $forceHttps): void {
            URL::forceRootUrl($root);
            if ($forceHttps) {
                URL::forceScheme('https');
            }
        };

        // No resolver el facade URL sin request (rompe diag/artisan y cron).
        if ($this->app->bound('request') && $this->app->make('request')) {
            $aplicarUrl();
        } else {
            $this->app->rebinding('request', function ($app, $request) use ($aplicarUrl): void {
                if ($request) {
                    $aplicarUrl();
                }
            });
        }

        config(['session.path' => UrlPrefix::sessionPath()]);

        Blade::directive('cop', function (string $expression) {
            return "<?php echo \\App\\Support\\Dinero::formatear($expression); ?>";
        });

        View::composer('layouts.app', function ($view) {
            if (! Auth::check()) {
                return;
            }

            $id = (int) Auth::id();

            // Sin static de proceso: en PHP-FPM mentía disponible/alertas entre requests.
            // resumenShell usa Cache Laravel + olvidarResumenShell al postear.
            if (! $view->offsetExists('situacion')) {
                $view->with('situacion', app(SituacionFinancieraService::class)->resumenShell($id));
            }

            if (! $view->offsetExists('alertas')) {
                $situacion = $view->offsetGet('situacion');
                if (isset($situacion['alertas']) && is_array($situacion['alertas'])) {
                    $view->with('alertas', $situacion['alertas']);
                } else {
                    $view->with('alertas', app(AlertaService::class)->evaluar($id, $situacion));
                }
            }

            // Chip móvil: disponible de “hoy”. En Situación el payload puede ser otro mes.
            if (! $view->offsetExists('disponibleShell')) {
                $situacion = $view->offsetGet('situacion');
                $esDashboardDenso = array_key_exists('calendario_grilla', $situacion)
                    || array_key_exists('cuentas', $situacion);
                $view->with(
                    'disponibleShell',
                    $esDashboardDenso
                        ? (int) (app(SituacionFinancieraService::class)->resumenShell($id)['dinero_disponible_real_centavos'] ?? 0)
                        : (int) ($situacion['dinero_disponible_real_centavos'] ?? 0)
                );
            }
        });
    }
}
