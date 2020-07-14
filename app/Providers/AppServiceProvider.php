<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */

    public function boot()
    {
    	if(config('app.secure_https')){
            \URL::forceScheme('https');
        }
    }
    public function register()
    {
        //
    }
}
