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
    private static string $dbPath = __DIR__ . '/../database/finance_tracker.db';

    /**
     * Get database connection
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
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
        }
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
