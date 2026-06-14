<?php
if (!defined('RESULT_FUNCTIONS_LOADED')) {
    define('RESULT_FUNCTIONS_LOADED', true);

    function getGrade(float $score, float $aMin = 75, float $bMin = 60, float $cMin = 50, float $dMin = 40, float $eMin = 30): string {
        if ($score >= $aMin) return 'A';
        if ($score >= $bMin) return 'B';
        if ($score >= $cMin) return 'C';
        if ($score >= $dMin) return 'D';
        if ($score >= $eMin) return 'E';
        return 'F';
    }

    function getGradeRemark(string $grade): string {
        return match ($grade) {
            'A' => 'Excellent',
            'B' => 'Very Good',
            'C' => 'Good',
            'D' => 'Fair',
            'E' => 'Poor',
            'F' => 'Fail',
            default => ''
        };
    }

    if (!function_exists('getGradePoint')) {
        function getGradePoint(string $grade): int {
            return match ($grade) {
                'A' => 5,
                'B' => 4,
                'C' => 3,
                'D' => 2,
                'E' => 1,
                'F' => 0,
                default => 0
            };
        }
    }

    function getResultSettings(PDO $db, int $sessionId, int $termId): array {
        $stmt = $db->prepare("SELECT * FROM result_settings WHERE session_id = ? AND term_id = ?");
        $stmt->execute([$sessionId, $termId]);
        $settings = $stmt->fetch();
        if (!$settings) {
            $db->prepare("INSERT INTO result_settings (session_id, term_id) VALUES (?, ?)")->execute([$sessionId, $termId]);
            $stmt->execute([$sessionId, $termId]);
            $settings = $stmt->fetch();
        }
        return $settings ?: [
            'ca_weight' => 40, 'exam_weight' => 60, 'pass_mark' => 40,
            'grade_a_min' => 75, 'grade_b_min' => 60, 'grade_c_min' => 50,
            'grade_d_min' => 40, 'grade_e_min' => 30,
            'max_assign1' => 10, 'max_assign2' => 10, 'max_test1' => 10,
            'max_test2' => 10, 'max_exam' => 60, 'ca_max' => 40
        ];
    }

    function computeCaTotal(float $assign1, float $assign2, float $test1, float $test2, float $caMax = 40): float {
        return min($assign1 + $assign2 + $test1 + $test2, $caMax);
    }

    function computeTotalScore(float $caTotal, float $examScore): float {
        return $caTotal + $examScore;
    }

    function computeAndSaveResult(PDO $db, int $scoreId, int $sessionId, int $termId): void {
        $stmt = $db->prepare("SELECT rs.*, s.id as subject_id, s.name as subject_name FROM result_scores rs JOIN subjects s ON rs.subject_id = s.id WHERE rs.id = ?");
        $stmt->execute([$scoreId]);
        $row = $stmt->fetch();
        if (!$row) return;

        $settings = getResultSettings($db, $sessionId, $termId);
        $caTotal = computeCaTotal($row['assignment_score'], $row['assignment2_score'], $row['test_score'], $row['test2_score'], $settings['ca_max']);
        $totalScore = computeTotalScore($caTotal, $row['exam_score']);
        $grade = getGrade($totalScore, $settings['grade_a_min'], $settings['grade_b_min'], $settings['grade_c_min'], $settings['grade_d_min'], $settings['grade_e_min']);

        $db->prepare("UPDATE result_scores SET ca_total = ?, total_score = ?, grade = ? WHERE id = ?")
            ->execute([$caTotal, $totalScore, $grade, $scoreId]);

        updateSubjectPositions($db, $row['student_id'], $row['class_id'], $row['subject_id'], $sessionId, $termId);
    }

    function updateSubjectPositions(PDO $db, int $studentId, int $classId, int $subjectId, int $sessionId, int $termId): void {
        $scores = $db->prepare("SELECT id, student_id, total_score FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? ORDER BY total_score DESC");
        $scores->execute([$classId, $subjectId, $sessionId, $termId]);
        $position = 0;
        $prevScore = -1;
        $prevPosition = 0;
        foreach ($scores as $row) {
            $position++;
            if ($row['total_score'] == $prevScore) {
                $pos = $prevPosition;
            } else {
                $pos = $position;
                $prevPosition = $position;
            }
            $prevScore = $row['total_score'];
            $db->prepare("UPDATE result_scores SET subject_position = ? WHERE id = ?")->execute([$pos, $row['id']]);
        }
    }

    function getStudentTermResults(PDO $db, int $studentId, int $sessionId, int $termId): array {
        $stmt = $db->prepare("
            SELECT rs.*, s.name as subject_name, s.code as subject_code
            FROM result_scores rs
            JOIN subjects s ON rs.subject_id = s.id
            WHERE rs.student_id = ? AND rs.session_id = ? AND rs.term_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$studentId, $sessionId, $termId]);
        return $stmt->fetchAll();
    }

    function getStudentTermSummary(PDO $db, int $studentId, int $sessionId, int $termId): array {
        $results = getStudentTermResults($db, $studentId, $sessionId, $termId);
        $totalMarks = 0;
        $count = count($results);
        $grades = [];
        $passCount = 0;

        $settings = getResultSettings($db, $sessionId, $termId);

        foreach ($results as $r) {
            $totalMarks += (float)$r['total_score'];
            $grades[] = $r['grade'];
            if ((float)$r['total_score'] >= $settings['pass_mark']) $passCount++;
        }

        $average = $count > 0 ? round($totalMarks / $count, 2) : 0;
        $overallGrade = getGrade($average, $settings['grade_a_min'], $settings['grade_b_min'], $settings['grade_c_min'], $settings['grade_d_min'], $settings['grade_e_min']);

        return [
            'results' => $results,
            'total_marks' => $totalMarks,
            'subject_count' => $count,
            'average' => $average,
            'overall_grade' => $overallGrade,
            'pass_count' => $passCount,
            'fail_count' => $count - $passCount
        ];
    }

    function getClassPosition(PDO $db, int $studentId, int $classId, int $sessionId, int $termId): int {
        $stmt = $db->prepare("
            SELECT rs.student_id, AVG(rs.total_score) as avg_score
            FROM result_scores rs
            WHERE rs.class_id = ? AND rs.session_id = ? AND rs.term_id = ?
            GROUP BY rs.student_id
            ORDER BY avg_score DESC
        ");
        $stmt->execute([$classId, $sessionId, $termId]);
        $position = 0;
        $prevAvg = -1;
        $prevPosition = 0;
        $i = 0;
        foreach ($stmt as $row) {
            $i++;
            $avg = round((float)$row['avg_score'], 2);
            if ($avg == $prevAvg) {
                $pos = $prevPosition;
            } else {
                $pos = $i;
                $prevPosition = $i;
            }
            $prevAvg = $avg;
            if ((int)$row['student_id'] === $studentId) return $pos;
        }
        return 0;
    }

    function getClassStats(PDO $db, int $classId, int $sessionId, int $termId): array {
        $stmt = $db->prepare("
            SELECT rs.student_id, AVG(rs.total_score) as avg_score
            FROM result_scores rs
            WHERE rs.class_id = ? AND rs.session_id = ? AND rs.term_id = ?
            GROUP BY rs.student_id
        ");
        $stmt->execute([$classId, $sessionId, $termId]);
        $avgs = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

        if (empty($avgs)) return ['highest' => 0, 'lowest' => 0, 'average' => 0, 'pass_percent' => 0, 'fail_percent' => 0, 'count' => 0];

        $settings = getResultSettings($db, $sessionId, $termId);
        $highest = max($avgs);
        $lowest = min($avgs);
        $avg = round(array_sum($avgs) / count($avgs), 2);
        $passCount = count(array_filter($avgs, fn($v) => $v >= $settings['pass_mark']));
        $total = count($avgs);

        return [
            'highest' => round($highest, 2),
            'lowest' => round($lowest, 2),
            'average' => $avg,
            'pass_percent' => $total > 0 ? round(($passCount / $total) * 100, 2) : 0,
            'fail_percent' => $total > 0 ? round((($total - $passCount) / $total) * 100, 2) : 0,
            'count' => $total
        ];
    }

    function getAttendanceStats(PDO $db, int $studentId, int $classId, int $sessionId, int $termId): array {
        $term = $db->prepare("SELECT start_date, end_date FROM terms WHERE id = ? AND session_id = ?");
        $term->execute([$termId, $sessionId]);
        $t = $term->fetch();
        if (!$t) return ['total_days' => 0, 'present' => 0, 'absent' => 0, 'percentage' => 0];

        $stmt = $db->prepare("
            SELECT COUNT(*) as total,
                SUM(CASE WHEN status = 'present' OR status = 'late' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent
            FROM attendance
            WHERE student_id = ? AND class_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$studentId, $classId, $t['start_date'], $t['end_date']]);
        $att = $stmt->fetch();
        $total = (int)$att['total'];
        $present = (int)$att['present'];
        $absent = (int)$att['absent'];
        return [
            'total_days' => $total,
            'present' => $present,
            'absent' => $absent,
            'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
        ];
    }

    function computeAnnualAverage(array $termAverages): float {
        $filtered = array_filter($termAverages, fn($v) => $v > 0);
        $count = count($filtered);
        return $count > 0 ? round(array_sum($filtered) / $count, 2) : 0;
    }

    function determinePromotion(PDO $db, int $studentId, int $fromClassId, int $sessionId): array {
        $stmt = $db->prepare("SELECT * FROM promotion_config WHERE session_id = ? AND class_id = ? AND is_active = 1");
        $stmt->execute([$sessionId, $fromClassId]);
        $config = $stmt->fetch();

        if (!$config) {
            $config = ['pass_mark' => 40, 'min_subjects_pass' => 5, 'conditional_pass_mark' => 35, 'max_fail_subjects' => 2];
        }

        $terms = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id");
        $terms->execute([$sessionId]);
        $allTerms = $terms->fetchAll();
        $termAverages = [];
        $totalFailSubjects = [];
        $totalSubjects = 0;

        foreach ($allTerms as $term) {
            $summary = getStudentTermSummary($db, $studentId, $sessionId, $term['id']);
            $termAverages[] = $summary['average'];
            $totalSubjects = max($totalSubjects, $summary['subject_count']);
            $failCount = 0;
            foreach ($summary['results'] as $r) {
                if ((float)$r['total_score'] < $config['pass_mark']) $failCount++;
            }
            $totalFailSubjects[] = $failCount;
        }

        $annualAvg = computeAnnualAverage($termAverages);
        $avgFail = count($totalFailSubjects) > 0 ? round(array_sum($totalFailSubjects) / count($totalFailSubjects)) : count($totalFailSubjects);

        $result = [
            'annual_average' => $annualAvg,
            'status' => 'repeated',
            'to_class_id' => null,
            'remark' => 'REPEAT CURRENT CLASS'
        ];

        if ($annualAvg >= $config['pass_mark'] && $avgFail <= $config['max_fail_subjects']) {
            $nextClass = getNextClass($db, $fromClassId);
            $result['status'] = 'promoted';
            $result['to_class_id'] = $nextClass;
            $result['remark'] = 'PROMOTED TO NEXT CLASS';
        } elseif ($annualAvg >= $config['conditional_pass_mark'] && $avgFail <= $config['max_fail_subjects'] + 1) {
            $nextClass = getNextClass($db, $fromClassId);
            $result['status'] = 'conditional';
            $result['to_class_id'] = $nextClass;
            $result['remark'] = 'CONDITIONALLY PROMOTED';
        }

        return $result;
    }

    function getNextClass(PDO $db, int $currentClassId): ?int {
        $stmt = $db->prepare("SELECT name, section FROM classes WHERE id = ?");
        $stmt->execute([$currentClassId]);
        $current = $stmt->fetch();
        if (!$current) return null;

        $order = ['JSS1' => 1, 'JSS2' => 2, 'JSS3' => 3, 'SS1' => 4, 'SS2' => 5, 'SS3' => 6];
        $parts = explode(' ', $current['name']);
        $level = $parts[0];
        $num = $parts[1] ?? '';
        $currentOrder = $order[$current['name']] ?? 0;

        $nextName = null;
        foreach ($order as $name => $o) {
            if ($o === $currentOrder + 1) { $nextName = $name; break; }
        }

        if (!$nextName) return null;

        $stmt = $db->prepare("SELECT id FROM classes WHERE name = ? AND section = ? AND academic_session_id = (SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1)");
        $stmt->execute([$nextName, $current['section']]);
        return $stmt->fetchColumn() ?: null;
    }

    function generateResultPin(PDO $db, int $sessionId, ?int $studentId = null, ?int $termId = null, ?string $expiresAt = null, int $generatedBy = 0): string {
        $pin = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $pinFormatted = chunk_split($pin, 4, '-');
        $pinFormatted = rtrim($pinFormatted, '-');
        $stmt = $db->prepare("INSERT INTO result_pins (pin, student_id, session_id, term_id, expires_at, generated_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$pinFormatted, $studentId, $sessionId, $termId, $expiresAt, $generatedBy]);
        return $pinFormatted;
    }

    function validateResultPin(PDO $db, string $pin, int $studentId, int $sessionId, ?int $termId = null): bool {
        $sql = "SELECT id, is_used, is_active, expires_at FROM result_pins WHERE pin = ? AND student_id = ? AND session_id = ?";
        $params = [$pin, $studentId, $sessionId];
        if ($termId) { $sql .= " AND (term_id = ? OR term_id IS NULL)"; $params[] = $termId; }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $pinData = $stmt->fetch();

        if (!$pinData) return false;
        if (!$pinData['is_active']) return false;
        if ($pinData['is_used']) return false;
        if ($pinData['expires_at'] && strtotime($pinData['expires_at']) < time()) return false;

        $db->prepare("UPDATE result_pins SET is_used = 1, used_at = NOW() WHERE id = ?")->execute([$pinData['id']]);
        return true;
    }

    function generateAcademicInsights(PDO $db, int $studentId, int $sessionId, int $termId): array {
        $results = getStudentTermResults($db, $studentId, $sessionId, $termId);
        $settings = getResultSettings($db, $sessionId, $termId);
        $strengths = [];
        $weaknesses = [];
        $recommendations = [];
        $subjectSuggestions = [];

        foreach ($results as $r) {
            $score = (float)$r['total_score'];
            $subject = $r['subject_name'];
            $grade = $r['grade'];

            if ($score >= $settings['grade_b_min']) {
                $strengths[] = "$subject ($grade - " . getGradeRemark($grade) . ")";
            } else {
                $weaknesses[] = "$subject ($grade - " . getGradeRemark($grade) . ")";
                if ($score < $settings['pass_mark']) {
                    $recommendations[] = "Needs improvement in $subject. Consider extra tutoring and more practice exercises.";
                } else {
                    $recommendations[] = "Can improve in $subject with additional effort and focus.";
                }
            }
        }

        if (!empty($strengths)) {
            $subjectSuggestions[] = "Strong areas: " . implode(', ', array_slice($strengths, 0, 3)) . ".";
            $subjectSuggestions[] = "Encourage participation in related competitions and advanced assignments.";
        }
        if (!empty($weaknesses)) {
            $subjectSuggestions[] = "Areas needing attention: " . implode(', ', array_slice($weaknesses, 0, 3)) . ".";
            $subjectSuggestions[] = "Create a structured study schedule focusing on these subjects.";
        }

        $insights = [
            'strengths' => implode("\n", $strengths),
            'weaknesses' => implode("\n", $weaknesses),
            'recommendations' => implode("\n", $recommendations),
            'subject_suggestions' => implode("\n", $subjectSuggestions)
        ];

        $stmt = $db->prepare("INSERT INTO academic_insights (student_id, session_id, term_id, strengths, weaknesses, recommendations, subject_suggestions) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE strengths = VALUES(strengths), weaknesses = VALUES(weaknesses), recommendations = VALUES(recommendations), subject_suggestions = VALUES(subject_suggestions)");
        $stmt->execute([$studentId, $sessionId, $termId, $insights['strengths'], $insights['weaknesses'], $insights['recommendations'], $insights['subject_suggestions']]);

        return $insights;
    }

    function isResultPublished(PDO $db, int $classId, int $sessionId, int $termId, ?int $subjectId = null): bool {
        if ($subjectId) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM result_approvals WHERE class_id = ? AND session_id = ? AND term_id = ? AND subject_id = ? AND approval_stage = 'published' AND status = 'approved'");
            $stmt->execute([$classId, $sessionId, $termId, $subjectId]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM result_approvals WHERE class_id = ? AND session_id = ? AND term_id = ? AND approval_stage = 'published' AND status = 'approved'");
            $stmt->execute([$classId, $sessionId, $termId]);
        }
        return $stmt->fetchColumn() > 0;
    }

    function getResultApprovalStatus(PDO $db, int $classId, int $sessionId, int $termId): array {
        $stmt = $db->prepare("SELECT sa.approval_stage, sa.status, sa.comment, CONCAT(u.first_name, ' ', u.last_name) as approved_by_name, sa.updated_at FROM result_approvals sa LEFT JOIN users u ON sa.approved_by = u.id WHERE sa.class_id = ? AND sa.session_id = ? AND sa.term_id = ? AND sa.subject_id IS NULL ORDER BY FIELD(sa.approval_stage, 'subject_teacher','class_teacher','principal','published')");
        $stmt->execute([$classId, $sessionId, $termId]);
        return $stmt->fetchAll();
    }
}
