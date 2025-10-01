<?php

declare(strict_types=1);

namespace FinanceTracker;

/**
 * User model for managing user accounts
 */
class User
{
    private int $id;
    private string $googleId;
    private string $email;
    private string $name;
    private ?string $picture;

    public function __construct(
        int $id,
        string $googleId,
        string $email,
        string $name,
        ?string $picture = null
    ) {
        $this->id = $id;
        $this->googleId = $googleId;
        $this->email = $email;
        $this->name = $name;
        $this->picture = $picture;
    }

    /**
     * Create or get user from Google OAuth data
     */
    public static function createOrGetFromOAuth(array $googleUser): self
    {
        $googleId = $googleUser['id'];
        $email = $googleUser['email'];
        $name = $googleUser['name'];
        $picture = $googleUser['picture'] ?? null;

        // Try to find existing user by Google ID
        $existingUser = self::findByGoogleId($googleId);
        if ($existingUser) {
            // Update user info in case it changed
            $existingUser->update($email, $name, $picture);
            return $existingUser;
        }

        // Try to find existing user by email (in case they used different OAuth before)
        $existingUser = self::findByEmail($email);
        if ($existingUser) {
            // Link this Google account to existing user
            $existingUser->updateGoogleId($googleId);
            $existingUser->update($email, $name, $picture);
            return $existingUser;
        }

        // Create new user
        return self::create($googleId, $email, $name, $picture);
    }

    /**
     * Create a new user
     */
    public static function create(string $googleId, string $email, string $name, ?string $picture = null): self
    {
        $sql = 'INSERT INTO users (google_id, email, name, picture) VALUES (?, ?, ?, ?)';
        Database::execute($sql, [$googleId, $email, $name, $picture]);

        $id = (int) Database::lastInsertId();
        return new self($id, $googleId, $email, $name, $picture);
    }

    /**
     * Find user by Google ID
     */
    public static function findByGoogleId(string $googleId): ?self
    {
        $sql = 'SELECT * FROM users WHERE google_id = ?';
        $result = Database::query($sql, [$googleId]);

        if (empty($result)) {
            return null;
        }

        return self::fromArray($result[0]);
    }

    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self
    {
        $sql = 'SELECT * FROM users WHERE email = ?';
        $result = Database::query($sql, [$email]);

        if (empty($result)) {
            return null;
        }

        return self::fromArray($result[0]);
    }

    /**
     * Find user by database ID
     */
    public static function findById(int $id): ?self
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $result = Database::query($sql, [$id]);

        if (empty($result)) {
            return null;
        }

        return self::fromArray($result[0]);
    }

    /**
     * Update user information
     */
    public function update(string $email, string $name, ?string $picture = null): bool
    {
        $sql = 'UPDATE users SET email = ?, name = ?, picture = ? WHERE id = ?';
        $affected = Database::execute($sql, [$email, $name, $picture, $this->id]);

        if ($affected > 0) {
            $this->email = $email;
            $this->name = $name;
            $this->picture = $picture;
            return true;
        }

        return false;
    }

    /**
     * Update Google ID for existing user
     */
    public function updateGoogleId(string $googleId): bool
    {
        $sql = 'UPDATE users SET google_id = ? WHERE id = ?';
        $affected = Database::execute($sql, [$googleId, $this->id]);

        if ($affected > 0) {
            $this->googleId = $googleId;
            return true;
        }

        return false;
    }

    /**
     * Create User from database row
     */
    private static function fromArray(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['google_id'],
            $row['email'],
            $row['name'],
            $row['picture']
        );
    }

    /**
     * Convert user to array for session storage
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'google_id' => $this->googleId,
            'email' => $this->email,
            'name' => $this->name,
            'picture' => $this->picture
        ];
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getGoogleId(): string
    {
        return $this->googleId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }
}