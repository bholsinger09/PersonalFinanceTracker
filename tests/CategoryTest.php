<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\Category;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    private string $testDbPath;
    private int $testUserId = 999; // Use a high ID to avoid conflicts

    protected function setUp(): void
    {
        // Use a temporary database for testing
        $this->testDbPath = sys_get_temp_dir() . '/test_finance_tracker_category.db';

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
        Database::execute($sql, [$this->testUserId, 'testcat123', 'testcat@example.com', 'Test Cat User']);
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

    public function testCreateDefaultCategories(): void
    {
        // Create default categories for the user
        $result = Category::createDefaultCategories($this->testUserId);
        $this->assertTrue($result);

        // Verify categories were created
        $categories = Category::getByUserId($this->testUserId);
        $this->assertNotEmpty($categories);
        
        // Should have both expense and income categories
        $this->assertGreaterThan(10, count($categories));
        
        // Check for some expected categories
        $categoryNames = array_column($categories, 'name');
        $this->assertContains('Food & Dining', $categoryNames);
        $this->assertContains('Salary', $categoryNames);
        $this->assertContains('Transportation', $categoryNames);
    }

    public function testCreateCustomCategory(): void
    {
        // Create a custom category
        $result = Category::create($this->testUserId, 'Custom Test Category', '#ff5733');
        $this->assertTrue($result);

        // Verify it was created
        $categories = Category::getByUserId($this->testUserId);
        $this->assertNotEmpty($categories);
        
        $categoryNames = array_column($categories, 'name');
        $this->assertContains('Custom Test Category', $categoryNames);
        
        // Find the category and check its color
        foreach ($categories as $category) {
            if ($category['name'] === 'Custom Test Category') {
                $this->assertEquals('#ff5733', $category['color']);
                break;
            }
        }
    }

    public function testGetGroupedByType(): void
    {
        // Create default categories first
        Category::createDefaultCategories($this->testUserId);

        // Get grouped categories
        $grouped = Category::getGroupedByType($this->testUserId);
        
        $this->assertArrayHasKey('expense', $grouped);
        $this->assertArrayHasKey('income', $grouped);
        
        $this->assertNotEmpty($grouped['expense']);
        $this->assertNotEmpty($grouped['income']);
        
        // Check that income categories contain income-related words
        $incomeNames = array_column($grouped['income'], 'name');
        $hasIncomeCategory = false;
        foreach ($incomeNames as $name) {
            if (stripos($name, 'salary') !== false || stripos($name, 'income') !== false) {
                $hasIncomeCategory = true;
                break;
            }
        }
        $this->assertTrue($hasIncomeCategory);
    }

    public function testUpdateCategory(): void
    {
        // Create a category first
        Category::create($this->testUserId, 'Test Category', '#000000');
        
        // Get the category ID
        $categories = Category::getByUserId($this->testUserId);
        $categoryId = null;
        foreach ($categories as $category) {
            if ($category['name'] === 'Test Category') {
                $categoryId = $category['id'];
                break;
            }
        }
        
        $this->assertNotNull($categoryId);
        
        // Update the category
        $result = Category::update($categoryId, $this->testUserId, 'Updated Category', '#ffffff');
        $this->assertTrue($result);
        
        // Verify the update
        $categories = Category::getByUserId($this->testUserId);
        $categoryNames = array_column($categories, 'name');
        $this->assertContains('Updated Category', $categoryNames);
        $this->assertNotContains('Test Category', $categoryNames);
    }

    public function testDeleteCategory(): void
    {
        // Create a category first
        Category::create($this->testUserId, 'Delete Me', '#ff0000');
        
        // Get the category ID
        $categories = Category::getByUserId($this->testUserId);
        $categoryId = null;
        foreach ($categories as $category) {
            if ($category['name'] === 'Delete Me') {
                $categoryId = $category['id'];
                break;
            }
        }
        
        $this->assertNotNull($categoryId);
        
        // Delete the category
        $result = Category::delete($categoryId, $this->testUserId);
        $this->assertTrue($result);
        
        // Verify it was deleted
        $categories = Category::getByUserId($this->testUserId);
        $categoryNames = array_column($categories, 'name');
        $this->assertNotContains('Delete Me', $categoryNames);
    }

    public function testUserIsolation(): void
    {
        // Create another test user
        $user2Id = 998;
        $sql = "INSERT OR REPLACE INTO users (id, google_id, email, name) VALUES (?, ?, ?, ?)";
        Database::execute($sql, [$user2Id, 'testcat456', 'testcat2@example.com', 'Test Cat User 2']);

        // Create categories for both users
        Category::create($this->testUserId, 'User 1 Category', '#111111');
        Category::create($user2Id, 'User 2 Category', '#222222');

        // Verify user isolation
        $user1Categories = Category::getByUserId($this->testUserId);
        $user2Categories = Category::getByUserId($user2Id);

        $user1Names = array_column($user1Categories, 'name');
        $user2Names = array_column($user2Categories, 'name');

        $this->assertContains('User 1 Category', $user1Names);
        $this->assertNotContains('User 2 Category', $user1Names);

        $this->assertContains('User 2 Category', $user2Names);
        $this->assertNotContains('User 1 Category', $user2Names);
    }
}