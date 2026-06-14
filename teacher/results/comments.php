<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Class Teacher Remarks';
$db = getDB();
$userId = (int)$_SESSION['user_id'];
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$classTeacherClasses = $db->prepare("SELECT id, name, section FROM classes WHERE class_teacher_id = ?");
$classTeacherClasses->execute([$userId]);
$classTeacherClasses = $classTeacherClasses->fetchAll();

if (empty($classTeacherClasses)) {
    require_once __DIR__ . '/../../includes/header.php';
    echo '<div class="card"><div class="card-body text-center py-5">
        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
        <p class="text-muted mb-0">You are not assigned as a class teacher.</p>
        <a href="' . BASE_URL . '/teacher/results/index.php" class="btn btn-primary mt-3"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div></div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$classId = (int)($_GET['class_id'] ?? ($classTeacherClasses[0]['id'] ?? 0));
$classValid = false;
foreach ($classTeacherClasses as $ctc) {
    if ((int)$ctc['id'] === $classId) { $classValid = true; break; }
}
if (!$classValid) $classId = (int)$classTeacherClasses[0]['id'];

$class = $db->prepare("SELECT id, name, section FROM classes WHERE id = ?");
$class->execute([$classId]);
$class = $class->fetch();

$students = $db->prepare("
    SELECT s.id, s.admission_no, u.first_name, u.last_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.class_id = ? AND s.status = 'active'
    ORDER BY u.last_name, u.first_name
");
$students->execute([$classId]);
$students = $students->fetchAll();

$existing = $db->prepare("SELECT * FROM result_comments WHERE session_id = ? AND term_id = ?");
$existing->execute([$sessionId, $termId]);
$existingMap = [];
foreach ($existing as $row) {
    $existingMap[$row['student_id']] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_comments'])) {
    foreach ($students as $student) {
        $sid = $student['id'];
        $remark = trim($_POST["remark_$sid"] ?? '');
        $remark = substr($remark, 0, 500);

        if (isset($existingMap[$sid])) {
            $db->prepare("UPDATE result_comments SET class_teacher_remark = ?, class_teacher_id = ? WHERE student_id = ? AND session_id = ? AND term_id = ?")
                ->execute([$remark, $userId, $sid, $sessionId, $termId]);
        } else {
            $db->prepare("INSERT INTO result_comments (student_id, session_id, term_id, class_teacher_remark, class_teacher_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$sid, $sessionId, $termId, $remark, $userId]);
        }
    }

    logAudit('class_teacher_remarks_saved', 'result_comments', $classId, null, "Class: $classId, Session: $sessionId, Term: $termId");
    $success = 'Class teacher remarks saved successfully.';

    $existing->execute([$sessionId, $termId]);
    $existingMap = [];
    foreach ($existing as $row) {
        $existingMap[$row['student_id']] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-comment me-2"></i>Class Teacher Remarks</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($class['name'] ?? '') ?> <?= sanitizeInput($class['section'] ?? '') ?></p>
    </div>
    <a href="<?= BASE_URL ?>/teacher/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-auto"><label class="form-label mb-0">Class:</label></div>
            <div class="col-auto">
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($classTeacherClasses as $ctc): ?>
                    <option value="<?= $ctc['id'] ?>" <?= (int)$ctc['id'] === $classId ? 'selected' : '' ?>>
                        <?= sanitizeInput($ctc['name']) ?> <?= sanitizeInput($ctc['section'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
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
<form method="post">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-users me-2"></i>Students (<?= count($students) ?>)</span>
            <button type="submit" name="save_comments" class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i>Save Remarks</button>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:120px">Admission No</th>
                        <th style="width:200px">Student Name</th>
                        <th>Class Teacher Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $presetRemarks = [
                        'Excellent performance, keep it up!',
                        'Very good, but needs to work harder.',
                        'Good, but has room for improvement.',
                        'Fair performance, needs more effort.',
                        'Poor performance, requires serious attention.',
                        'Shows improvement this term.',
                        'Outstanding conduct and academic excellence.',
                        'Satisfactory progress, can do better.',
                    ];
                    $i = 1; foreach ($students as $student):
                        $remark = $existingMap[$student['id']]['class_teacher_remark'] ?? '';
                        $matched = in_array($remark, $presetRemarks) ? $remark : '';
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><small><?= sanitizeInput($student['admission_no']) ?></small></td>
                        <td class="fw-medium"><?= sanitizeInput($student['last_name'] . ' ' . $student['first_name']) ?></td>
                        <td>
                            <select class="form-select form-select-sm mb-1 preset-remark" data-student="<?= $student['id'] ?>">
                                <option value="">-- Choose a remark --</option>
                                <?php foreach ($presetRemarks as $pr): ?>
                                <option value="<?= sanitizeInput($pr) ?>" <?= $matched === $pr ? 'selected' : '' ?>><?= sanitizeInput($pr) ?></option>
                                <?php endforeach; ?>
                                <option value="__custom__" <?= $matched === '' && $remark !== '' ? 'selected' : '' ?>>Custom remark...</option>
                            </select>
                            <textarea name="remark_<?= $student['id'] ?>" id="remark_<?= $student['id'] ?>" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="Enter or choose a remark..."><?= sanitizeInput($remark) ?></textarea>
                            <small class="text-muted">Max 500 characters</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-end">
            <button type="submit" name="save_comments" class="btn btn-gold"><i class="fas fa-save me-1"></i>Save Remarks</button>
        </div>
    </div>
</form>
<?php endif; ?>

<script>
document.querySelectorAll('.preset-remark').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var sid = this.dataset.student;
        var ta = document.getElementById('remark_' + sid);
        if (this.value && this.value !== '__custom__') {
            ta.value = this.value;
        }
    });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
