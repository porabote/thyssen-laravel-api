<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ClientAuthService;

class ClientAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ClientAuthService::class, function ($app) {
            return new ClientAuthService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
