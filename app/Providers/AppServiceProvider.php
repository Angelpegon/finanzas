<?php

namespace App\Providers;

use App\Services\AlertaService;
use App\Services\SituacionFinancieraService;
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
        if ($this->app->environment('production') || config('app.force_https')) {
            URL::forceScheme('https');
        }

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
