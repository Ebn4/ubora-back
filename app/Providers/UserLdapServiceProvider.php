<?php

namespace App\Providers;

use App\UserLdapServiceImpl;
use App\Services\UserLdapService;
use Illuminate\Support\ServiceProvider;

class UserLdapServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(UserLdapService::class, function () {
            return new UserLdapServiceImpl();
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
