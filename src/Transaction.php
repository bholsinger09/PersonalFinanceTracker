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
    public static function create($userId, float $amount, string $description, ?string $category = null, string $type = 'expense'): bool
    {
        // Check if type column exists
        if (self::hasTypeColumn()) {
            $sql = 'INSERT INTO transactions (user_id, amount, description, category, type, date) VALUES (?, ?, ?, ?, ?, datetime("now"))';
            $result = Database::execute($sql, [$userId, $amount, $description, $category, $type]);
        } else {
            // Fallback for tables without type column
            $sql = 'INSERT INTO transactions (user_id, amount, description, category, date) VALUES (?, ?, ?, ?, datetime("now"))';
            $result = Database::execute($sql, [$userId, $amount, $description, $category]);
        }
        return $result > 0;
    }

    /**
     * Get current balance for a user
     */
    public static function getCurrentBalance($userId): float
    {
        // Get starting balance
        $startingBalance = self::getStartingBalance($userId);
        
        // Calculate balance from transactions
        if (self::hasTypeColumn()) {
            $sql = "
                SELECT 
                    SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END) as balance_change
                FROM transactions 
                WHERE user_id = ?
            ";
        } else {
            // Fallback: assume all transactions are expenses if no type column
            $sql = "
                SELECT 
                    SUM(-amount) as balance_change
                FROM transactions 
                WHERE user_id = ?
            ";
        }
        $result = Database::query($sql, [$userId]);
        $transactionBalance = $result[0]['balance_change'] ?? 0.0;
        
        return $startingBalance + $transactionBalance;
    }

    /**
     * Set starting balance for a user
     */
    public static function setStartingBalance($userId, float $amount): bool
    {
        // Delete existing starting balance
        $sql = "DELETE FROM starting_balance WHERE user_id = ?";
        Database::execute($sql, [$userId]);
        
        // Insert new starting balance
        $sql = "INSERT INTO starting_balance (user_id, amount) VALUES (?, ?)";
        $result = Database::execute($sql, [$userId, $amount]);
        return $result > 0;
    }

    /**
     * Get starting balance for a user
     */
    public static function getStartingBalance($userId): float
    {
        $sql = "SELECT amount FROM starting_balance WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
        $result = Database::query($sql, [$userId]);
        return $result[0]['amount'] ?? 0.0;
    }

    /**
     * Get all transactions with optional filtering
     */
    public static function getAllWithFilter($userId, ?string $type = null, ?string $category = null): array
    {
        $sql = "SELECT * FROM transactions WHERE user_id = ?";
        $params = [$userId];
        
        if ($type && in_array($type, ['expense', 'deposit']) && self::hasTypeColumn()) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        // Check if date column exists, otherwise use created_at
        if (self::hasDateColumn()) {
            $sql .= " ORDER BY date DESC";
        } else {
            $sql .= " ORDER BY created_at DESC";
        }
        
        return Database::query($sql, $params);
    }

    /**
     * Check if the date column exists in the transactions table
     */
    private static function hasDateColumn(): bool
    {
        static $hasDate = null;
        
        if ($hasDate === null) {
            try {
                $result = Database::query("PRAGMA table_info(transactions)");
                $hasDate = false;
                foreach ($result as $column) {
                    if ($column['name'] === 'date') {
                        $hasDate = true;
                        break;
                    }
                }
            } catch (PDOException $e) {
                $hasDate = false;
            }
        }
        
        return $hasDate;
    }

    /**
     * Check if the type column exists in the transactions table
     */
    private static function hasTypeColumn(): bool
    {
        static $hasType = null;
        
        if ($hasType === null) {
            try {
                $result = Database::query("PRAGMA table_info(transactions)");
                $hasType = false;
                foreach ($result as $column) {
                    if ($column['name'] === 'type') {
                        $hasType = true;
                        break;
                    }
                }
            } catch (PDOException $e) {
                $hasType = false;
            }
        }
        
        return $hasType;
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
    public function update(float $amount, string $description, ?string $category = null, string $type = 'expense'): bool
    {
        if (self::hasTypeColumn()) {
            $sql = 'UPDATE transactions SET amount = ?, description = ?, category = ?, type = ? WHERE id = ?';
            $affected = Database::execute($sql, [$amount, $description, $category, $type, $this->id]);
        } else {
            $sql = 'UPDATE transactions SET amount = ?, description = ?, category = ? WHERE id = ?';
            $affected = Database::execute($sql, [$amount, $description, $category, $this->id]);
        }

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

    /**
     * Static method to update a transaction by ID and user ID
     */
    public static function updateById(int $id, $userId, float $amount, string $description, ?string $category = null, string $type = 'expense'): bool
    {
        if (self::hasTypeColumn()) {
            $sql = "UPDATE transactions SET amount = ?, description = ?, category = ?, type = ? WHERE id = ? AND user_id = ?";
            return Database::execute($sql, [$amount, $description, $category, $type, $id, $userId]) > 0;
        } else {
            $sql = "UPDATE transactions SET amount = ?, description = ?, category = ? WHERE id = ? AND user_id = ?";
            return Database::execute($sql, [$amount, $description, $category, $id, $userId]) > 0;
        }
    }

    /**
     * Static method to delete a transaction by ID and user ID
     */
    public static function deleteById(int $id, $userId): bool
    {
        $sql = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
        return Database::execute($sql, [$id, $userId]) > 0;
    }

    /**
     * Get a transaction by ID and user ID (for security)
     */
    public static function getByIdAndUserId(int $id, $userId): ?array
    {
        $sql = "SELECT * FROM transactions WHERE id = ? AND user_id = ?";
        $result = Database::query($sql, [$id, $userId]);
        return $result[0] ?? null;
    }
}
