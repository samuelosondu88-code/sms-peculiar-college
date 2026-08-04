<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Student-related domain logic.
 */
final class StudentService
{
    /**
     * Build a deterministic, unique admission number from a user id.
     */
    public static function admissionNumber(int $userId, string $prefix = 'STU'): string
    {
        return $prefix . '-' . str_pad((string) $userId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Fetch a student (with user + class fields) by id.
     */
    public static function findById(PDO $db, int $studentId): ?array
    {
        return (new \App\Repositories\StudentRepository($db))->findById($studentId);
    }

    /**
     * Fetch a student by admission number.
     */
    public static function findByAdmissionNo(PDO $db, string $admissionNo): ?array
    {
        return (new \App\Repositories\StudentRepository($db))->findByAdmissionNo($admissionNo);
    }

    /**
     * Create a student record linked to an existing user id.
     */
    public static function create(PDO $db, int $userId, int $classId, string $prefix = 'STU'): int
    {
        $admissionNo = self::admissionNumber($userId, $prefix);
        return (new \App\Repositories\StudentRepository($db))->insert($userId, $admissionNo, $classId);
    }
}