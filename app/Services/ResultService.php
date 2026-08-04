<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Result/grade domain logic.
 *
 * These delegate to the proven procedural grading helpers in
 * includes/result_functions.php (still the source of truth), exposing them
 * through a typed service API for controllers that opt into it.
 */
final class ResultService
{
    /**
     * Term summary for a student (totals, average, grade, subject breakdown).
     */
    public static function termSummary(PDO $db, int $studentId, int $sessionId, int $termId): array
    {
        return \getStudentTermSummary($db, $studentId, $sessionId, $termId);
    }

    /**
     * Overall class position of a student for a term.
     */
    public static function classPosition(PDO $db, int $studentId, int $classId, int $sessionId, int $termId): int
    {
        return \getClassPosition($db, $studentId, $classId, $sessionId, $termId);
    }

    /**
     * Letter grade for a raw score based on the configured thresholds.
     */
    public static function grade(float $score, array $settings): string
    {
        return \getGrade(
            $score,
            $settings['grade_a_min'] ?? 75,
            $settings['grade_b_min'] ?? 60,
            $settings['grade_c_min'] ?? 50,
            $settings['grade_d_min'] ?? 40,
            $settings['grade_e_min'] ?? 30
        );
    }

    /**
     * Human remark for a grade letter.
     */
    public static function gradeRemark(string $grade): string
    {
        return \getGradeRemark($grade);
    }
}