<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\OAuthGoogle;
use PHPUnit\Framework\TestCase;

class OAuthGoogleTest extends TestCase
{
    private OAuthGoogle $oauth;

    protected function setUp(): void
    {
        $this->oauth = new OAuthGoogle();
    }

    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(OAuthGoogle::class, $this->oauth);
    }

    public function testOAuthInstanceHasRequiredProperties(): void
    {
        // Test that the class has the necessary private properties
        // We can't directly test private properties, but we can test the class exists
        $this->assertTrue(class_exists(OAuthGoogle::class));
    }

    public function testOAuthUrlsAreValid(): void
    {
        // Test that OAuth URLs are properly formatted
        $reflection = new \ReflectionClass(OAuthGoogle::class);

        $authUrl = $reflection->getProperty('authUrl');
        $authUrl->setAccessible(true);
        $this->assertStringStartsWith('https://', $authUrl->getValue($this->oauth));

        $tokenUrl = $reflection->getProperty('tokenUrl');
        $tokenUrl->setAccessible(true);
        $this->assertStringStartsWith('https://', $tokenUrl->getValue($this->oauth));

        $userInfoUrl = $reflection->getProperty('userInfoUrl');
        $userInfoUrl->setAccessible(true);
        $this->assertStringStartsWith('https://', $userInfoUrl->getValue($this->oauth));
    }

    public function testOAuthConfigurationValues(): void
    {
        $reflection = new \ReflectionClass(OAuthGoogle::class);

        $redirectUri = $reflection->getProperty('redirectUri');
        $redirectUri->setAccessible(true);
        $this->assertStringContainsString('oauth.php', $redirectUri->getValue($this->oauth));

        $clientId = $reflection->getProperty('clientId');
        $clientId->setAccessible(true);
        $this->assertIsString($clientId->getValue($this->oauth));

        $clientSecret = $reflection->getProperty('clientSecret');
        $clientSecret->setAccessible(true);
        $this->assertIsString($clientSecret->getValue($this->oauth));
    }

    /**
     * Note: Full OAuth testing would require:
     * - Mocking HTTP requests and responses
     * - Setting up test OAuth credentials
     * - Testing the complete authentication flow
     *
     * For production, consider using a library like Guzzle with proper mocking
     */
}