<?php

namespace App\Providers;

use App\Auth\UniqueEmailEloquentUserProvider;
use App\Services\Mail\MailConfigurationService;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

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

        try {
            if (Schema::hasTable('configuracion_correos')) {
                $this->app->make(MailConfigurationService::class)->loadAndApply();
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo cargar la configuración SMTP almacenada', [
                'exception' => $exception::class,
            ]);
        }

        URL::forceScheme('https');

        //
    }
}
