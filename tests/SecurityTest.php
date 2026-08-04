<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    private array $sessionBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
        require_once dirname(__DIR__) . '/includes/security.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testGenerateCsrfTokenReturns64Hex(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', generateCsrfToken());
    }

    public function testCsrfTokenStableWithinSession(): void
    {
        self::assertSame(generateCsrfToken(), generateCsrfToken());
    }

    public function testVerifyAcceptsGeneratedToken(): void
    {
        self::assertTrue(verifyCsrfToken(generateCsrfToken()));
    }

    public function testVerifyRejectsWrongToken(): void
    {
        generateCsrfToken();
        self::assertFalse(verifyCsrfToken('0' . str_repeat('a', 63)));
    }

    public function testVerifyRejectsEmptyOrNullToken(): void
    {
        generateCsrfToken();
        self::assertFalse(verifyCsrfToken(''));
        self::assertFalse(verifyCsrfToken(null));
    }

    public function testVerifyRejectsWhenNoTokenIssued(): void
    {
        self::assertFalse(verifyCsrfToken('abcdef'));
    }

    public function testPasswordPolicyRejectsWeakPassword(): void
    {
        self::assertNotEmpty(validatePasswordPolicy('weak'));
    }

    public function testPasswordPolicyAcceptsStrongPassword(): void
    {
        self::assertEmpty(validatePasswordPolicy('StrongPass1!'));
    }

    public function testGeneratedStrongPasswordMeetsPolicy(): void
    {
        self::assertEmpty(validatePasswordPolicy(generateStrongPassword()));
    }
}
