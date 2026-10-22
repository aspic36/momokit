<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

final class Request
{
    /**
     * @param  array<string, string>  $headers  Lower-cased header names.
     * @param  array<string, string>  $query
     * @param  array<string, string>  $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $cookies = [],
        public readonly string $body = '',
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');

        if ($header === null || ! str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token === '' ? null : $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : [];
    }

    public function form(string $field): ?string
    {
        parse_str($this->body, $fields);

        $value = $fields[$field] ?? null;

        return is_string($value) ? $value : null;
    }
}
