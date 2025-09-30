<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\Transaction;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private string $testDbPath;

    protected function setUp(): void
    {
        // Use a temporary database for testing
        $this->testDbPath = sys_get_temp_dir() . '/test_finance_tracker.db';

        // Override the database path for testing
        $reflection = new \ReflectionClass(Database::class);
        $dbPathProperty = $reflection->getProperty('dbPath');
        $dbPathProperty->setAccessible(true);
        $dbPathProperty->setValue(null, $this->testDbPath);

        // Reset the PDO connection
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Clean up test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }

        // Reset database path to original
        $reflection = new \ReflectionClass(Database::class);
        $dbPathProperty = $reflection->getProperty('dbPath');
        $dbPathProperty->setAccessible(true);
        $dbPathProperty->setValue(null, __DIR__ . '/../database/finance_tracker.db');

        // Reset PDO connection
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, null);
    }

    public function testDatabaseConnection(): void
    {
        $pdo = Database::getConnection();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testDatabaseQueryExecution(): void
    {
        // Test basic query execution
        $result = Database::query('SELECT 1 as test');
        $this->assertIsArray($result);
        $this->assertEquals(1, $result[0]['test']);
    }

    public function testDatabaseExecute(): void
    {
        // Test execute method
        $affected = Database::execute('CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT)');
        $this->assertGreaterThanOrEqual(0, $affected);

        // Insert and check
        $affected = Database::execute('INSERT INTO test_table (name) VALUES (?)', ['test']);
        $this->assertEquals(1, $affected);

        $result = Database::query('SELECT COUNT(*) as count FROM test_table');
        $this->assertEquals(1, $result[0]['count']);
    }
}