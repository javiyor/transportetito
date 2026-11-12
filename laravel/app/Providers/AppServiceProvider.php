<?php

namespace App\Providers;

use App\Auth\UniqueEmailEloquentUserProvider;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppClient::class, function () {
            return new WhatsAppClient;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('eloquent-unique-email', function ($app, array $config) {
            return new UniqueEmailEloquentUserProvider($app['hash'], $config['model']);
        });

        URL::forceScheme('https');

        //
    }
}
