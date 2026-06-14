<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Preview Results';
$db = getDB();
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$classId = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

$allSubjects = $classId ? $db->prepare("SELECT DISTINCT s.id, s.name, s.code FROM subjects s JOIN subject_allocations sa ON sa.subject_id = s.id WHERE sa.class_id = ? AND sa.academic_session_id = ? ORDER BY s.name") : [];
if ($classId) {
    $allSubjects->execute([$classId, $sessionId]);
    $allSubjects = $allSubjects->fetchAll();
} else {
    $allSubjects = $db->query("SELECT id, name, code FROM subjects ORDER BY name")->fetchAll();
}

$settings = null;
$classStats = null;
$scores = [];
$selectedClass = null;
$selectedSubject = null;

if ($classId) {
    $cls = $db->prepare("SELECT id, name, section FROM classes WHERE id = ?");
    $cls->execute([$classId]);
    $selectedClass = $cls->fetch();
}

if ($subjectId) {
    $subj = $db->prepare("SELECT id, name, code FROM subjects WHERE id = ?");
    $subj->execute([$subjectId]);
    $selectedSubject = $subj->fetch();
}

if ($classId && $subjectId && $selectedClass && $selectedSubject) {
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
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-eye me-2"></i>Preview Results</h4>
        <p class="text-muted small mb-0">Review scores across all classes and subjects</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($classId && $subjectId): ?>
        <a href="<?= BASE_URL ?>/admin/results/enter.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit Scores</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
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
                    <option value="">-- All Classes --</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= (int)$c['id'] === $classId ? 'selected' : '' ?>><?= sanitizeInput($c['name']) ?> <?= sanitizeInput($c['section'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select form-select-sm" onchange="this.form.submit()" <?= !$classId ? 'disabled' : '' ?>>
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($allSubjects as $sub): ?>
                    <option value="<?= $sub['id'] ?>" <?= (int)$sub['id'] === $subjectId ? 'selected' : '' ?>><?= sanitizeInput($sub['name']) ?> (<?= sanitizeInput($sub['code'] ?? 'N/A') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($classId && $subjectId && !empty($scores) && $settings): ?>
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
        <span><i class="fas fa-table me-2"></i>Scores - <?= sanitizeInput($selectedSubject['name']) ?> (<?= sanitizeInput($selectedClass['name']) ?> <?= sanitizeInput($selectedClass['section'] ?? '') ?>)</span>
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
                <a href="<?= BASE_URL ?>/admin/results/enter.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit Scores</a>
            </div>
        </div>
    </div>
</div>
<?php elseif ($classId && $subjectId && empty($scores)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">No scores have been entered for this subject yet.</p>
        <a href="<?= BASE_URL ?>/admin/results/enter.php?class_id=<?= $classId ?>&subject_id=<?= $subjectId ?>" class="btn btn-primary mt-3"><i class="fas fa-plus me-1"></i>Enter Scores</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-search fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">Select a class and subject to preview results.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
