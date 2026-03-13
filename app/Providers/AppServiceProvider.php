<?php

namespace App\Providers;

use App\Support\Pyro\SafeAddonManager;
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
        $this->configurePyroRuntimeFallbacks();
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

    protected function configurePyroRuntimeFallbacks(): void
    {
        $this->app->extend(
            'Anomaly\Streams\Platform\Addon\AddonManager',
            fn ($manager) => new SafeAddonManager(
                $this->app->make('Anomaly\Streams\Platform\Addon\AddonPaths'),
                $this->app->make('Anomaly\Streams\Platform\Addon\AddonLoader'),
                $this->app->make('Anomaly\Streams\Platform\Addon\Module\ModuleModel'),
                $this->app,
                $this->app->make('Anomaly\Streams\Platform\Addon\Extension\ExtensionModel'),
                $this->app->make('Anomaly\Streams\Platform\Addon\AddonIntegrator'),
                $this->app->make('Anomaly\Streams\Platform\Addon\AddonCollection')
            )
        );
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
