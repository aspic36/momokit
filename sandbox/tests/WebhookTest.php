<?php

declare(strict_types=1);

namespace MomoKit\Sandbox\Tests;

use MomoKit\Sandbox\Webhook;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    public function test_the_signature_is_stable(): void
    {
        $payload = '{"event":"payment.succeeded"}';
        $signature = Webhook::sign($payload, 'whsec_test', 1730000000);

        $this->assertSame(
            't=1730000000,s='.hash_hmac('sha256', '1730000000.'.$payload, 'whsec_test'),
            $signature,
        );
    }

    public function test_private_and_loopback_urls_are_rejected(): void
    {
        $this->assertFalse(Webhook::isAllowedUrl('http://127.0.0.1:9000/hook'));
        $this->assertFalse(Webhook::isAllowedUrl('http://localhost/hook'));
        $this->assertFalse(Webhook::isAllowedUrl('http://10.0.0.1/hook'));
        $this->assertFalse(Webhook::isAllowedUrl('http://192.168.1.10/hook'));
        $this->assertFalse(Webhook::isAllowedUrl('ftp://example.com/hook'));
        $this->assertFalse(Webhook::isAllowedUrl('not a url'));
    }

    public function test_public_urls_are_allowed(): void
    {
        $this->assertTrue(Webhook::isAllowedUrl('https://93.184.216.34/hook'));
        $this->assertTrue(Webhook::isAllowedUrl('https://example.com/hook'));
    }

    public function test_private_urls_can_be_allowed_for_local_development(): void
    {
        $this->assertTrue(Webhook::isAllowedUrl('http://127.0.0.1:9000/hook', true));
    }
}
