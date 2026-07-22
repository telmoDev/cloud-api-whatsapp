<?php

namespace Telmo\CloudApiWhatsapp;

use Illuminate\Support\ServiceProvider;

class CloudApiWhatsappServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cloud-api-whatsapp.php',
            'cloud-api-whatsapp'
        );

        // Bind main class
        $this->app->singleton('cloud-api-whatsapp', function ($app) {
            $config = $app['config']->get('cloud-api-whatsapp');
            return new CloudApiWhatsapp($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cloud-api-whatsapp.php' => config_path('cloud-api-whatsapp.php'),
            ], 'cloud-api-whatsapp-config');
        }
    }
}
