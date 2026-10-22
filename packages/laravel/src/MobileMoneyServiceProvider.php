<?php

declare(strict_types=1);

namespace MomoKit\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use MomoKit\Core\Contracts\Driver;
use MomoKit\Core\Drivers\FedaPay\FedaPayConfig;
use MomoKit\Core\Drivers\FedaPay\FedaPayDriver;
use MomoKit\Core\Drivers\Mock\MockDriver;
use MomoKit\Core\MobileMoneyManager;

class MobileMoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mobile-money.php', 'mobile-money');

        $this->app->singleton('mobile-money', function (Application $app): MobileMoneyManager {
            $manager = new MobileMoneyManager($app['config']->get('mobile-money.default'));

            /** @var array<string, array{driver?: string, secret_key?: string, webhook_secret?: string, environment?: string, callback_url?: string}> $drivers */
            $drivers = $app['config']->get('mobile-money.drivers', []);

            foreach ($drivers as $name => $config) {
                $driver = $config['driver'] ?? $name;

                if ($driver === 'mock') {
                    $manager->register(new MockDriver());

                    continue;
                }

                if ($driver === 'fedapay') {
                    $secretKey = (string) ($config['secret_key'] ?? '');

                    if ($secretKey === '') {
                        continue;
                    }

                    $manager->register($this->makeFedaPayDriver($config));
                }
            }

            return $manager;
        });
    }

    /**
     * @param  array{secret_key?: string, webhook_secret?: string, environment?: string, callback_url?: string}  $config
     */
    private function makeFedaPayDriver(array $config): Driver
    {
        $factory = new HttpFactory();
        $webhookSecret = (string) ($config['webhook_secret'] ?? '');
        $callbackUrl = (string) ($config['callback_url'] ?? '');

        return new FedaPayDriver(
            http: new Client(),
            requestFactory: $factory,
            streamFactory: $factory,
            config: new FedaPayConfig(
                secretKey: (string) ($config['secret_key'] ?? ''),
                environment: (string) ($config['environment'] ?? 'sandbox'),
                webhookSecret: $webhookSecret === '' ? null : $webhookSecret,
                callbackUrl: $callbackUrl === '' ? null : $callbackUrl,
            ),
        );
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
