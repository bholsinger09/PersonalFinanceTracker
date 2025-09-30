<?php

declare(strict_types=1);

namespace FinanceTracker\Tests;

use FinanceTracker\OAuthGoogle;
use PHPUnit\Framework\TestCase;

class OAuthGoogleTest extends TestCase
{
    public function testClassCanBeInstantiated(): void
    {
        $oauth = new OAuthGoogle();
        $this->assertInstanceOf(OAuthGoogle::class, $oauth);
    }

    // Note: Full OAuth testing would require mocking HTTP requests
    // For now, we have basic structure test
}