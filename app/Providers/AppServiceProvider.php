<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(app_path('Modules/Monitoring/config.php'), 'monitoring');
        $this->mergeConfigFrom(app_path('Modules/TelegramAccess/config.php'), 'telegram_access');
        $this->mergeConfigFrom(app_path('Modules/TelegramSupport/config.php'), 'telegram_support');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
