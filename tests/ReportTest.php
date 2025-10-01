<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\Transaction;
use FinanceTracker\Report;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    private string $testDbPath;
    private int $testUserId = 888; // Use a unique ID for reports testing

    protected function setUp(): void
    {
        // Use a temporary database for testing
        $this->testDbPath = sys_get_temp_dir() . '/test_finance_tracker_reports.db';

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
        Database::execute($sql, [$this->testUserId, 'testreport123', 'testreport@example.com', 'Test Report User']);
        
        // Create some test transactions for January 2024
        Transaction::create($this->testUserId, 5000.00, 'January Salary', 'Salary', 'deposit');
        Transaction::create($this->testUserId, 1200.00, 'Rent Payment', 'Bills & Utilities', 'expense');
        Transaction::create($this->testUserId, 300.00, 'Groceries', 'Food & Dining', 'expense');
        Transaction::create($this->testUserId, 150.00, 'Gas', 'Transportation', 'expense');
        Transaction::create($this->testUserId, 100.00, 'Entertainment', 'Entertainment', 'expense');
        
        // Update transaction dates to January 2024
        $sql = "UPDATE transactions SET date = '2024-01-15' WHERE user_id = ?";
        Database::execute($sql, [$this->testUserId]);
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

    public function testGetMonthlySummary(): void
    {
        $summary = Report::getMonthlySummary($this->testUserId, '2024', '01');
        
        $this->assertEquals('2024', $summary['year']);
        $this->assertEquals('01', $summary['month']);
        $this->assertEquals('January', $summary['month_name']);
        $this->assertEquals('January 2024', $summary['period']);
        
        // Check financial calculations
        $this->assertEquals(5000.00, $summary['total_income']);
        $this->assertEquals(1750.00, $summary['total_expenses']); // 1200 + 300 + 150 + 100
        $this->assertEquals(3250.00, $summary['net_change']); // 5000 - 1750
        
        // Check transaction counts
        $this->assertEquals(1, $summary['income_transactions']);
        $this->assertEquals(4, $summary['expense_transactions']);
        $this->assertEquals(5, $summary['total_transactions']);
    }

    public function testGetMonthlySpendingByCategory(): void
    {
        $categorySpending = Report::getMonthlySpendingByCategory($this->testUserId, '2024', '01');
        
        $this->assertNotEmpty($categorySpending);
        
        // Should have 5 entries (1 income + 4 expense categories)
        $this->assertEquals(5, count($categorySpending));
        
        // Check that amounts are correct
        $categories = [];
        foreach ($categorySpending as $item) {
            $categories[$item['category']] = $item;
        }
        
        $this->assertEquals(5000.00, $categories['Salary']['total_amount']);
        $this->assertEquals(1200.00, $categories['Bills & Utilities']['total_amount']);
        $this->assertEquals(300.00, $categories['Food & Dining']['total_amount']);
        $this->assertEquals(150.00, $categories['Transportation']['total_amount']);
        $this->assertEquals(100.00, $categories['Entertainment']['total_amount']);
    }

    public function testGetYearlyOverview(): void
    {
        $overview = Report::getYearlyOverview($this->testUserId, '2024');
        
        $this->assertEquals('2024', $overview['year']);
        $this->assertArrayHasKey('months', $overview);
        $this->assertArrayHasKey('totals', $overview);
        
        // Should have 12 months
        $this->assertEquals(12, count($overview['months']));
        
        // January should have data
        $january = $overview['months'][0]; // First month
        $this->assertEquals('01', $january['month']);
        $this->assertEquals('January', $january['month_name']);
        $this->assertEquals(5000.00, $january['income']);
        $this->assertEquals(1750.00, $january['expenses']);
        $this->assertEquals(3250.00, $january['net']);
        
        // Other months should be empty
        $february = $overview['months'][1];
        $this->assertEquals(0.0, $february['income']);
        $this->assertEquals(0.0, $february['expenses']);
        
        // Check totals
        $this->assertEquals(5000.00, $overview['totals']['income']);
        $this->assertEquals(1750.00, $overview['totals']['expenses']);
        $this->assertEquals(3250.00, $overview['totals']['net']);
        $this->assertEquals(5, $overview['totals']['transactions']);
    }

    public function testGetTopSpendingCategories(): void
    {
        $topCategories = Report::getTopSpendingCategories($this->testUserId, 3);
        
        $this->assertNotEmpty($topCategories);
        $this->assertLessThanOrEqual(3, count($topCategories));
        
        // Should be ordered by amount descending
        $this->assertEquals('Bills & Utilities', $topCategories[0]['category']);
        $this->assertEquals(1200.00, $topCategories[0]['total_amount']);
        
        $this->assertEquals('Food & Dining', $topCategories[1]['category']);
        $this->assertEquals(300.00, $topCategories[1]['total_amount']);
        
        $this->assertEquals('Transportation', $topCategories[2]['category']);
        $this->assertEquals(150.00, $topCategories[2]['total_amount']);
    }

    public function testGetSpendingTrends(): void
    {
        // Add some December 2023 data for comparison
        Transaction::create($this->testUserId, 4800.00, 'December Salary', 'Salary', 'deposit');
        Transaction::create($this->testUserId, 1100.00, 'December Rent', 'Bills & Utilities', 'expense');
        Transaction::create($this->testUserId, 250.00, 'December Groceries', 'Food & Dining', 'expense');
        
        // Update these to December 2023
        $sql = "UPDATE transactions SET date = '2023-12-15' WHERE description LIKE 'December%'";
        Database::execute($sql, []);
        
        $trends = Report::getSpendingTrends($this->testUserId, '2024', '01');
        
        $this->assertArrayHasKey('current', $trends);
        $this->assertArrayHasKey('previous', $trends);
        $this->assertArrayHasKey('trends', $trends);
        
        // Check current period
        $this->assertEquals('January 2024', $trends['current']['period']);
        $this->assertEquals(5000.00, $trends['current']['total_income']);
        
        // Check previous period
        $this->assertEquals('December 2023', $trends['previous']['period']);
        $this->assertEquals(4800.00, $trends['previous']['total_income']);
        
        // Check trends calculations
        $this->assertGreaterThan(0, $trends['trends']['income_change']); // Should be positive (income increased)
        $this->assertGreaterThan(0, $trends['trends']['expense_change']); // Should be positive (expenses increased)
    }

    public function testGetDailySpending(): void
    {
        $dailySpending = Report::getDailySpending($this->testUserId, '2024', '01');
        
        $this->assertNotEmpty($dailySpending);
        
        // Should have 31 days for January
        $this->assertEquals(31, count($dailySpending));
        
        // Check specific day (15th) where our transactions are
        $day15 = null;
        foreach ($dailySpending as $day) {
            if ($day['day'] === '15') {
                $day15 = $day;
                break;
            }
        }
        
        $this->assertNotNull($day15);
        $this->assertEquals('2024-01-15', $day15['date']);
        $this->assertEquals(5000.00, $day15['income']);
        $this->assertEquals(1750.00, $day15['expenses']);
        $this->assertEquals(3250.00, $day15['net']);
        
        // Other days should be zero
        $day1 = $dailySpending[0];
        $this->assertEquals('2024-01-01', $day1['date']);
        $this->assertEquals(0.0, $day1['income']);
        $this->assertEquals(0.0, $day1['expenses']);
    }

    public function testUserIsolation(): void
    {
        // Create another test user with transactions
        $user2Id = 887;
        $sql = "INSERT OR REPLACE INTO users (id, google_id, email, name) VALUES (?, ?, ?, ?)";
        Database::execute($sql, [$user2Id, 'testreport456', 'testreport2@example.com', 'Test Report User 2']);
        
        Transaction::create($user2Id, 2000.00, 'User 2 Income', 'Salary', 'deposit');
        
        // Update user 2's transaction to January 2024
        $sql = "UPDATE transactions SET date = '2024-01-15' WHERE user_id = ? AND description = 'User 2 Income'";
        Database::execute($sql, [$user2Id]);
        
        // Get summaries for both users
        $user1Summary = Report::getMonthlySummary($this->testUserId, '2024', '01');
        $user2Summary = Report::getMonthlySummary($user2Id, '2024', '01');
        
        // Verify isolation
        $this->assertEquals(5000.00, $user1Summary['total_income']);
        $this->assertEquals(1750.00, $user1Summary['total_expenses']);
        
        $this->assertEquals(2000.00, $user2Summary['total_income']);
        $this->assertEquals(0.0, $user2Summary['total_expenses']);
    }
}