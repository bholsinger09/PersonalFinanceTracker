<?php

declare(strict_types=1);

namespace FinanceTracker;

use PDO;
use PDOException;

/**
 * Database connection and query management class
 */
class Database
{
    private static ?PDO $pdo = null;
    private static string $dbPath;

    /**
     * Initialize database path from environment or default
     */
    private static function initializeDbPath(): void
    {
        if (!isset(self::$dbPath)) {
            // Check for DATABASE_URL (Heroku style)
            $databaseUrl = getenv('DATABASE_URL');
            if ($databaseUrl) {
                // Parse DATABASE_URL for SQLite
                $parsedUrl = parse_url($databaseUrl);
                if ($parsedUrl && isset($parsedUrl['path'])) {
                    self::$dbPath = $parsedUrl['path'];
                } else {
                    self::$dbPath = $databaseUrl;
                }
            } else {
                // Default local path
                self::$dbPath = __DIR__ . '/../database/finance_tracker.db';
            }
        }
    }

    /**
     * Get database connection
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            self::initializeDbPath();
            try {
                self::$pdo = new PDO('sqlite:' . self::$dbPath);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Initialize database schema
                self::initializeSchema();
            } catch (PDOException $e) {
                throw new PDOException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$pdo;
    }

    /**
     * Initialize database schema if not exists
     */
    private static function initializeSchema(): void
    {
        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaPath)) {
            $schema = file_get_contents($schemaPath);
            self::$pdo->exec($schema);
        } else {
            // Fallback: Create tables directly if schema.sql doesn't exist
            self::createDefaultSchema();
        }
        
        // Run migrations to handle schema updates for existing databases
        self::runMigrations();
    }

    /**
     * Run database migrations to update existing schemas
     */
    private static function runMigrations(): void
    {
        try {
            // Check if transactions table has date column
            $result = self::$pdo->query("PRAGMA table_info(transactions)");
            $columns = $result->fetchAll();
            
            $hasDateColumn = false;
            $hasCategoryColumn = false;
            
            foreach ($columns as $column) {
                if ($column['name'] === 'date') {
                    $hasDateColumn = true;
                }
                if ($column['name'] === 'category') {
                    $hasCategoryColumn = true;
                }
            }
            
            // Add date column if missing
            if (!$hasDateColumn) {
                self::$pdo->exec("ALTER TABLE transactions ADD COLUMN date DATETIME DEFAULT CURRENT_TIMESTAMP");
                error_log("Database migration: Added date column to transactions table");
            }
            
            // Add category column if missing
            if (!$hasCategoryColumn) {
                self::$pdo->exec("ALTER TABLE transactions ADD COLUMN category TEXT");
                error_log("Database migration: Added category column to transactions table");
            }
            
            // Update existing transactions that might not have date set
            $updateCount = self::$pdo->exec("UPDATE transactions SET date = created_at WHERE date IS NULL");
            if ($updateCount > 0) {
                error_log("Database migration: Updated $updateCount transactions with date from created_at");
            }
            
        } catch (PDOException $e) {
            error_log("Database migration warning: " . $e->getMessage());
            // Don't throw - migrations are best effort for existing databases
        }
    }

    /**
     * Create default database schema
     */
    private static function createDefaultSchema(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                google_id TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                picture TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS starting_balance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT NOT NULL,
                category TEXT,
                type TEXT NOT NULL DEFAULT 'expense' CHECK (type IN ('expense', 'deposit')),
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                color TEXT DEFAULT '#007bff',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE(user_id, name)
            );
        ";
        
        self::$pdo->exec($sql);
    }

    /**
     * Execute a query with parameters
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query that doesn't return results
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get last inserted ID
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }
}
