<?php

declare(strict_types=1);

namespace MomoKit\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MomoKit\Core\Drivers\Mock\MockDriver;
use MomoKit\Core\MobileMoneyManager;

class MobileMoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mobile-money.php', 'mobile-money');

        $this->app->singleton('mobile-money', function (Application $app): MobileMoneyManager {
            $manager = new MobileMoneyManager($app['config']->get('mobile-money.default'));

            /** @var array<string, array{driver?: string}> $drivers */
            $drivers = $app['config']->get('mobile-money.drivers', []);

            foreach ($drivers as $name => $config) {
                $driver = $config['driver'] ?? $name;

                if ($driver === 'mock') {
                    $manager->register(new MockDriver());
                }
            }

            return $manager;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mobile-money.php' => config_path('mobile-money.php'),
            ], 'mobile-money-config');
        }
    }
}
