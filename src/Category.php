<?php

declare(strict_types=1);

namespace FinanceTracker;

/**
 * Category model for managing transaction categories
 */
class Category
{
    /**
     * Default categories with colors for new users
     */
    private static array $defaultCategories = [
        // Expense categories
        'Food & Dining' => '#e74c3c',
        'Groceries' => '#27ae60',
        'Transportation' => '#3498db',
        'Gas' => '#f39c12',
        'Entertainment' => '#9b59b6',
        'Shopping' => '#e67e22',
        'Bills & Utilities' => '#34495e',
        'Healthcare' => '#1abc9c',
        'Education' => '#2ecc71',
        'Travel' => '#8e44ad',
        'Subscriptions' => '#95a5a6',
        'Other Expenses' => '#7f8c8d',
        
        // Income categories
        'Salary' => '#16a085',
        'Freelance' => '#27ae60',
        'Investment' => '#2980b9',
        'Gift' => '#e91e63',
        'Refund' => '#607d8b',
        'Other Income' => '#4caf50'
    ];

    /**
     * Create default categories for a new user
     */
    public static function createDefaultCategories(int $userId): bool
    {
        $sql = "INSERT OR IGNORE INTO categories (user_id, name, color) VALUES (?, ?, ?)";
        
        foreach (self::$defaultCategories as $name => $color) {
            Database::execute($sql, [$userId, $name, $color]);
        }
        
        return true;
    }

    /**
     * Get all categories for a user
     */
    public static function getByUserId(int $userId): array
    {
        $sql = "SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC";
        return Database::query($sql, [$userId]);
    }

    /**
     * Create a new category
     */
    public static function create(int $userId, string $name, string $color = '#007bff'): bool
    {
        $sql = "INSERT INTO categories (user_id, name, color) VALUES (?, ?, ?)";
        return Database::execute($sql, [$userId, $name, $color]) > 0;
    }

    /**
     * Update a category
     */
    public static function update(int $id, int $userId, string $name, string $color): bool
    {
        $sql = "UPDATE categories SET name = ?, color = ? WHERE id = ? AND user_id = ?";
        return Database::execute($sql, [$name, $color, $id, $userId]) > 0;
    }

    /**
     * Delete a category
     */
    public static function delete(int $id, int $userId): bool
    {
        // First, update any transactions using this category to null
        $sql = "UPDATE transactions SET category = NULL WHERE category = (SELECT name FROM categories WHERE id = ? AND user_id = ?)";
        Database::execute($sql, [$id, $userId]);
        
        // Then delete the category
        $sql = "DELETE FROM categories WHERE id = ? AND user_id = ?";
        return Database::execute($sql, [$id, $userId]) > 0;
    }

    /**
     * Get categories grouped by type (expense/income)
     */
    public static function getGroupedByType(int $userId): array
    {
        $categories = self::getByUserId($userId);
        
        $expenseCategories = [];
        $incomeCategories = [];
        
        // Define income category patterns
        $incomePatterns = ['salary', 'freelance', 'investment', 'gift', 'refund', 'income'];
        
        foreach ($categories as $category) {
            $isIncome = false;
            foreach ($incomePatterns as $pattern) {
                if (stripos($category['name'], $pattern) !== false) {
                    $isIncome = true;
                    break;
                }
            }
            
            if ($isIncome) {
                $incomeCategories[] = $category;
            } else {
                $expenseCategories[] = $category;
            }
        }
        
        return [
            'expense' => $expenseCategories,
            'income' => $incomeCategories
        ];
    }

    /**
     * Get spending by category for a user
     */
    public static function getSpendingByCategory(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "
            SELECT 
                category,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                type
            FROM transactions 
            WHERE user_id = ? AND category IS NOT NULL
        ";
        
        $params = [$userId];
        
        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY category, type ORDER BY total_amount DESC";
        
        return Database::query($sql, $params);
    }
}