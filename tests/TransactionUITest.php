<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\Transaction;
use PHPUnit\Framework\TestCase;

class TransactionUITest extends TestCase
{
    private string $testDbPath;
    private string $testUserId = 'test_user@example.com';

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

        // Initialize schema
        Database::getConnection();
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
        // Test valid transaction creation
        $transaction = Transaction::create($this->testUserId, 100.50, 'Valid transaction');
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(100.50, $transaction->getAmount());
        $this->assertEquals('Valid transaction', $transaction->getDescription());
    }

    public function testTransactionCreationWithNegativeAmount(): void
    {
        // Test negative amounts (expenses)
        $transaction = Transaction::create($this->testUserId, -50.25, 'Expense transaction');
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(-50.25, $transaction->getAmount());
    }

    public function testTransactionCreationWithZeroAmount(): void
    {
        // Test zero amount
        $transaction = Transaction::create($this->testUserId, 0.00, 'Zero transaction');
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(0.00, $transaction->getAmount());
    }

    public function testTransactionCreationWithLargeAmount(): void
    {
        // Test large amounts
        $transaction = Transaction::create($this->testUserId, 999999.99, 'Large transaction');
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(999999.99, $transaction->getAmount());
    }

    public function testTransactionCreationWithLongDescription(): void
    {
        // Test long descriptions
        $longDescription = str_repeat('A', 500);
        $transaction = Transaction::create($this->testUserId, 100.00, $longDescription);
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($longDescription, $transaction->getDescription());
    }

    public function testMultipleUsersHaveSeparateTransactions(): void
    {
        // Create transactions for different users
        $user1 = 'user1@example.com';
        $user2 = 'user2@example.com';

        Transaction::create($user1, 100.00, 'User 1 transaction 1');
        Transaction::create($user1, 200.00, 'User 1 transaction 2');
        Transaction::create($user2, 300.00, 'User 2 transaction 1');

        $user1Transactions = Transaction::findByUserId($user1);
        $user2Transactions = Transaction::findByUserId($user2);

        $this->assertCount(2, $user1Transactions);
        $this->assertCount(1, $user2Transactions);

        // Verify user isolation
        foreach ($user1Transactions as $transaction) {
            $this->assertEquals($user1, $transaction->getUserId());
        }
        foreach ($user2Transactions as $transaction) {
            $this->assertEquals($user2, $transaction->getUserId());
        }
    }

    public function testTransactionBalanceCalculation(): void
    {
        // Create various transactions
        Transaction::create($this->testUserId, 100.00, 'Income 1');
        Transaction::create($this->testUserId, 50.00, 'Income 2');
        Transaction::create($this->testUserId, -25.00, 'Expense 1');
        Transaction::create($this->testUserId, -10.50, 'Expense 2');

        $transactions = Transaction::findByUserId($this->testUserId);

        $balance = 0.0;
        foreach ($transactions as $transaction) {
            $balance += $transaction->getAmount();
        }

        // Expected: 100 + 50 - 25 - 10.50 = 114.50
        $this->assertEquals(114.50, $balance);
    }

    public function testTransactionFormatting(): void
    {
        $transaction = Transaction::create($this->testUserId, -1234.56, 'Test transaction');

        // Test formatted amount
        $this->assertEquals('($1,234.56)', $transaction->getFormattedAmount());

        // Test that created date is formatted
        $formattedDate = $transaction->getFormattedCreatedAt();
        $this->assertIsString($formattedDate);
        $this->assertGreaterThan(0, strlen($formattedDate));
    }
}