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
                // If schema initialization fails, try to recover with basic setup
                error_log("Schema initialization failed, attempting recovery: " . $e->getMessage());
                try {
                    self::initializeBasicSchema();
                } catch (PDOException $recoveryError) {
                    throw new PDOException('Database connection failed: ' . $e->getMessage());
                }
            }
        }

        return self::$pdo;
    }

    /**
     * Initialize database schema if not exists
     */
    private static function initializeSchema(): void
    {
        try {
            // First run migrations on existing tables to ensure compatibility
            self::runMigrations();
            
            // Then run schema.sql to create any missing tables and indexes
            $schemaPath = __DIR__ . '/../database/schema.sql';
            $safeSchemaPath = __DIR__ . '/../database/schema_safe.sql';
            
            // In production environments with existing databases, use safe schema
            // In dev/test environments or new databases, use full schema
            $useSchema = $schemaPath; // Default to full schema
            
            if (file_exists(self::$dbPath) && filesize(self::$dbPath) > 0) {
                // Database exists and has content, check if it's missing columns
                try {
                    $result = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='transactions'");
                    if ($result && $result->fetch()) {
                        // Table exists, use safe schema to avoid conflicts
                        if (file_exists($safeSchemaPath)) {
                            $useSchema = $safeSchemaPath;
                        }
                    }
                } catch (PDOException $e) {
                    // If we can't check, use safe schema as fallback
                    if (file_exists($safeSchemaPath)) {
                        $useSchema = $safeSchemaPath;
                    }
                }
            }
            
            if (file_exists($useSchema)) {
                try {
                    $schema = file_get_contents($useSchema);
                    self::$pdo->exec($schema);
                } catch (PDOException $e) {
                    // If schema execution fails, try to run it piece by piece
                    error_log("Schema execution failed, attempting individual statements: " . $e->getMessage());
                    self::executeSchemaStatements($schema);
                }
            } else {
                // Fallback: Create tables directly if schema.sql doesn't exist
                self::createDefaultSchema();
            }
            
            // After migrations and schema, create indexes that depend on migrated columns
            self::createPostMigrationIndexes();
            
        } catch (PDOException $e) {
            // If everything fails, use basic recovery schema
            error_log("Complete schema initialization failed, using basic recovery: " . $e->getMessage());
            self::initializeBasicSchema();
        }
    }

    /**
     * Execute schema statements individually to handle errors gracefully
     */
    private static function executeSchemaStatements(string $schema): void
    {
        // Split into individual statements
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue; // Skip empty lines and comments
            }
            
            try {
                self::$pdo->exec($statement . ';');
            } catch (PDOException $e) {
                // Log the error but continue with other statements
                error_log("Schema statement failed (continuing): " . $e->getMessage() . " - Statement: " . substr($statement, 0, 100));
            }
        }
    }

    /**
     * Create indexes that depend on columns added by migrations
     */
    private static function createPostMigrationIndexes(): void
    {
        try {
            // Create date index if date column exists
            $result = self::$pdo->query("PRAGMA table_info(transactions)");
            $columns = $result->fetchAll();
            
            $hasDateColumn = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'date') {
                    $hasDateColumn = true;
                    break;
                }
            }
            
            if ($hasDateColumn) {
                self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(date)");
            }
            
        } catch (PDOException $e) {
            error_log("Post-migration index creation warning: " . $e->getMessage());
        }
    }

    /**
     * Initialize basic schema for recovery when main schema fails
     */
    private static function initializeBasicSchema(): void
    {
        // Create only essential tables without any indexes that might fail
        $basicTables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                google_id TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                picture TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS starting_balance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'expense' CHECK (type IN ('expense', 'deposit')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                color TEXT DEFAULT '#007bff',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                UNIQUE(user_id, name)
            )"
        ];
        
        foreach ($basicTables as $table) {
            try {
                self::$pdo->exec($table);
            } catch (PDOException $e) {
                error_log("Basic table creation failed: " . $e->getMessage());
            }
        }
        
        // Now run migrations to add missing columns
        self::runMigrations();
        
        // Finally create indexes that are safe
        self::createPostMigrationIndexes();
    }

    /**
     * Run database migrations to update existing schemas
     */
    private static function runMigrations(): void
    {
        try {
            // First check if we can even query the database
            $tables = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
            
            // Check if transactions table exists
            $transactionsExists = false;
            foreach ($tables as $table) {
                if ($table['name'] === 'transactions') {
                    $transactionsExists = true;
                    break;
                }
            }
            
            if (!$transactionsExists) {
                // Table doesn't exist yet, skip migrations (schema will create it)
                return;
            }
            
            // Check if transactions table has date column
            $result = self::$pdo->query("PRAGMA table_info(transactions)");
            $columns = $result->fetchAll();
            
            $hasDateColumn = false;
            $hasCategoryColumn = false;
            $hasTypeColumn = false;
            
            foreach ($columns as $column) {
                if ($column['name'] === 'date') {
                    $hasDateColumn = true;
                }
                if ($column['name'] === 'category') {
                    $hasCategoryColumn = true;
                }
                if ($column['name'] === 'type') {
                    $hasTypeColumn = true;
                }
            }
            
            // Add type column if missing
            if (!$hasTypeColumn) {
                self::$pdo->exec("ALTER TABLE transactions ADD COLUMN type TEXT NOT NULL DEFAULT 'expense'");
                error_log("Database migration: Added type column to transactions table");
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
