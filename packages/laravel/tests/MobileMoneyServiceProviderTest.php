<?php

declare(strict_types=1);

namespace MomoKit\Laravel\Tests;

use MomoKit\Core\DTO\CollectRequest;
use MomoKit\Core\Enums\PaymentStatus;
use MomoKit\Core\Exceptions\PaymentTimeoutException;
use MomoKit\Laravel\Facades\MobileMoney;
use MomoKit\Laravel\MobileMoneyServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

final class MobileMoneyServiceProviderTest extends OrchestraTestCase
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

    public function test_the_mock_driver_is_the_default(): void
    {
        $this->assertSame('mock', MobileMoney::driver()->name());
    }

    public function test_config_is_available(): void
    {
        $this->assertSame('mock', config('mobile-money.default'));
        $this->assertArrayHasKey('mock', config('mobile-money.drivers'));
    }

    public function test_collect_with_a_success_number(): void
    {
        $payment = MobileMoney::collect(
            CollectRequest::make(5000, 'XOF', '+229 97 00 00 01', 'Commande #42'),
        );

        $this->assertTrue($payment->succeeded());
        $this->assertSame(5000, $payment->amount);
        $this->assertSame('XOF', $payment->currency);
        $this->assertSame('Commande #42', $payment->description);
    }

    public function test_collect_with_an_insufficient_funds_number(): void
    {
        $payment = MobileMoney::collect(
            CollectRequest::make(5000, 'XOF', '+22997000002'),
        );

        $this->assertSame(PaymentStatus::Failed, $payment->status);
    }

    public function test_collect_with_a_timeout_number_throws(): void
    {
        $this->expectException(PaymentTimeoutException::class);

        MobileMoney::collect(CollectRequest::make(5000, 'XOF', '+22997000003'));
    }

    public function test_the_default_driver_can_be_overridden(): void
    {
        config()->set('mobile-money.drivers.mock.driver', 'mock');
        config()->set('mobile-money.default', 'mock');

        $this->assertSame('mock', MobileMoney::driver()->name());
    }
}
