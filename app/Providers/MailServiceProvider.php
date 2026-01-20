<?php

namespace App\Providers;
use App\MailServiceImpl;
use App\Services\MailService;
use Illuminate\Support\ServiceProvider;


class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MailService::class, function () {
            return new MailServiceImpl();
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
