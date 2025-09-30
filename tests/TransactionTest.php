<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\Transaction;
use DateTime;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    private string $testDbPath;
    private string $testUserId = 'test_user@example.com';

    protected function setUp(): void
    {
        // Use a temporary database for testing
        $this->testDbPath = sys_get_temp_dir() . '/test_finance_tracker_transactions.db';

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

    public function testCreateTransaction(): void
    {
        $amount = 100.50;
        $description = 'Test transaction';

        $transaction = Transaction::create($this->testUserId, $amount, $description);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($this->testUserId, $transaction->getUserId());
        $this->assertEquals($amount, $transaction->getAmount());
        $this->assertEquals($description, $transaction->getDescription());
        $this->assertGreaterThan(0, $transaction->getId());
    }

    public function testFindTransactionById(): void
    {
        // Create a transaction first
        $transaction = Transaction::create($this->testUserId, 50.00, 'Find test');

        // Find it by ID
        $found = Transaction::find($transaction->getId());

        $this->assertNotNull($found);
        $this->assertEquals($transaction->getId(), $found->getId());
        $this->assertEquals($transaction->getUserId(), $found->getUserId());
        $this->assertEquals($transaction->getAmount(), $found->getAmount());
        $this->assertEquals($transaction->getDescription(), $found->getDescription());
    }

    public function testFindNonExistentTransaction(): void
    {
        $found = Transaction::find(99999);
        $this->assertNull($found);
    }

    public function testFindTransactionsByUserId(): void
    {
        // Create multiple transactions for the same user with small delays
        Transaction::create($this->testUserId, 25.00, 'Transaction 1');
        usleep(1000); // 1ms delay
        Transaction::create($this->testUserId, -10.00, 'Transaction 2');
        usleep(1000); // 1ms delay
        Transaction::create($this->testUserId, 75.50, 'Transaction 3');

        // Create transaction for different user
        Transaction::create('other_user@example.com', 100.00, 'Other user transaction');

        $transactions = Transaction::findByUserId($this->testUserId);

        $this->assertCount(3, $transactions);

        // Check that all transactions belong to the correct user
        foreach ($transactions as $transaction) {
            $this->assertEquals($this->testUserId, $transaction->getUserId());
        }

        // Check that we have all expected descriptions (order may vary due to timestamp precision)
        $descriptions = array_map(fn($t) => $t->getDescription(), $transactions);
        $this->assertContains('Transaction 1', $descriptions);
        $this->assertContains('Transaction 2', $descriptions);
        $this->assertContains('Transaction 3', $descriptions);
    }

    public function testUpdateTransaction(): void
    {
        // Create a transaction
        $transaction = Transaction::create($this->testUserId, 100.00, 'Original description');

        // Update it
        $newAmount = 150.00;
        $newDescription = 'Updated description';
        $result = $transaction->update($newAmount, $newDescription);

        $this->assertTrue($result);
        $this->assertEquals($newAmount, $transaction->getAmount());
        $this->assertEquals($newDescription, $transaction->getDescription());

        // Verify in database
        $found = Transaction::find($transaction->getId());
        $this->assertNotNull($found);
        $this->assertEquals($newAmount, $found->getAmount());
        $this->assertEquals($newDescription, $found->getDescription());
    }

    public function testDeleteTransaction(): void
    {
        // Create a transaction
        $transaction = Transaction::create($this->testUserId, 50.00, 'To be deleted');

        // Delete it
        $result = $transaction->delete();
        $this->assertTrue($result);

        // Verify it's gone
        $found = Transaction::find($transaction->getId());
        $this->assertNull($found);
    }

    public function testFormattedAmount(): void
    {
        $transaction = Transaction::create($this->testUserId, 1234.56, 'Test');
        $this->assertEquals('$1,234.56', $transaction->getFormattedAmount());
    }

    public function testFormattedCreatedAt(): void
    {
        $transaction = Transaction::create($this->testUserId, 100.00, 'Test');
        $formatted = $transaction->getFormattedCreatedAt();

        // Should be in format like "Sep 30, 2025 2:30 PM"
        $this->assertMatchesRegularExpression('/^[A-Za-z]{3} \d{1,2}, \d{4} \d{1,2}:\d{2} (AM|PM)$/', $formatted);
    }
}