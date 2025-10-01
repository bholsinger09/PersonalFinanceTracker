<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\Transaction;
use PHPUnit\Framework\TestCase;

class TransactionUITest extends TestCase
{
    private string $testDbPath;
    private int $testUserId = 1; // Use integer for database ID

    protected function setUp(): void
    {
        // Use a temporary database for testing
        $this->testDbPath = sys_get_temp_dir() . '/test_finance_tracker_ui.db';

        // Override the database path for testing
        $reflection = new \ReflectionClass(Database::class);
        $dbPathProperty = $reflection->getProperty('dbPath');
        $dbPathProperty->setAccessible(true);
        $dbPathProperty->setValue(null, $this->testDbPath);

        // Reset the PDO connection
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, null);

        // Initialize schema and create test user
        $pdo = Database::getConnection();
        
        // Insert a test user
        $sql = "INSERT OR REPLACE INTO users (id, google_id, email, name) VALUES (?, ?, ?, ?)";
        Database::execute($sql, [$this->testUserId, 'test123', 'test@example.com', 'Test User']);
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

    public function testTransactionCreationValidation(): void
    {
        // Test valid transaction creation - new API returns boolean
        $result = Transaction::create($this->testUserId, 100.50, 'Valid transaction', null, 'deposit');
        $this->assertTrue($result);
        
        // Verify transaction was created by fetching it
        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertNotEmpty($transactions);
        $this->assertEquals(100.50, $transactions[0]['amount']);
        $this->assertEquals('Valid transaction', $transactions[0]['description']);
        $this->assertEquals('deposit', $transactions[0]['type']);
    }

    public function testTransactionCreationWithNegativeAmount(): void
    {
        // Test negative amounts (expenses)
        $result = Transaction::create($this->testUserId, -50.25, 'Expense transaction', null, 'expense');
        $this->assertTrue($result);
        
        // Verify transaction was created
        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertNotEmpty($transactions);
        $this->assertEquals(-50.25, $transactions[0]['amount']);
    }

    public function testTransactionCreationWithZeroAmount(): void
    {
        // Test zero amount
        $result = Transaction::create($this->testUserId, 0.00, 'Zero transaction', null, 'deposit');
        $this->assertTrue($result);
        
        // Verify transaction was created
        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertNotEmpty($transactions);
        $this->assertEquals(0.00, $transactions[0]['amount']);
    }

    public function testTransactionCreationWithLargeAmount(): void
    {
        // Test large amounts
        $result = Transaction::create($this->testUserId, 999999.99, 'Large transaction', null, 'deposit');
        $this->assertTrue($result);
        
        // Verify transaction was created
        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertNotEmpty($transactions);
        $this->assertEquals(999999.99, $transactions[0]['amount']);
    }

    public function testTransactionCreationWithLongDescription(): void
    {
        // Test long descriptions
        $longDescription = str_repeat('A', 500);
        $result = Transaction::create($this->testUserId, 100.00, $longDescription, null, 'deposit');
        $this->assertTrue($result);
        
        // Verify transaction was created
        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertNotEmpty($transactions);
        $this->assertEquals($longDescription, $transactions[0]['description']);
    }

    public function testMultipleUsersHaveSeparateTransactions(): void
    {
        // Create transactions for different users - first create another test user
        $user2Id = 2;
        $sql = "INSERT OR REPLACE INTO users (id, google_id, email, name) VALUES (?, ?, ?, ?)";
        Database::execute($sql, [$user2Id, 'test456', 'user2@example.com', 'Test User 2']);

        Transaction::create($this->testUserId, 100.00, 'User 1 transaction 1', null, 'deposit');
        Transaction::create($this->testUserId, 200.00, 'User 1 transaction 2', null, 'deposit');
        Transaction::create($user2Id, 300.00, 'User 2 transaction 1', null, 'deposit');

        $user1Transactions = Transaction::getAllWithFilter($this->testUserId);
        $user2Transactions = Transaction::getAllWithFilter($user2Id);

        $this->assertCount(2, $user1Transactions);
        $this->assertCount(1, $user2Transactions);

        // Verify user isolation
        foreach ($user1Transactions as $transaction) {
            $this->assertEquals($this->testUserId, $transaction['user_id']);
        }
        foreach ($user2Transactions as $transaction) {
            $this->assertEquals($user2Id, $transaction['user_id']);
        }
    }

    public function testTransactionBalanceCalculation(): void
    {
        // Create various transactions
        Transaction::create($this->testUserId, 100.00, 'Income 1', null, 'deposit');
        Transaction::create($this->testUserId, 50.00, 'Income 2', null, 'deposit');
        Transaction::create($this->testUserId, 25.00, 'Expense 1', null, 'expense');
        Transaction::create($this->testUserId, 10.50, 'Expense 2', null, 'expense');

        $balance = Transaction::getCurrentBalance($this->testUserId);

        // Expected: 100 + 50 - 25 - 10.50 = 114.50
        $this->assertEquals(114.50, $balance);
    }

    public function testTransactionFormatting(): void
    {
        Transaction::create($this->testUserId, 1234.56, 'Test transaction', null, 'expense');

        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertNotEmpty($transactions);
        
        $transaction = $transactions[0];
        $this->assertEquals(1234.56, $transaction['amount']);
        $this->assertIsString($transaction['created_at']);
        $this->assertGreaterThan(0, strlen($transaction['created_at']));
    }
}