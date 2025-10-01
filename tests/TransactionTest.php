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
    private int $testUserId = 1; // Use integer for database ID

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

        // Initialize schema - this will use the fallback since schema.sql doesn't exist in temp
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

    public function testCreateTransaction(): void
    {
        $amount = 100.50;
        $description = 'Test transaction';
        $category = 'Food';
        $type = 'expense';

        $result = Transaction::create($this->testUserId, $amount, $description, $category, $type);

        $this->assertTrue($result);
        
        // Verify transaction was created by checking the database
        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertCount(1, $transactions);
        $this->assertEquals($amount, $transactions[0]['amount']);
        $this->assertEquals($description, $transactions[0]['description']);
        $this->assertEquals($category, $transactions[0]['category']);
        $this->assertEquals($type, $transactions[0]['type']);
    }

    public function testCreateDeposit(): void
    {
        $amount = 500.00;
        $description = 'Test deposit';
        $type = 'deposit';

        $result = Transaction::create($this->testUserId, $amount, $description, null, $type);

        $this->assertTrue($result);
        
        // Verify deposit was created
        $transactions = Transaction::getAllWithFilter($this->testUserId, 'deposit');
        $this->assertCount(1, $transactions);
        $this->assertEquals($type, $transactions[0]['type']);
    }

    public function testGetCurrentBalanceWithNoTransactions(): void
    {
        $balance = Transaction::getCurrentBalance($this->testUserId);
        $this->assertEquals(0.0, $balance);
    }

    public function testSetAndGetStartingBalance(): void
    {
        $startingAmount = 1000.00;
        
        $result = Transaction::setStartingBalance($this->testUserId, $startingAmount);
        $this->assertTrue($result);
        
        $retrievedBalance = Transaction::getStartingBalance($this->testUserId);
        $this->assertEquals($startingAmount, $retrievedBalance);
    }

    public function testBalanceCalculationWithTransactions(): void
    {
        // Set starting balance
        Transaction::setStartingBalance($this->testUserId, 1000.00);
        
        // Add some transactions
        Transaction::create($this->testUserId, 100.00, 'Expense 1', 'Food', 'expense');
        Transaction::create($this->testUserId, 50.00, 'Expense 2', 'Gas', 'expense');
        Transaction::create($this->testUserId, 200.00, 'Deposit 1', 'Salary', 'deposit');
        
        // Calculate expected balance: 1000 - 100 - 50 + 200 = 1050
        $expectedBalance = 1050.00;
        $actualBalance = Transaction::getCurrentBalance($this->testUserId);
        
        $this->assertEquals($expectedBalance, $actualBalance);
    }

    public function testFilterTransactionsByType(): void
    {
        // Create mixed transactions
        Transaction::create($this->testUserId, 100.00, 'Expense 1', 'Food', 'expense');
        Transaction::create($this->testUserId, 200.00, 'Deposit 1', 'Salary', 'deposit');
        Transaction::create($this->testUserId, 50.00, 'Expense 2', 'Gas', 'expense');
        
        // Test filter by expenses
        $expenses = Transaction::getAllWithFilter($this->testUserId, 'expense');
        $this->assertCount(2, $expenses);
        
        // Test filter by deposits
        $deposits = Transaction::getAllWithFilter($this->testUserId, 'deposit');
        $this->assertCount(1, $deposits);
        
        // Test all transactions
        $allTransactions = Transaction::getAllWithFilter($this->testUserId);
        $this->assertCount(3, $allTransactions);
    }

    public function testTransactionDataIntegrity(): void
    {
        $amount = 125.75;
        $description = 'Test transaction with special chars: åäö';
        $category = 'Entertainment & Fun';
        $type = 'expense';

        $result = Transaction::create($this->testUserId, $amount, $description, $category, $type);
        $this->assertTrue($result);

        $transactions = Transaction::getAllWithFilter($this->testUserId);
        $transaction = $transactions[0];

        $this->assertEquals($amount, $transaction['amount']);
        $this->assertEquals($description, $transaction['description']);
        $this->assertEquals($category, $transaction['category']);
        $this->assertEquals($type, $transaction['type']);
    }

    public function testMultipleUsersHaveSeparateData(): void
    {
        // Create second user
        $secondUserId = 2;
        $sql = "INSERT OR REPLACE INTO users (id, google_id, email, name) VALUES (?, ?, ?, ?)";
        Database::execute($sql, [$secondUserId, 'test456', 'test2@example.com', 'Test User 2']);

        // Set different starting balances
        Transaction::setStartingBalance($this->testUserId, 1000.00);
        Transaction::setStartingBalance($secondUserId, 500.00);

        // Add transactions for both users
        Transaction::create($this->testUserId, 100.00, 'User 1 expense', 'Food', 'expense');
        Transaction::create($secondUserId, 50.00, 'User 2 expense', 'Gas', 'expense');

        // Verify balances are separate
        $balance1 = Transaction::getCurrentBalance($this->testUserId);
        $balance2 = Transaction::getCurrentBalance($secondUserId);

        $this->assertEquals(900.00, $balance1); // 1000 - 100
        $this->assertEquals(450.00, $balance2); // 500 - 50

        // Verify transactions are separate
        $transactions1 = Transaction::getAllWithFilter($this->testUserId);
        $transactions2 = Transaction::getAllWithFilter($secondUserId);

        $this->assertCount(1, $transactions1);
        $this->assertCount(1, $transactions2);
        $this->assertEquals('User 1 expense', $transactions1[0]['description']);
        $this->assertEquals('User 2 expense', $transactions2[0]['description']);
    }
}