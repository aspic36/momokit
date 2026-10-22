<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

final class Response
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public static function json(int $status, array $data, array $headers = []): self
    {
        return new self(
            status: $status,
            body: json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            headers: $headers + ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function html(int $status, string $html, array $headers = []): self
    {
        return new self(
            status: $status,
            body: $html,
            headers: $headers + ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    public static function redirect(string $location): self
    {
        return new self(303, '', ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name.': '.$value);
        }

        echo $this->body;
    }
}
