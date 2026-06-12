<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Affective Domain Assessment';
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

$existing = $db->prepare("SELECT * FROM affective_assessments WHERE class_id = ? AND session_id = ? AND term_id = ?");
$existing->execute([$classId, $sessionId, $termId]);
$existingMap = [];
foreach ($existing as $row) {
    $existingMap[$row['student_id']] = $row;
}

$traits = ['honesty', 'punctuality', 'respect', 'cooperation', 'responsibility'];
$traitLabels = [
    'honesty' => 'Honesty',
    'punctuality' => 'Punctuality',
    'respect' => 'Respect',
    'cooperation' => 'Cooperation',
    'responsibility' => 'Responsibility',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_affective'])) {
    foreach ($students as $student) {
        $sid = $student['id'];
        $data = [];
        foreach ($traits as $trait) {
            $val = $_POST["{$trait}_{$sid}"] ?? 'C';
            if (!in_array($val, ['A','B','C','D','E'])) $val = 'C';
            $data[$trait] = $val;
        }

        if (isset($existingMap[$sid])) {
            $sql = "UPDATE affective_assessments SET honesty = ?, punctuality = ?, respect = ?, cooperation = ?, responsibility = ? WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?";
            $db->prepare($sql)->execute([$data['honesty'], $data['punctuality'], $data['respect'], $data['cooperation'], $data['responsibility'], $sid, $classId, $sessionId, $termId]);
        } else {
            $sql = "INSERT INTO affective_assessments (student_id, class_id, session_id, term_id, honesty, punctuality, respect, cooperation, responsibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->prepare($sql)->execute([$sid, $classId, $sessionId, $termId, $data['honesty'], $data['punctuality'], $data['respect'], $data['cooperation'], $data['responsibility']]);
        }
    }

    logAudit('affective_assessment_saved', 'affective_assessments', $classId, null, "Class: $classId, Session: $sessionId, Term: $termId");
    $success = 'Affective assessments saved successfully.';

    $existing->execute([$classId, $sessionId, $termId]);
    $existingMap = [];
    foreach ($existing as $row) {
        $existingMap[$row['student_id']] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-heart me-2"></i>Affective Domain Assessment</h4>
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
            <button type="submit" name="save_affective" class="btn btn-gold btn-sm"><i class="fas fa-save me-1"></i>Save Assessments</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <?php foreach ($traitLabels as $key => $label): ?>
                            <th style="min-width:120px"><?= $label ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($students as $student):
                            $assess = $existingMap[$student['id']] ?? [];
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><small><?= sanitizeInput($student['admission_no']) ?></small></td>
                            <td class="fw-medium"><?= sanitizeInput($student['last_name'] . ' ' . $student['first_name']) ?></td>
                            <?php foreach ($traits as $trait): ?>
                            <td>
                                <select name="<?= $trait ?>_<?= $student['id'] ?>" class="form-select form-select-sm">
                                    <?php $val = $assess[$trait] ?? 'C'; foreach (['A','B','C','D','E'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $val === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" name="save_affective" class="btn btn-gold"><i class="fas fa-save me-1"></i>Save Assessments</button>
        </div>
    </div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
