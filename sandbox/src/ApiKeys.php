<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

use PDO;

final class ApiKeys
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{key: string, webhook_secret: string, created_at: string}
     */
    public function create(): array
    {
        $key = 'sk_sandbox_'.bin2hex(random_bytes(18));
        $webhookSecret = 'whsec_'.bin2hex(random_bytes(18));
        $createdAt = gmdate('c');

        $this->pdo->prepare(
            'INSERT INTO api_keys (key, webhook_secret, created_at) VALUES (?, ?, ?)',
        )->execute([$key, $webhookSecret, $createdAt]);

        return [
            'key' => $key,
            'webhook_secret' => $webhookSecret,
            'created_at' => $createdAt,
        ];
    }

    /**
     * @return array{key: string, webhook_secret: string, created_at: string}|null
     */
    public function find(string $key): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM api_keys WHERE key = ?');
        $statement->execute([$key]);

        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'key' => (string) $row['key'],
            'webhook_secret' => (string) $row['webhook_secret'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
