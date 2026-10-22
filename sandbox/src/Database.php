<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

use PDO;

final class Database
{
    public static function connect(string $path): PDO
    {
        if ($path !== ':memory:') {
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        $pdo = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS api_keys (
                key TEXT PRIMARY KEY,
                webhook_secret TEXT NOT NULL,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS payments (
                id TEXT PRIMARY KEY,
                api_key TEXT NOT NULL,
                amount INTEGER NOT NULL,
                currency TEXT NOT NULL,
                phone TEXT NOT NULL,
                description TEXT,
                status TEXT NOT NULL,
                callback_url TEXT,
                webhook_status INTEGER,
                webhook_error TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS payments_api_key ON payments (api_key);
            SQL);
    }
}
