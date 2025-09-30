<?php

declare(strict_types=1);

namespace FinanceTracker;

use DateTime;

/**
 * Transaction model for managing financial transactions
 */
class Transaction
{
    private int $id;
    private string $userId;
    private float $amount;
    private string $description;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        int $id,
        string $userId,
        float $amount,
        string $description,
        DateTime $createdAt,
        DateTime $updatedAt
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->amount = $amount;
        $this->description = $description;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Create a new transaction
     */
    public static function create(string $userId, float $amount, string $description): self
    {
        $sql = 'INSERT INTO transactions (user_id, amount, description) VALUES (?, ?, ?)';
        Database::execute($sql, [$userId, $amount, $description]);

        $id = (int) Database::lastInsertId();
        $now = new DateTime();

        return new self($id, $userId, $amount, $description, $now, $now);
    }

    /**
     * Find transaction by ID
     */
    public static function find(int $id): ?self
    {
        $sql = 'SELECT * FROM transactions WHERE id = ?';
        $result = Database::query($sql, [$id]);

        if (empty($result)) {
            return null;
        }

        $row = $result[0];
        return self::fromArray($row);
    }

    /**
     * Get all transactions for a user
     */
    public static function findByUserId(string $userId): array
    {
        $sql = 'SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC';
        $results = Database::query($sql, [$userId]);

        $transactions = [];
        foreach ($results as $row) {
            $transactions[] = self::fromArray($row);
        }

        return $transactions;
    }

    /**
     * Update transaction
     */
    public function update(float $amount, string $description): bool
    {
        $sql = 'UPDATE transactions SET amount = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        $affected = Database::execute($sql, [$amount, $description, $this->id]);

        if ($affected > 0) {
            $this->amount = $amount;
            $this->description = $description;
            $this->updatedAt = new DateTime();
            return true;
        }

        return false;
    }

    /**
     * Delete transaction
     */
    public function delete(): bool
    {
        $sql = 'DELETE FROM transactions WHERE id = ?';
        $affected = Database::execute($sql, [$this->id]);
        return $affected > 0;
    }

    /**
     * Create Transaction from database row
     */
    private static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['user_id'],
            (float) $row['amount'],
            $row['description'],
            new DateTime($row['created_at']),
            new DateTime($row['updated_at'])
        );
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmount(): string
    {
        $formatted = '$' . number_format(abs($this->amount), 2);
        return $this->amount < 0 ? "($formatted)" : $formatted;
    }

    /**
     * Get formatted created date
     */
    public function getFormattedCreatedAt(): string
    {
        return $this->createdAt->format('M j, Y g:i A');
    }
}
