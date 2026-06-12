<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Enter Scores';
$db = getDB();
$teacherId = getTeacherId();
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$classId = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

if (!$classId || !$subjectId) {
    redirect('/teacher/results/index.php');
}

$allocation = $db->prepare("
    SELECT sa.id FROM subject_allocations sa
    WHERE sa.teacher_id = ? AND sa.class_id = ? AND sa.subject_id = ? AND sa.academic_session_id = ?
");
$allocation->execute([$teacherId, $classId, $subjectId, $sessionId]);
if (!$allocation->fetch()) {
    redirect('/teacher/results/index.php');
}

$class = $db->prepare("SELECT id, name, section FROM classes WHERE id = ?");
$class->execute([$classId]);
$class = $class->fetch();
if (!$class) redirect('/teacher/results/index.php');

$subject = $db->prepare("SELECT id, name, code FROM subjects WHERE id = ?");
$subject->execute([$subjectId]);
$subject = $subject->fetch();
if (!$subject) redirect('/teacher/results/index.php');

$isPublished = isResultPublished($db, $classId, $sessionId, $termId, $subjectId);

$existingStatus = $db->prepare("SELECT DISTINCT status FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? LIMIT 1");
$existingStatus->execute([$classId, $subjectId, $sessionId, $termId]);
$currentStatus = $existingStatus->fetchColumn() ?: 'draft';

$readOnly = $isPublished || in_array($currentStatus, ['submitted', 'approved']);

$students = $db->prepare("
    SELECT s.id, s.admission_no, u.first_name, u.last_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id = ? AND s.status = 'active'
    ORDER BY u.last_name, u.first_name
");
$students->execute([$classId]);
$students = $students->fetchAll();

$existingScores = [];
if (!empty($students)) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge($ids, [$classId, $subjectId, $sessionId, $termId]);
    $stmt = $db->prepare("SELECT * FROM result_scores WHERE student_id IN ($placeholders) AND class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ?");
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $existingScores[$row['student_id']] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$readOnly) {
    $action = $_POST['action'] ?? 'save_draft';
    $newStatus = ($action === 'submit') ? 'submitted' : 'draft';

    foreach ($students as $student) {
        $sid = $student['id'];
        $assignment = (float)($_POST["assignment_$sid"] ?? 0);
        $test = (float)($_POST["test_$sid"] ?? 0);
        $project = (float)($_POST["project_$sid"] ?? 0);
        $exam = (float)($_POST["exam_$sid"] ?? 0);

        $assignment = max(0, min(100, $assignment));
        $test = max(0, min(100, $test));
        $project = max(0, min(100, $project));
        $exam = max(0, min(100, $exam));

        if (isset($existingScores[$sid])) {
            $scoreId = $existingScores[$sid]['id'];
            $db->prepare("UPDATE result_scores SET assignment_score = ?, test_score = ?, project_score = ?, exam_score = ?, status = ? WHERE id = ?")
                ->execute([$assignment, $test, $project, $exam, $newStatus, $scoreId]);
        } else {
            $db->prepare("INSERT INTO result_scores (student_id, class_id, subject_id, session_id, term_id, assignment_score, test_score, project_score, exam_score, status, entered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$sid, $classId, $subjectId, $sessionId, $termId, $assignment, $test, $project, $exam, $newStatus, $userId = $_SESSION['user_id']]);
            $scoreId = $db->lastInsertId();
        }

        computeAndSaveResult($db, $scoreId, $sessionId, $termId);
    }

    logAudit('result_scores_entered', 'result_scores', $subjectId, null, "Class: $classId, Subject: $subjectId, Status: $newStatus");
    $success = ($newStatus === 'submitted') ? 'Scores submitted successfully.' : 'Scores saved as draft.';
    $currentStatus = $newStatus;

    $existingScores = [];
    $params = array_merge(array_column($students, 'id'), [$classId, $subjectId, $sessionId, $termId]);
    $placeholders = implode(',', array_fill(0, count($students), '?'));
    $stmt = $db->prepare("SELECT * FROM result_scores WHERE student_id IN ($placeholders) AND class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ?");
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $existingScores[$row['student_id']] = $row;
    }

    $readOnly = in_array($currentStatus, ['submitted', 'approved']);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-edit me-2"></i>Enter Scores</h4>
        <p class="text-muted small mb-0">
            <?= sanitizeInput($subject['name']) ?> (<?= sanitizeInput($subject['code'] ?? 'N/A') ?>) -
            <?= sanitizeInput($class['name']) ?> <?= sanitizeInput($class['section'] ?? '') ?>
        </p>
    </div>
    <a href="<?= BASE_URL ?>/teacher/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($isPublished): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>These results have been published and are read-only.</div>
<?php elseif ($currentStatus === 'submitted'): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>These results have been submitted for approval. Contact an administrator to make changes.</div>
<?php elseif ($currentStatus === 'approved'): ?>
<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>These results have been approved and are read-only.</div>
<?php endif; ?>

<?php if (empty($students)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-users fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">No active students found in this class.</p>
    </div>
</div>
<?php else: ?>
<form method="post" id="scoresForm">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-users me-2"></i>Students (<?= count($students) ?>)</span>
            <?php if (!$readOnly): ?>
            <div class="d-flex gap-2">
                <button type="submit" name="action" value="save_draft" class="btn btn-warning btn-sm"><i class="fas fa-save me-1"></i>Save as Draft</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary btn-sm" onclick="return confirm('Submit these scores for approval? This action cannot be undone.')"><i class="fas fa-check-double me-1"></i>Submit for Approval</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th style="min-width:100px">Assignment (0-100)</th>
                            <th style="min-width:100px">Test (0-100)</th>
                            <th style="min-width:100px">Project (0-100)</th>
                            <th style="min-width:80px">CA Total</th>
                            <th style="min-width:100px">Exam (0-100)</th>
                            <th style="min-width:100px">Total</th>
                            <th style="min-width:60px">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $student):
                            $score = $existingScores[$student['id']] ?? null;
                            $assignment = $score ? (float)$score['assignment_score'] : 0;
                            $test = $score ? (float)$score['test_score'] : 0;
                            $project = $score ? (float)$score['project_score'] : 0;
                            $exam = $score ? (float)$score['exam_score'] : 0;
                            $caTotal = $score ? (float)$score['ca_total'] : 0;
                            $total = $score ? (float)$score['total_score'] : 0;
                            $grade = $score ? $score['grade'] : '-';
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><small><?= sanitizeInput($student['admission_no']) ?></small></td>
                            <td class="fw-medium"><?= sanitizeInput($student['last_name'] . ' ' . $student['first_name']) ?></td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="assignment_<?= $student['id'] ?>"
                                    value="<?= $assignment ?>" min="0" max="100" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="assignment"
                                    <?= $readOnly ? 'readonly' : '' ?> required>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="test_<?= $student['id'] ?>"
                                    value="<?= $test ?>" min="0" max="100" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="test"
                                    <?= $readOnly ? 'readonly' : '' ?> required>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="project_<?= $student['id'] ?>"
                                    value="<?= $project ?>" min="0" max="100" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="project"
                                    <?= $readOnly ? 'readonly' : '' ?> required>
                            </td>
                            <td class="fw-bold ca-total text-center" id="ca_<?= $student['id'] ?>"><?= $caTotal ?></td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="exam_<?= $student['id'] ?>"
                                    value="<?= $exam ?>" min="0" max="100" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="exam"
                                    <?= $readOnly ? 'readonly' : '' ?> required>
                            </td>
                            <td class="fw-bold total-score text-center" id="total_<?= $student['id'] ?>"><?= $total ?></td>
                            <td class="fw-bold text-center grade" id="grade_<?= $student['id'] ?>"><?= $grade ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (!$readOnly): ?>
        <div class="card-footer text-end">
            <button type="submit" name="action" value="save_draft" class="btn btn-warning"><i class="fas fa-save me-1"></i>Save as Draft</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary" onclick="return confirm('Submit these scores for approval? This action cannot be undone.')"><i class="fas fa-check-double me-1"></i>Submit for Approval</button>
        </div>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<?php $extraScripts = <<<EOS
<script>
document.querySelectorAll('.score-input').forEach(function(input) {
    input.addEventListener('input', function() {
        var sid = this.dataset.student;
        var assignment = parseFloat(document.querySelector('[name="assignment_' + sid + '"]').value) || 0;
        var test = parseFloat(document.querySelector('[name="test_' + sid + '"]').value) || 0;
        var project = parseFloat(document.querySelector('[name="project_' + sid + '"]').value) || 0;
        var exam = parseFloat(document.querySelector('[name="exam_' + sid + '"]').value) || 0;

        var caTotal = Math.min(assignment + test + project, 100);
        var totalScore = Math.min(caTotal + exam, 100);

        document.getElementById('ca_' + sid).textContent = caTotal.toFixed(1);
        document.getElementById('total_' + sid).textContent = totalScore.toFixed(1);

        var grade = '-';
        if (totalScore >= 75) grade = 'A';
        else if (totalScore >= 60) grade = 'B';
        else if (totalScore >= 50) grade = 'C';
        else if (totalScore >= 40) grade = 'D';
        else if (totalScore >= 30) grade = 'E';
        else if (totalScore > 0) grade = 'F';
        document.getElementById('grade_' + sid).textContent = grade;
    });
});
</script>
EOS;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
