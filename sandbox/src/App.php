<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

use MomoKit\Core\Exceptions\InvalidAmountException;
use MomoKit\Core\Exceptions\InvalidPhoneNumberException;
use PDO;

final class App
{
    private ApiKeys $keys;

    private Payments $payments;

    public function __construct(
        PDO $pdo,
        private readonly string $baseUrl,
        private readonly bool $allowPrivateWebhooks = false,
    ) {
        $this->keys = new ApiKeys($pdo);
        $this->payments = new Payments($pdo);
    }

    public function handle(Request $request): Response
    {
        $path = rtrim($request->path, '/');
        $path = $path === '' ? '/' : $path;
        $method = strtoupper($request->method);

        if ($path === '/health') {
            return Response::json(200, ['status' => 'ok']);
        }

        if ($path === '/' && $method === 'GET') {
            return $this->home($request);
        }

        if ($path === '/api/v1/keys' && $method === 'POST') {
            return Response::json(201, $this->keys->create());
        }

        if ($path === '/api/v1/payments' && $method === 'POST') {
            return $this->createPayment($request);
        }

        if (preg_match('#^/api/v1/payments/([A-Za-z0-9_]+)$#', $path, $matches) === 1 && $method === 'GET') {
            return $this->showPayment($request, $matches[1]);
        }

        if (preg_match('#^/api/v1/payments/([A-Za-z0-9_]+)/simulate$#', $path, $matches) === 1 && $method === 'POST') {
            return $this->simulate($request, $matches[1]);
        }

        if (preg_match('#^/pay/([A-Za-z0-9_]+)$#', $path, $matches) === 1 && $method === 'GET') {
            return $this->checkout($matches[1]);
        }

        if (preg_match('#^/pay/([A-Za-z0-9_]+)$#', $path, $matches) === 1 && $method === 'POST') {
            return $this->completeCheckout($request, $matches[1]);
        }

        return Response::json(404, [
            'error' => 'not_found',
            'message' => 'Unknown route. Open / for the sandbox documentation.',
        ]);
    }

    private function home(Request $request): Response
    {
        $headers = ['Cache-Control' => 'no-store'];

        $cookieKey = $request->cookies['momokit_key'] ?? null;
        $record = is_string($cookieKey) ? $this->keys->find($cookieKey) : null;

        if ($record === null || ($request->query['reset'] ?? '') === '1') {
            $record = $this->keys->create();
            $headers['Set-Cookie'] = 'momokit_key='.$record['key'].'; Path=/; Max-Age=2592000; HttpOnly; SameSite=Lax';
        }

        return Response::html(200, View::render('home', [
            'key' => $record['key'],
            'webhookSecret' => $record['webhook_secret'],
            'baseUrl' => $this->baseUrl,
            'payments' => $this->payments->recent($record['key']),
        ]), $headers);
    }

    private function createPayment(Request $request): Response
    {
        $apiKey = $this->authenticatedKey($request);

        if ($apiKey === null) {
            return $this->unauthorized();
        }

        $input = $request->json();

        if ($input === []) {
            return Response::json(422, [
                'error' => 'invalid_request',
                'message' => 'Send a JSON body with amount, currency and phone.',
            ]);
        }

        $callbackUrl = str_value($input['callback_url'] ?? null);

        if ($callbackUrl !== null && ! Webhook::isAllowedUrl($callbackUrl, $this->allowPrivateWebhooks)) {
            return Response::json(422, [
                'error' => 'invalid_callback_url',
                'message' => 'callback_url must be a public http(s) URL.',
            ]);
        }

        try {
            $payment = $this->payments->create($apiKey, $input);
        } catch (InvalidAmountException|InvalidPhoneNumberException $exception) {
            return Response::json(422, [
                'error' => 'invalid_request',
                'message' => $exception->getMessage(),
            ]);
        }

        return Response::json(201, Payments::toArray($payment, $this->baseUrl));
    }

    private function showPayment(Request $request, string $id): Response
    {
        $apiKey = $this->authenticatedKey($request);

        if ($apiKey === null) {
            return $this->unauthorized();
        }

        $payment = $this->payments->find($id, $apiKey);

        if ($payment === null) {
            return $this->paymentNotFound();
        }

        return Response::json(200, Payments::toArray($payment, $this->baseUrl));
    }

    private function simulate(Request $request, string $id): Response
    {
        $apiKey = $this->authenticatedKey($request);

        if ($apiKey === null) {
            return $this->unauthorized();
        }

        $payment = $this->payments->find($id, $apiKey);

        if ($payment === null) {
            return $this->paymentNotFound();
        }

        $status = str_value($request->json()['status'] ?? null) ?? '';

        if (! in_array($status, Payments::FINAL_STATUSES, true)) {
            return Response::json(422, [
                'error' => 'invalid_status',
                'message' => 'status must be one of: '.implode(', ', Payments::FINAL_STATUSES).'.',
            ]);
        }

        $updated = $this->payments->markAs($id, $status) ?? $payment;
        $webhook = $this->deliverWebhook($updated);
        $updated = $this->payments->find($id, null) ?? $updated;

        return Response::json(200, [
            'payment' => Payments::toArray($updated, $this->baseUrl),
            'webhook' => $webhook,
        ]);
    }

    private function checkout(string $id): Response
    {
        $payment = $this->payments->find($id, null);

        if ($payment === null) {
            return Response::html(404, View::render('not-found'), ['Cache-Control' => 'no-store']);
        }

        return Response::html(200, View::render('checkout', [
            'payment' => Payments::toArray($payment, $this->baseUrl),
        ]), ['Cache-Control' => 'no-store']);
    }

    private function completeCheckout(Request $request, string $id): Response
    {
        $payment = $this->payments->find($id, null);

        if ($payment === null) {
            return Response::html(404, View::render('not-found'), ['Cache-Control' => 'no-store']);
        }

        $status = $request->form('status') ?? '';

        if (! in_array($status, Payments::FINAL_STATUSES, true)) {
            return Response::html(422, View::render('checkout', [
                'payment' => Payments::toArray($payment, $this->baseUrl),
                'error' => 'Statut de simulation inconnu.',
            ]), ['Cache-Control' => 'no-store']);
        }

        $updated = $this->payments->markAs($id, $status) ?? $payment;
        $this->deliverWebhook($updated);

        return Response::redirect('/pay/'.$id);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{delivered: bool, status: int|null, error: string|null}|null
     */
    private function deliverWebhook(array $payment): ?array
    {
        $url = str_value($payment['callback_url'] ?? null);

        if ($url === null) {
            return null;
        }

        $keyRecord = $this->keys->find((string) $payment['api_key']);
        $secret = $keyRecord['webhook_secret'] ?? '';

        $event = [
            'event' => 'payment.'.$payment['status'],
            'payment' => Payments::toArray($payment, $this->baseUrl),
        ];

        $result = Webhook::deliver($url, $event, $secret, $this->allowPrivateWebhooks);

        $this->payments->recordWebhook(
            (string) $payment['id'],
            $result['status'],
            $result['delivered'] ? null : $result['error'],
        );

        return $result;
    }

    private function authenticatedKey(Request $request): ?string
    {
        $token = $request->bearerToken();

        if ($token === null || $this->keys->find($token) === null) {
            return null;
        }

        return $token;
    }

    private function unauthorized(): Response
    {
        return Response::json(401, [
            'error' => 'unauthorized',
            'message' => 'Send a valid sandbox key: Authorization: Bearer sk_sandbox_...',
        ]);
    }

    private function paymentNotFound(): Response
    {
        return Response::json(404, [
            'error' => 'payment_not_found',
            'message' => 'No payment with this id for your key.',
        ]);
    }
}
