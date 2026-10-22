<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

final class Webhook
{
    /**
     * @param  array<string, mixed>  $event
     * @return array{delivered: bool, status: int|null, error: string|null}
     */
    public static function deliver(string $url, array $event, string $secret, bool $allowPrivate = false): array
    {
        if (! self::isAllowedUrl($url, $allowPrivate)) {
            return [
                'delivered' => false,
                'status' => null,
                'error' => 'The callback URL is not a public http(s) URL.',
            ];
        }

        $payload = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = time();

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'User-Agent: MomoKit-Sandbox/0.1',
                    'X-Momokit-Signature: '.self::sign($payload, $secret, $timestamp),
                ]),
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return [
                'delivered' => false,
                'status' => null,
                'error' => 'The callback could not be reached.',
            ];
        }

        $status = self::statusFromHeaders($http_response_header ?? []);

        if ($status === null) {
            return [
                'delivered' => false,
                'status' => null,
                'error' => 'The callback returned no HTTP status.',
            ];
        }

        if ($status < 200 || $status >= 300) {
            return [
                'delivered' => false,
                'status' => $status,
                'error' => 'The callback answered with HTTP '.$status.'.',
            ];
        }

        return ['delivered' => true, 'status' => $status, 'error' => null];
    }

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return 't='.$timestamp.',s='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public static function isAllowedUrl(string $url, bool $allowPrivate = false): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($allowPrivate) {
            return true;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $addresses = gethostbynamel($host) ?: [];
        }

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     */
    private static function statusFromHeaders(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
