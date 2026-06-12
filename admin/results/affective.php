<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Affective Assessments';
$db = getDB();
$msg = '';
$msgType = 'success';

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT id, term_name, session_id FROM terms ORDER BY session_id, id")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$selectedSession = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_POST['term_id'] ?? $_GET['term_id'] ?? 0);
$selectedClass = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? 0);

$gradeOptions = ['A' => 'A - Excellent', 'B' => 'B - Very Good', 'C' => 'C - Good', 'D' => 'D - Fair', 'E' => 'E - Poor'];
$fields = ['honesty', 'punctuality', 'respect', 'cooperation', 'responsibility'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_affective'])) {
    $sessionId = (int)$_POST['session_id'];
    $termId = (int)$_POST['term_id'];
    $classId = (int)$_POST['class_id'];
    $assessments = $_POST['assessments'] ?? [];
    $saved = 0;

    foreach ($assessments as $studentId => $grades) {
        $studentId = (int)$studentId;
        $honesty = strtoupper(sanitizeInput($grades['honesty'] ?? 'C'));
        $punctuality = strtoupper(sanitizeInput($grades['punctuality'] ?? 'C'));
        $respect = strtoupper(sanitizeInput($grades['respect'] ?? 'C'));
        $cooperation = strtoupper(sanitizeInput($grades['cooperation'] ?? 'C'));
        $responsibility = strtoupper(sanitizeInput($grades['responsibility'] ?? 'C'));

        $stmt = $db->prepare("SELECT id FROM affective_assessments WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
        $stmt->execute([$studentId, $classId, $sessionId, $termId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $db->prepare("UPDATE affective_assessments SET honesty = ?, punctuality = ?, respect = ?, cooperation = ?, responsibility = ? WHERE id = ?");
            $stmt->execute([$honesty, $punctuality, $respect, $cooperation, $responsibility, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO affective_assessments (student_id, class_id, session_id, term_id, honesty, punctuality, respect, cooperation, responsibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$studentId, $classId, $sessionId, $termId, $honesty, $punctuality, $respect, $cooperation, $responsibility]);
        }
        $saved++;
    }

    logAudit('affective_bulk_save', 'affective_assessments', null, null, "Session=$sessionId, Term=$termId, Class=$classId, Saved=$saved");
    $msg = "Affective assessments saved for $saved student(s).";
}

$studentList = [];
if ($selectedClass && $selectedSession && $selectedTerm) {
    $students = $db->prepare("
        SELECT s.id, u.first_name, u.last_name, s.admission_no
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ? AND s.status = 'active'
        ORDER BY u.last_name, u.first_name
    ");
    $students->execute([$selectedClass]);
    $studentList = $students->fetchAll();

    foreach ($studentList as &$student) {
        $stmt = $db->prepare("SELECT * FROM affective_assessments WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
        $stmt->execute([$student['id'], $selectedClass, $selectedSession, $selectedTerm]);
        $student['assessment'] = $stmt->fetch();
    }
    unset($student);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-heart me-2"></i>Affective Assessments</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select">
                    <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Load</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($studentList)): ?>
<form method="POST" id="affectiveForm">
    <input type="hidden" name="session_id" value="<?= $selectedSession ?>">
    <input type="hidden" name="term_id" value="<?= $selectedTerm ?>">
    <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-users me-2"></i>Students in <?= sanitizeInput($classes[array_search($selectedClass, array_column($classes, 'id'))]['name'] ?? '') ?></span>
            <button type="submit" name="save_affective" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save All</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Admission</th>
                            <?php foreach ($fields as $field): ?>
                            <th><?= ucfirst($field) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($studentList as $s): $a = $s['assessment']; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?></td>
                            <td><?= sanitizeInput($s['admission_no']) ?></td>
                            <?php foreach ($fields as $field): ?>
                            <td>
                                <select name="assessments[<?= $s['id'] ?>][<?= $field ?>]" class="form-select form-select-sm" style="min-width:120px">
                                    <?php foreach ($gradeOptions as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($a && isset($a[$field]) && $a[$field] === $val) ? 'selected' : ($val === 'C' && !$a ? 'selected' : '') ?>><?= $label ?></option>
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
    </div>
</form>
<?php elseif ($selectedClass): ?>
<div class="text-center text-muted py-4">No active students found in this class.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
