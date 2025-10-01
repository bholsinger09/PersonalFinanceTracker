<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\Database;
use FinanceTracker\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private string $testDbPath;

    protected function setUp(): void
    {
        // Use a temporary database for testing
        $this->testDbPath = sys_get_temp_dir() . '/test_finance_tracker_users.db';

        // Override the database path for testing
        $reflection = new \ReflectionClass(Database::class);
        $dbPathProperty = $reflection->getProperty('dbPath');
        $dbPathProperty->setAccessible(true);
        $dbPathProperty->setValue(null, $this->testDbPath);

        // Reset the PDO connection
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue(null, null);

        // Initialize schema - this will use the fallback to create tables directly
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

    public function testCreateUser(): void
    {
        $googleId = '123456789';
        $email = 'test@example.com';
        $name = 'Test User';
        $picture = 'https://example.com/picture.jpg';

        $user = User::create($googleId, $email, $name, $picture);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($googleId, $user->getGoogleId());
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($name, $user->getName());
        $this->assertEquals($picture, $user->getPicture());
        $this->assertGreaterThan(0, $user->getId());
    }

    public function testFindUserByGoogleId(): void
    {
        $googleId = '123456789';
        $email = 'test@example.com';
        $name = 'Test User';

        // Create user
        $user = User::create($googleId, $email, $name);

        // Find by Google ID
        $found = User::findByGoogleId($googleId);

        $this->assertNotNull($found);
        $this->assertEquals($user->getId(), $found->getId());
        $this->assertEquals($googleId, $found->getGoogleId());
    }

    public function testFindUserByEmail(): void
    {
        $googleId = '123456789';
        $email = 'test@example.com';
        $name = 'Test User';

        // Create user
        $user = User::create($googleId, $email, $name);

        // Find by email
        $found = User::findByEmail($email);

        $this->assertNotNull($found);
        $this->assertEquals($user->getId(), $found->getId());
        $this->assertEquals($email, $found->getEmail());
    }

    public function testCreateOrGetFromOAuthNewUser(): void
    {
        $googleUserData = [
            'id' => '123456789',
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'picture' => 'https://example.com/picture.jpg'
        ];

        $user = User::createOrGetFromOAuth($googleUserData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($googleUserData['id'], $user->getGoogleId());
        $this->assertEquals($googleUserData['email'], $user->getEmail());
        $this->assertEquals($googleUserData['name'], $user->getName());
    }

    public function testCreateOrGetFromOAuthExistingUser(): void
    {
        $googleId = '123456789';
        $email = 'existing@example.com';
        $name = 'Existing User';

        // Create user first
        $originalUser = User::create($googleId, $email, $name);

        // Simulate OAuth data for same user
        $googleUserData = [
            'id' => $googleId,
            'email' => $email,
            'name' => 'Updated Name',  // Different name to test update
            'picture' => 'https://example.com/new-picture.jpg'
        ];

        $user = User::createOrGetFromOAuth($googleUserData);

        $this->assertEquals($originalUser->getId(), $user->getId());
        $this->assertEquals('Updated Name', $user->getName()); // Should be updated
    }

    public function testUserToArray(): void
    {
        $googleId = '123456789';
        $email = 'test@example.com';
        $name = 'Test User';
        $picture = 'https://example.com/picture.jpg';

        $user = User::create($googleId, $email, $name, $picture);
        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertEquals($user->getId(), $array['id']);
        $this->assertEquals($googleId, $array['google_id']);
        $this->assertEquals($email, $array['email']);
        $this->assertEquals($name, $array['name']);
        $this->assertEquals($picture, $array['picture']);
    }

    public function testUpdateUser(): void
    {
        $user = User::create('123456789', 'original@example.com', 'Original Name');

        $newEmail = 'updated@example.com';
        $newName = 'Updated Name';
        $newPicture = 'https://example.com/new-picture.jpg';

        $result = $user->update($newEmail, $newName, $newPicture);

        $this->assertTrue($result);
        $this->assertEquals($newEmail, $user->getEmail());
        $this->assertEquals($newName, $user->getName());
        $this->assertEquals($newPicture, $user->getPicture());
    }

    public function testFindNonExistentUser(): void
    {
        $found = User::findByGoogleId('nonexistent');
        $this->assertNull($found);

        $found = User::findByEmail('nonexistent@example.com');
        $this->assertNull($found);

        $found = User::findById(999);
        $this->assertNull($found);
    }
}