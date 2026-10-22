<?php

declare(strict_types=1);

namespace MomoKit\Sandbox\Tests;

use MomoKit\Sandbox\App;
use MomoKit\Sandbox\Database;
use MomoKit\Sandbox\Request;
use MomoKit\Sandbox\Response;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = new App(Database::connect(':memory:'), 'https://sandbox.test');
    }

    public function test_health_check(): void
    {
        $response = $this->json('GET', '/health');

        $this->assertSame(200, $response->status);
        $this->assertSame(['status' => 'ok'], $this->payload($response));
    }

    public function test_a_key_is_created_with_a_webhook_secret(): void
    {
        $response = $this->json('POST', '/api/v1/keys');

        $this->assertSame(201, $response->status);

        $key = $this->payload($response);

        $this->assertStringStartsWith('sk_sandbox_', $key['key']);
        $this->assertStringStartsWith('whsec_', $key['webhook_secret']);
    }

    public function test_payment_creation_requires_a_key(): void
    {
        $response = $this->json('POST', '/api/v1/payments', [
            'amount' => 5000,
            'currency' => 'XOF',
            'phone' => '+22997000001',
        ]);

        $this->assertSame(401, $response->status);
        $this->assertSame('unauthorized', $this->payload($response)['error']);
    }

    public function test_a_payment_is_created_pending(): void
    {
        $key = $this->newKey();

        $response = $this->api('POST', '/api/v1/payments', $key, [
            'amount' => 5000,
            'currency' => 'xof',
            'phone' => '+229 97 00 00 01',
            'description' => 'Commande #42',
        ]);

        $this->assertSame(201, $response->status);

        $payment = $this->payload($response);

        $this->assertStringStartsWith('pay_', $payment['id']);
        $this->assertSame('pending', $payment['status']);
        $this->assertSame(5000, $payment['amount']);
        $this->assertSame('XOF', $payment['currency']);
        $this->assertSame('+22997000001', $payment['phone']);
        $this->assertSame('Commande #42', $payment['description']);
        $this->assertSame('https://sandbox.test/pay/'.$payment['id'], $payment['checkout_url']);
        $this->assertNull($payment['webhook']['status']);
    }

    public function test_invalid_amounts_and_phones_are_rejected(): void
    {
        $key = $this->newKey();

        $zero = $this->api('POST', '/api/v1/payments', $key, [
            'amount' => 0,
            'currency' => 'XOF',
            'phone' => '+22997000001',
        ]);

        $shortPhone = $this->api('POST', '/api/v1/payments', $key, [
            'amount' => 1000,
            'currency' => 'XOF',
            'phone' => '123',
        ]);

        $this->assertSame(422, $zero->status);
        $this->assertSame(422, $shortPhone->status);
        $this->assertSame('invalid_request', $this->payload($zero)['error']);
    }

    public function test_payments_are_scoped_to_their_key(): void
    {
        $key = $this->newKey();
        $other = $this->newKey();
        $id = $this->createPayment($key);

        $this->assertSame(200, $this->api('GET', '/api/v1/payments/'.$id, $key)->status);
        $this->assertSame(404, $this->api('GET', '/api/v1/payments/'.$id, $other)->status);
    }

    public function test_simulation_updates_the_status(): void
    {
        $key = $this->newKey();
        $id = $this->createPayment($key);

        $response = $this->api('POST', '/api/v1/payments/'.$id.'/simulate', $key, [
            'status' => 'succeeded',
        ]);

        $this->assertSame(200, $response->status);

        $body = $this->payload($response);

        $this->assertSame('succeeded', $body['payment']['status']);
        $this->assertNull($body['webhook']);

        $status = $this->payload($this->api('GET', '/api/v1/payments/'.$id, $key));

        $this->assertSame('succeeded', $status['status']);
    }

    public function test_an_invalid_simulation_status_is_rejected(): void
    {
        $key = $this->newKey();
        $id = $this->createPayment($key);

        $response = $this->api('POST', '/api/v1/payments/'.$id.'/simulate', $key, [
            'status' => 'paid',
        ]);

        $this->assertSame(422, $response->status);
        $this->assertSame('invalid_status', $this->payload($response)['error']);
    }

    public function test_a_private_callback_url_is_rejected(): void
    {
        $key = $this->newKey();

        $response = $this->api('POST', '/api/v1/payments', $key, [
            'amount' => 1000,
            'currency' => 'XOF',
            'phone' => '+22997000001',
            'callback_url' => 'http://127.0.0.1:9000/hook',
        ]);

        $this->assertSame(422, $response->status);
        $this->assertSame('invalid_callback_url', $this->payload($response)['error']);
    }

    public function test_the_home_page_issues_and_reuses_a_key(): void
    {
        $response = $this->json('GET', '/');

        $this->assertSame(200, $response->status);
        $this->assertArrayHasKey('Set-Cookie', $response->headers);

        preg_match('/momokit_key=([^;]+)/', $response->headers['Set-Cookie'], $matches);
        $key = $matches[1];

        $this->assertStringContainsString($key, $response->body);

        $again = $this->json('GET', '/', [], [], ['momokit_key' => $key]);

        $this->assertArrayNotHasKey('Set-Cookie', $again->headers);
        $this->assertStringContainsString($key, $again->body);
    }

    public function test_the_checkout_page_can_complete_a_payment(): void
    {
        $key = $this->newKey();
        $id = $this->createPayment($key);

        $page = $this->json('GET', '/pay/'.$id);

        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('Payer', $page->body);

        $response = $this->app->handle(new Request(
            method: 'POST',
            path: '/pay/'.$id,
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            body: 'status=succeeded',
        ));

        $this->assertSame(303, $response->status);
        $this->assertSame('/pay/'.$id, $response->headers['Location']);

        $status = $this->payload($this->api('GET', '/api/v1/payments/'.$id, $key));

        $this->assertSame('succeeded', $status['status']);
    }

    public function test_unknown_payments_return_404(): void
    {
        $key = $this->newKey();

        $this->assertSame(404, $this->api('GET', '/api/v1/payments/pay_missing', $key)->status);
        $this->assertSame(404, $this->json('GET', '/pay/pay_missing')->status);
        $this->assertSame(404, $this->api('POST', '/api/v1/payments/pay_missing/simulate', $key, ['status' => 'succeeded'])->status);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $cookies
     */
    private function json(string $method, string $path, array $body = [], array $headers = [], array $cookies = []): Response
    {
        return $this->app->handle(new Request(
            method: $method,
            path: $path,
            headers: $headers,
            cookies: $cookies,
            body: $body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function api(string $method, string $path, string $key, array $body = []): Response
    {
        return $this->json($method, $path, $body, [
            'authorization' => 'Bearer '.$key,
            'content-type' => 'application/json',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function newKey(): string
    {
        return $this->payload($this->json('POST', '/api/v1/keys'))['key'];
    }

    private function createPayment(string $key): string
    {
        return $this->payload($this->api('POST', '/api/v1/payments', $key, [
            'amount' => 5000,
            'currency' => 'XOF',
            'phone' => '+22997000001',
            'description' => 'Commande #42',
        ]))['id'];
    }
}
