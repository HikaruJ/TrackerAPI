<?php namespace Office365;

use Helpers;
use Illuminate\Support\ServiceProvider;

class Office365ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('Office365\Office365ClientInterface', function ($app) {
            $client = new \GuzzleHttp\Client();
            return new Office365Client($client);
        });

        $this->app->bind('Office365\Office365DBClientInterface', function ($app) {
            return new Office365DBClient();
        });
    }
}