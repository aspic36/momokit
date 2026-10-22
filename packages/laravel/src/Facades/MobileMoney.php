<?php

declare(strict_types=1);

namespace MomoKit\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use MomoKit\Core\Contracts\Driver;
use MomoKit\Core\DTO\CollectRequest;
use MomoKit\Core\DTO\Payment;
use MomoKit\Core\MobileMoneyManager;
use MomoKit\Core\Testing\MobileMoneyFake;

/**
 * @method static MobileMoneyManager register(Driver $driver)
 * @method static Driver driver(?string $name = null)
 * @method static Payment collect(CollectRequest $request, ?string $driver = null)
 * @method static MobileMoneyFake fake(string ...$drivers)
 *
 * @see MobileMoneyManager
 */
class MobileMoney extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mobile-money';
    }
}
