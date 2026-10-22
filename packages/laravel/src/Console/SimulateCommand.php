<?php

declare(strict_types=1);

namespace MomoKit\Laravel\Console;

use Illuminate\Console\Command;
use MomoKit\Core\DTO\CollectRequest;
use MomoKit\Core\Enums\PaymentStatus;
use MomoKit\Core\Exceptions\MobileMoneyException;
use MomoKit\Core\MobileMoneyManager;

final class SimulateCommand extends Command
{
    protected $signature = 'mobile-money:simulate
        {driver? : Driver name (mock, fedapay, ...)}
        {--amount=1000 : Amount in the smallest currency unit}
        {--currency=XOF : ISO 4217 currency code}
        {--phone=+22997000001 : Customer phone number}
        {--description= : Payment description}
        {--status=pending : Final status (pending, succeeded, failed, cancelled, refunded)}';

    protected $description = 'Simulate a mobile money payment locally, without network or credentials';

    public function handle(): int
    {
        /** @var MobileMoneyManager $manager */
        $manager = $this->laravel->make('mobile-money');

        $driver = (string) ($this->argument('driver') ?? $manager->defaultDriver() ?? 'mock');
        $status = PaymentStatus::tryFrom((string) $this->option('status'));

        if ($status === null) {
            $this->components->error(sprintf(
                'Unknown status [%s]. Use one of: %s.',
                (string) $this->option('status'),
                implode(', ', array_map(
                    static fn (PaymentStatus $case): string => $case->value,
                    PaymentStatus::cases(),
                )),
            ));

            return self::FAILURE;
        }

        try {
            $fake = $manager->fake($driver);

            $description = trim((string) $this->option('description'));

            $request = CollectRequest::make(
                amount: (int) $this->option('amount'),
                currency: (string) $this->option('currency'),
                phone: (string) $this->option('phone'),
                description: $description === '' ? null : $description,
            );

            $payment = $fake->driver($driver)->collect($request);

            if ($status !== PaymentStatus::Pending) {
                $fake->markAs($payment->id, $status, $driver);
                $payment = $fake->driver($driver)->status($payment->id);
            }
        } catch (MobileMoneyException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], array_values(array_filter([
            ['Driver', $payment->driver],
            ['Payment ID', $payment->id],
            ['Status', $payment->status->value],
            ['Amount', sprintf('%d %s', $payment->amount, $payment->currency)],
            ['Phone', $payment->phone],
            ['Description', $payment->description],
            ['Checkout URL', $payment->checkoutUrl],
        ], static fn (array $row): bool => $row[1] !== null)));

        $this->components->info('Simulation only: no network call, no real money.');

        return self::SUCCESS;
    }
}
