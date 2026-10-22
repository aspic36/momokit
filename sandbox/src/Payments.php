<?php

declare(strict_types=1);

namespace MomoKit\Sandbox;

use MomoKit\Core\DTO\CollectRequest;
use PDO;
use RuntimeException;

final class Payments
{
    public const FINAL_STATUSES = ['succeeded', 'failed', 'cancelled'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(string $apiKey, array $input): array
    {
        $amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_INT);

        $request = CollectRequest::make(
            amount: $amount === false ? 0 : $amount,
            currency: strtoupper(str_value($input['currency'] ?? null) ?? 'XOF'),
            phone: str_value($input['phone'] ?? null) ?? '',
            description: $this->description($input['description'] ?? null),
            callbackUrl: str_value($input['callback_url'] ?? null),
        );

        $now = gmdate('c');
        $id = 'pay_'.bin2hex(random_bytes(10));

        $this->pdo->prepare(
            'INSERT INTO payments
                (id, api_key, amount, currency, phone, description, status, callback_url, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([
            $id,
            $apiKey,
            $request->amount,
            $request->currency,
            (string) $request->phone,
            $request->description,
            'pending',
            $request->callbackUrl,
            $now,
            $now,
        ]);

        $this->prune();

        return $this->find($id, $apiKey) ?? throw new RuntimeException('The payment was not persisted.');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id, ?string $apiKey): ?array
    {
        $sql = 'SELECT * FROM payments WHERE id = ?';
        $parameters = [$id];

        if ($apiKey !== null) {
            $sql .= ' AND api_key = ?';
            $parameters[] = $apiKey;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function markAs(string $id, string $status): ?array
    {
        $this->pdo->prepare('UPDATE payments SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, gmdate('c'), $id]);

        return $this->find($id, null);
    }

    public function recordWebhook(string $id, ?int $status, ?string $error): void
    {
        $this->pdo->prepare('UPDATE payments SET webhook_status = ?, webhook_error = ? WHERE id = ?')
            ->execute([$status, $error, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(string $apiKey, int $limit = 8): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM payments WHERE api_key = ? ORDER BY created_at DESC, rowid DESC LIMIT ?',
        );
        $statement->bindValue(1, $apiKey);
        $statement->bindValue(2, $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function toArray(array $row, string $baseUrl): array
    {
        return [
            'id' => $row['id'],
            'status' => $row['status'],
            'amount' => (int) $row['amount'],
            'currency' => $row['currency'],
            'phone' => $row['phone'],
            'description' => $row['description'],
            'callback_url' => $row['callback_url'],
            'checkout_url' => $baseUrl.'/pay/'.$row['id'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'webhook' => [
                'status' => $row['webhook_status'] === null ? null : (int) $row['webhook_status'],
                'error' => $row['webhook_error'],
            ],
        ];
    }

    private function description(mixed $value): ?string
    {
        $description = str_value($value);

        return $description === null ? null : mb_substr($description, 0, 200);
    }

    private function prune(): void
    {
        $limit = gmdate('c', time() - 7 * 86400);

        $this->pdo->prepare('DELETE FROM payments WHERE created_at < ?')->execute([$limit]);
    }
}
