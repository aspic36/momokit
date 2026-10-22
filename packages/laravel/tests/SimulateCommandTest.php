<?php

declare(strict_types=1);

namespace MomoKit\Laravel\Tests;

use MomoKit\Laravel\MobileMoneyServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

final class SimulateCommandTest extends OrchestraTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            MobileMoneyServiceProvider::class,
        ];
    }

    public function test_it_simulates_a_pending_payment(): void
    {
        $this->artisan('mobile-money:simulate', [
            'driver' => 'mock',
            '--amount' => 5000,
            '--phone' => '+22997000001',
            '--description' => 'Commande #42',
        ])
            ->expectsOutputToContain('mock_fake_0001')
            ->expectsOutputToContain('pending')
            ->expectsOutputToContain('no network call')
            ->assertSuccessful();
    }

    public function test_it_simulates_a_successful_fedapay_payment_without_credentials(): void
    {
        $this->artisan('mobile-money:simulate', [
            'driver' => 'fedapay',
            '--amount' => 5000,
            '--status' => 'succeeded',
        ])
            ->expectsOutputToContain('fedapay_fake_0001')
            ->expectsOutputToContain('succeeded')
            ->assertSuccessful();
    }

    public function test_it_rejects_an_unknown_status(): void
    {
        $this->artisan('mobile-money:simulate', [
            '--status' => 'paid',
        ])
            ->expectsOutputToContain('Unknown status')
            ->assertFailed();
    }

    public function test_it_rejects_an_invalid_amount(): void
    {
        $this->artisan('mobile-money:simulate', [
            '--amount' => 0,
        ])
            ->expectsOutputToContain('positive integer')
            ->assertFailed();
    }
}
