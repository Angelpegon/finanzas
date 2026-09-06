<?php

namespace App\Providers;

use App\Services\AlertaService;
use App\Services\SituacionFinancieraService;
use App\Support\UrlPrefix;
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

            static $cache = [];
            static $alertasCache = [];
            $id = Auth::id();

            if (! $view->offsetExists('situacion')) {
                $cache[$id] ??= app(SituacionFinancieraService::class)->responder($id);
                $view->with('situacion', $cache[$id]);
            }

            if (! $view->offsetExists('alertas')) {
                $situacion = $view->offsetGet('situacion');
                if (isset($situacion['alertas']) && is_array($situacion['alertas'])) {
                    $view->with('alertas', $situacion['alertas']);
                } else {
                    $alertasCache[$id] ??= app(AlertaService::class)->evaluar($id, $situacion);
                    $view->with('alertas', $alertasCache[$id]);
                }
            }
        });
    }
}
