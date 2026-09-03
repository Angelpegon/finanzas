<?php

namespace App\Providers;

use App\Services\SituacionFinancieraService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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
        Blade::directive('cop', function (string $expression) {
            return "<?php echo \\App\\Support\\Dinero::formatear($expression); ?>";
        });

        View::composer('layouts.app', function ($view) {
            if (! Auth::check() || $view->offsetExists('situacion')) {
                return;
            }
            static $cache = [];
            $id = Auth::id();
            $cache[$id] ??= app(SituacionFinancieraService::class)->responder($id);
            $view->with('situacion', $cache[$id]);
        });
    }
}
