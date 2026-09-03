<?php

namespace App\Providers;

use App\Support\Dinero;
use Illuminate\Support\Facades\Blade;
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
    }
}
