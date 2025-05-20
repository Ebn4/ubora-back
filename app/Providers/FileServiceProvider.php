<?php

namespace App\Providers;

use FileService;
use FileServiceImplementation;
use Illuminate\Support\ServiceProvider;

class FileServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FileService::class, function ($app) {
            return new FileServiceImplementation();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
