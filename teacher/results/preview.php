<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Preview Results';
$db = getDB();
$teacherId = getTeacherId();
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$subjects = $db->prepare("
    SELECT sa.id as allocation_id, s.id as subject_id, s.name as subject_name, s.code, c.id as class_id, c.name as class_name, c.section
    FROM subject_allocations sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN classes c ON sa.class_id = c.id
    WHERE sa.teacher_id = ? AND sa.academic_session_id = ?
    ORDER BY c.name, s.name
");
$subjects->execute([$teacherId, $sessionId]);
$mySubjects = $subjects->fetchAll();

$classId = (int)($_GET['class_id'] ?? ($mySubjects[0]['class_id'] ?? 0));
$subjectId = (int)($_GET['subject_id'] ?? ($mySubjects[0]['subject_id'] ?? 0));

$subjectValid = false;
$selectedClass = null;
$selectedSubject = null;
foreach ($mySubjects as $s) {
    if ((int)$s['class_id'] === $classId && (int)$s['subject_id'] === $subjectId) {
        $subjectValid = true;
        $selectedClass = ['id' => $s['class_id'], 'name' => $s['class_name'], 'section' => $s['section']];
        $selectedSubject = ['id' => $s['subject_id'], 'name' => $s['subject_name'], 'code' => $s['subject_code'] ?? ''];
        break;
    }
}

$uniqueClasses = [];
$uniqueSubjects = [];
foreach ($mySubjects as $s) {
    $uniqueClasses[$s['class_id']] = ['id' => $s['class_id'], 'name' => $s['class_name'], 'section' => $s['section']];
    $uniqueSubjects[$s['subject_id']] = ['id' => $s['subject_id'], 'name' => $s['subject_name'], 'code' => $s['code'] ?? ''];
}

$settings = null;
$classStats = null;
$scores = [];

if ($subjectValid && $classId && $subjectId) {
    $settings = getResultSettings($db, $sessionId, $termId);
    $classStats = getClassStats($db, $classId, $sessionId, $termId);

    $scores = $db->prepare("
        SELECT rs.*, s.admission_no, u.first_name, u.last_name
        FROM result_scores rs
        JOIN students s ON rs.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE rs.class_id = ? AND rs.subject_id = ? AND rs.session_id = ? AND rs.term_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $scores->execute([$classId, $subjectId, $sessionId, $termId]);
    $scores = $scores->fetchAll();

    $totalStudents = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND status = 'active'");
    $totalStudents->execute([$classId]);
    $totalStudentsCount = (int)$totalStudents->fetchColumn();

    $statusCounts = $db->prepare("SELECT status, COUNT(*) as cnt FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? GROUP BY status");
    $statusCounts->execute([$classId, $subjectId, $sessionId, $termId]);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-eye me-2"></i>Preview Results</h4>
        <p class="text-muted small mb-0">Review scores before submission</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/teacher/results/enter.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit Scores</a>
        <a href="<?= BASE_URL ?>/teacher/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-auto"><label class="form-label mb-0">Class:</label></div>
            <div class="col-auto">
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Select Class --</option>
                    <?php foreach ($uniqueClasses as $uc): ?>
                    <option value="<?= $uc['id'] ?>" <?= (int)$uc['id'] === $classId ? 'selected' : '' ?>>
                        <?= sanitizeInput($uc['name']) ?> <?= sanitizeInput($uc['section'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><label class="form-label mb-0">Subject:</label></div>
            <div class="col-auto">
                <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($mySubjects as $s):
                        if ((int)$s['class_id'] !== $classId && $classId > 0) continue;
                    ?>
                    <option value="<?= $s['subject_id'] ?>" <?= (int)$s['subject_id'] === $subjectId && (int)$s['class_id'] === $classId ? 'selected' : '' ?>>
                        <?= sanitizeInput($s['subject_name']) ?> (<?= sanitizeInput($s['code'] ?? 'N/A') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($subjectValid && !empty($scores)): ?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy" style="min-height:100px;padding:16px;">
            <div class="stat-value" style="font-size:24px;"><?= count($scores) ?></div>
            <div class="stat-label">Scored</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success" style="min-height:100px;padding:16px;">
            <div class="stat-value" style="font-size:24px;"><?= $classStats['highest'] ?></div>
            <div class="stat-label">Highest Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning" style="min-height:100px;padding:16px;">
            <div class="stat-value" style="font-size:24px;"><?= $classStats['lowest'] ?></div>
            <div class="stat-label">Lowest Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info" style="min-height:100px;padding:16px;">
            <div class="stat-value" style="font-size:24px;"><?= $classStats['average'] ?></div>
            <div class="stat-label">Class Average</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-2"></i>Scores - <?= sanitizeInput($selectedSubject['name']) ?></span>
        <span class="badge bg-info"><?= count($scores) ?> students</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        <th>Assign 1</th>
                        <th>Assign 2</th>
                        <th>Test 1</th>
                        <th>Test 2</th>
                        <th>CA Total</th>
                        <th>Exam</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>Position</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($scores as $score): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><small><?= sanitizeInput($score['admission_no']) ?></small></td>
                        <td class="fw-medium"><?= sanitizeInput($score['last_name'] . ' ' . $score['first_name']) ?></td>
                        <td><?= (float)$score['assignment_score'] ?></td>
                        <td><?= (float)$score['assignment2_score'] ?></td>
                        <td><?= (float)$score['test_score'] ?></td>
                        <td><?= (float)$score['test2_score'] ?></td>
                        <td class="fw-bold"><?= (float)$score['ca_total'] ?></td>
                        <td><?= (float)$score['exam_score'] ?></td>
                        <td class="fw-bold"><?= (float)$score['total_score'] ?></td>
                        <td><span class="badge bg-<?= $score['grade'] === 'A' ? 'success' : ($score['grade'] === 'F' ? 'danger' : 'primary') ?>"><?= $score['grade'] ?></span></td>
                        <td><?= $score['subject_position'] ?: '-' ?></td>
                        <td><?= getStatusBadge($score['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
                Pass mark: <?= $settings['pass_mark'] ?>% |
                Grade A: <?= $settings['grade_a_min'] ?>+ |
                B: <?= $settings['grade_b_min'] ?>+ |
                C: <?= $settings['grade_c_min'] ?>+ |
                D: <?= $settings['grade_d_min'] ?>+ |
                E: <?= $settings['grade_e_min'] ?>+
            </small>
            <div>
                <?php if ($totalStudentsCount > count($scores)): ?>
                <span class="text-warning small me-2"><i class="fas fa-exclamation-triangle"></i> <?= $totalStudentsCount - count($scores) ?> student(s) not yet scored</span>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/teacher/results/enter.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit Scores</a>
            </div>
        </div>
    </div>
</div>
<?php elseif ($subjectValid && empty($scores)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">No scores have been entered for this subject yet.</p>
        <a href="<?= BASE_URL ?>/teacher/results/enter.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-primary mt-3"><i class="fas fa-plus me-1"></i>Enter Scores</a>
    </div>
</div>
<?php elseif (empty($mySubjects)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-book fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">You have no subjects assigned.</p>
        <a href="<?= BASE_URL ?>/teacher/results/index.php" class="btn btn-primary mt-3"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
