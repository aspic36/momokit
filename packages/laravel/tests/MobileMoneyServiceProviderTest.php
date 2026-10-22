<?php

declare(strict_types=1);

namespace MomoKit\Laravel\Tests;

use MomoKit\Core\DTO\CollectRequest;
use MomoKit\Core\Enums\PaymentStatus;
use MomoKit\Core\Exceptions\PaymentTimeoutException;
use MomoKit\Core\Exceptions\UnknownDriverException;
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

    public function test_the_fedapay_driver_is_registered_when_a_secret_key_is_configured(): void
    {
        config()->set('mobile-money.drivers.fedapay', [
            'driver' => 'fedapay',
            'secret_key' => 'sk_sandbox_test',
            'webhook_secret' => 'wh_sandbox_test',
            'environment' => 'sandbox',
        ]);

        $this->assertSame('fedapay', MobileMoney::driver('fedapay')->name());
    }

    public function test_the_fedapay_driver_is_skipped_without_a_secret_key(): void
    {
        $this->expectException(UnknownDriverException::class);

        MobileMoney::driver('fedapay');
    }

    public function test_the_facade_fake_intercepts_collects(): void
    {
        $fake = MobileMoney::fake('fedapay');

        $payment = MobileMoney::collect(
            CollectRequest::make(5000, 'XOF', '+22997000001'),
            driver: 'fedapay',
        );

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertStringStartsWith('fedapay_fake_', $payment->id);

        $fake->assertCollected(static fn (CollectRequest $request): bool => $request->amount === 5000);

        $fake->succeed($payment->id);

        $this->assertSame(
            PaymentStatus::Succeeded,
            MobileMoney::driver('fedapay')->status($payment->id)->status,
        );
    }
}
