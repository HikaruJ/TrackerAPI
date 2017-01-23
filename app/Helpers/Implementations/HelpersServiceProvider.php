<?php namespace Helpers;

use Illuminate\Support\ServiceProvider;

class HelpersServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('Helpers\TokenHelperInterface', function ($app) {
            return new TokenHelper();
        });
    }
}