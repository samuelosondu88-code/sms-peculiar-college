<?php

namespace App\Tests;

use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testHashAndVerifyRoundTrip(): void
    {
        $hash = AuthService::hashPassword('StrongPass1!');
        self::assertTrue(AuthService::verify('StrongPass1!', $hash));
    }

    public function testVerifyRejectsWrongPassword(): void
    {
        $hash = AuthService::hashPassword('StrongPass1!');
        self::assertFalse(AuthService::verify('WrongPass1!', $hash));
    }

    public function testVerifyRejectsEmptyHash(): void
    {
        self::assertFalse(AuthService::verify('StrongPass1!', ''));
    }

    public function testHashUsesBcryptWithConfiguredCost(): void
    {
        $hash = AuthService::hashPassword('StrongPass1!');
        self::assertStringStartsWith('$2y$12$', $hash);
    }

    public function testPolicyAcceptsStrongPassword(): void
    {
        $r = AuthService::meetsPolicy('StrongPass1!');
        self::assertTrue($r['ok']);
    }

    public function testPolicyRejectsShortPassword(): void
    {
        $r = AuthService::meetsPolicy('Ab1!');
        self::assertFalse($r['ok']);
        self::assertStringContainsString('at least 8', $r['message']);
    }

    public function testPolicyRejectsMissingUppercase(): void
    {
        $r = AuthService::meetsPolicy('strongpass1!');
        self::assertFalse($r['ok']);
    }

    public function testPolicyRejectsMissingNumber(): void
    {
        $r = AuthService::meetsPolicy('StrongPass!');
        self::assertFalse($r['ok']);
    }

    public function testPolicyRejectsMissingSpecialCharacter(): void
    {
        $r = AuthService::meetsPolicy('StrongPass1');
        self::assertFalse($r['ok']);
    }
}
