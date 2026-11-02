<?php

namespace App\Providers;

use App\Auth\LegacyUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Auth::provider('legacy', fn ($app, array $config) => new LegacyUserProvider($app['hash'], $config['model']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
