<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Authentication and password policy helpers.
 */
final class AuthService
{
    public const DEFAULT_BCRYPT_COST = 12;
    public const DEFAULT_MIN_LENGTH = 8;

    /**
     * Hash a plain-text password with bcrypt.
     */
    public static function hashPassword(string $password, int $cost = self::DEFAULT_BCRYPT_COST): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Verify a plain-text password against a stored hash.
     */
    public static function verify(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    /**
     * Check a candidate password against a security policy.
     *
     * @return array{ok: bool, message: string}
     */
    public static function meetsPolicy(string $password, int $minLength = self::DEFAULT_MIN_LENGTH, bool $requireMixed = true, bool $requireNumber = true, bool $requireSpecial = true): array
    {
        if (strlen($password) < $minLength) {
            return ['ok' => false, 'message' => "Password must be at least {$minLength} characters."];
        }
        if ($requireMixed && !preg_match('/[a-z]/', $password)) {
            return ['ok' => false, 'message' => 'Password must contain at least one lowercase letter.'];
        }
        if ($requireMixed && !preg_match('/[A-Z]/', $password)) {
            return ['ok' => false, 'message' => 'Password must contain at least one uppercase letter.'];
        }
        if ($requireNumber && !preg_match('/\d/', $password)) {
            return ['ok' => false, 'message' => 'Password must contain at least one number.'];
        }
        if ($requireSpecial && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return ['ok' => false, 'message' => 'Password must contain at least one special character.'];
        }
        return ['ok' => true, 'message' => ''];
    }
}