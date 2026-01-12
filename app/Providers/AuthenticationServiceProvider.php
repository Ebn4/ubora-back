<?php

namespace App\Providers;

use App\AuthenticationServiceImpl;
use App\Services\AuthenticationService;
use App\Services\UserService;
use App\Services\UserLdapService;
use Illuminate\Support\ServiceProvider;

class AuthenticationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthenticationService::class, function ($app) {
            $userService = $app->make(UserService::class);
            $userLdapService = $app->make(UserLdapService::class);
            return new AuthenticationServiceImpl($userService,$userLdapService);
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
