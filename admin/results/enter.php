<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Enter / Modify Scores';
$db = getDB();
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$classId = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

if (!$classId) {
    $subjects = [];
} else {
    $subjects = $db->prepare("SELECT id, name, code FROM subjects ORDER BY name");
    $subjects->execute();
    $subjects = $subjects->fetchAll();
}

$class = null;
$subject = null;
$students = [];
$existingScores = [];
$settings = null;
$readOnly = false;

if ($classId && $subjectId) {
    $class = $db->prepare("SELECT id, name, section FROM classes WHERE id = ?");
    $class->execute([$classId]);
    $class = $class->fetch();

    $subject = $db->prepare("SELECT id, name, code FROM subjects WHERE id = ?");
    $subject->execute([$subjectId]);
    $subject = $subject->fetch();

    if ($class && $subject) {
        $settings = getResultSettings($db, $sessionId, $termId);

        $students = $db->prepare("
            SELECT s.id, s.admission_no, u.first_name, u.last_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.class_id = ? AND s.status = 'active'
            ORDER BY u.last_name, u.first_name
        ");
        $students->execute([$classId]);
        $students = $students->fetchAll();

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
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_draft';
    $newStatus = ($action === 'submit') ? 'submitted' : 'draft';

    foreach ($students as $student) {
        $sid = $student['id'];
        $assignment = (float)($_POST["assignment_$sid"] ?? 0);
        $assignment2 = (float)($_POST["assignment2_$sid"] ?? 0);
        $test = (float)($_POST["test_$sid"] ?? 0);
        $test2 = (float)($_POST["test2_$sid"] ?? 0);
        $exam = (float)($_POST["exam_$sid"] ?? 0);

        $assignment = max(0, min($settings['max_assign1'], $assignment));
        $assignment2 = max(0, min($settings['max_assign2'], $assignment2));
        $test = max(0, min($settings['max_test1'], $test));
        $test2 = max(0, min($settings['max_test2'], $test2));
        $exam = max(0, min($settings['max_exam'], $exam));

        if (isset($existingScores[$sid])) {
            $scoreId = $existingScores[$sid]['id'];
            $db->prepare("UPDATE result_scores SET assignment_score = ?, assignment2_score = ?, test_score = ?, test2_score = ?, exam_score = ?, status = ? WHERE id = ?")
                ->execute([$assignment, $assignment2, $test, $test2, $exam, $newStatus, $scoreId]);
        } else {
            $db->prepare("INSERT INTO result_scores (student_id, class_id, subject_id, session_id, term_id, assignment_score, assignment2_score, test_score, test2_score, exam_score, status, entered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$sid, $classId, $subjectId, $sessionId, $termId, $assignment, $assignment2, $test, $test2, $exam, $newStatus, $_SESSION['user_id']]);
            $scoreId = $db->lastInsertId();
        }

        computeAndSaveResult($db, $scoreId, $sessionId, $termId);
    }

    logAudit('result_scores_entered', 'result_scores', $subjectId, null, "Class: $classId, Subject: $subjectId, Status: $newStatus");
    $success = ($newStatus === 'submitted') ? 'Scores submitted successfully.' : 'Scores saved as draft.';

    $existingScores = [];
    $params = array_merge(array_column($students, 'id'), [$classId, $subjectId, $sessionId, $termId]);
    $placeholders = implode(',', array_fill(0, count($students), '?'));
    $stmt = $db->prepare("SELECT * FROM result_scores WHERE student_id IN ($placeholders) AND class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ?");
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $existingScores[$row['student_id']] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-edit me-2"></i>Enter / Modify Scores</h4>
        <p class="text-muted small mb-0">Admin can enter and modify results for any class and subject</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end" id="selectorForm">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$s['id'] === $sessionId ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $classId ? 'selected' : '' ?>><?= sanitizeInput($c['name']) ?> <?= sanitizeInput($c['section'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()" <?= !$classId ? 'disabled' : '' ?>>
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                    <option value="<?= $sub['id'] ?>" <?= (int)$sub['id'] === $subjectId ? 'selected' : '' ?>><?= sanitizeInput($sub['name']) ?> (<?= sanitizeInput($sub['code'] ?? 'N/A') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($class && $subject): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="mb-0"><?= sanitizeInput($subject['name']) ?> (<?= sanitizeInput($subject['code'] ?? 'N/A') ?>) - <?= sanitizeInput($class['name']) ?> <?= sanitizeInput($class['section'] ?? '') ?></h5>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/results/preview.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-info btn-sm text-white"><i class="fas fa-eye me-1"></i>Preview</a>
    </div>
</div>

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
            <div class="d-flex gap-2">
                <button type="submit" name="action" value="save_draft" class="btn btn-warning btn-sm"><i class="fas fa-save me-1"></i>Save as Draft</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary btn-sm" onclick="return confirm('Submit these scores for approval?')"><i class="fas fa-check-double me-1"></i>Submit for Approval</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th style="min-width:80px">Assign 1</th>
                            <th style="min-width:80px">Assign 2</th>
                            <th style="min-width:80px">Test 1</th>
                            <th style="min-width:80px">Test 2</th>
                            <th style="min-width:80px">CA Total</th>
                            <th style="min-width:80px">Exam</th>
                            <th style="min-width:100px">Total</th>
                            <th style="min-width:60px">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $student):
                            $score = $existingScores[$student['id']] ?? null;
                            $assignment = $score ? (float)$score['assignment_score'] : 0;
                            $assignment2 = $score ? (float)$score['assignment2_score'] : 0;
                            $test = $score ? (float)$score['test_score'] : 0;
                            $test2 = $score ? (float)$score['test2_score'] : 0;
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
                                    value="<?= $assignment ?>" min="0" max="<?= $settings['max_assign1'] ?>" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="assignment" required>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="assignment2_<?= $student['id'] ?>"
                                    value="<?= $assignment2 ?>" min="0" max="<?= $settings['max_assign2'] ?>" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="assignment2" required>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="test_<?= $student['id'] ?>"
                                    value="<?= $test ?>" min="0" max="<?= $settings['max_test1'] ?>" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="test" required>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="test2_<?= $student['id'] ?>"
                                    value="<?= $test2 ?>" min="0" max="<?= $settings['max_test2'] ?>" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="test2" required>
                            </td>
                            <td class="fw-bold ca-total text-center" id="ca_<?= $student['id'] ?>"><?= $caTotal ?></td>
                            <td>
                                <input type="number" class="form-control form-control-sm score-input" name="exam_<?= $student['id'] ?>"
                                    value="<?= $exam ?>" min="0" max="<?= $settings['max_exam'] ?>" step="0.5"
                                    data-student="<?= $student['id'] ?>" data-field="exam" required>
                            </td>
                            <td class="fw-bold total-score text-center" id="total_<?= $student['id'] ?>"><?= $total ?></td>
                            <td class="fw-bold text-center grade" id="grade_<?= $student['id'] ?>"><?= $grade ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" name="action" value="save_draft" class="btn btn-warning"><i class="fas fa-save me-1"></i>Save as Draft</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary" onclick="return confirm('Submit these scores for approval?')"><i class="fas fa-check-double me-1"></i>Submit for Approval</button>
        </div>
    </div>
</form>
<?php endif; ?>
<?php elseif ($classId && $subjectId && (!$class || !$subject)): ?>
<div class="alert alert-danger">Invalid class or subject selected.</div>
<?php endif; ?>

<?php
$caMaxJs = $settings['ca_max'] ?? 60;
$examMaxJs = $settings['max_exam'] ?? 100;
$extraScripts = <<<EOS
<script>
document.querySelectorAll('.score-input').forEach(function(input) {
    input.addEventListener('input', function() {
        var sid = this.dataset.student;
        var assignment = parseFloat(document.querySelector('[name="assignment_' + sid + '"]').value) || 0;
        var assignment2 = parseFloat(document.querySelector('[name="assignment2_' + sid + '"]').value) || 0;
        var test = parseFloat(document.querySelector('[name="test_' + sid + '"]').value) || 0;
        var test2 = parseFloat(document.querySelector('[name="test2_' + sid + '"]').value) || 0;
        var exam = parseFloat(document.querySelector('[name="exam_' + sid + '"]').value) || 0;

        var caMax = $caMaxJs;
        var examMax = $examMaxJs;
        var caTotal = Math.min(assignment + assignment2 + test + test2, caMax);
        var totalScore = Math.min(caTotal + exam, caMax + examMax);

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
