<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configurePassport();
        $this->configureLocalRuntimeFallbacks();
    }

    protected function configurePassport(): void
    {
        Passport::enablePasswordGrant();
    }

    protected function configureLocalRuntimeFallbacks(): void
    {
        if (!$this->app->environment('local', 'testing')) {
            return;
        }

        if (config('session.driver') === 'redis' && !extension_loaded('redis')) {
            config(['session.driver' => 'file']);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
