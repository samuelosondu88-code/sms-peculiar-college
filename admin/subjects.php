<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../includes/result_functions.php';

$pageTitle = 'Manage Subjects';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_subject'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $code = sanitizeInput(strtoupper($_POST['code'] ?? ''));
    $classId = (int)($_POST['class_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);

    if (isset($_POST['subject_id']) && $_POST['subject_id']) {
        $stmt = $db->prepare("UPDATE subjects SET name = ?, code = ?, class_id = ?, teacher_id = ? WHERE id = ?");
        $stmt->execute([$name, $code, $classId, $teacherId ?: null, $_POST['subject_id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO subjects (name, code, class_id, teacher_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $code, $classId, $teacherId ?: null]);
    }
    redirect('/admin/subjects.php?msg=Subject saved');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_allocation'])) {
    $teacherId = (int)($_POST['alloc_teacher_id'] ?? 0);
    $classId = (int)($_POST['alloc_class_id'] ?? 0);
    $subjectId = (int)($_POST['alloc_subject_id'] ?? 0);
    $sessionId = (int)($_POST['alloc_session_id'] ?? 0);

    if ($teacherId && $classId && $subjectId && $sessionId) {
        $check = $db->prepare("SELECT id FROM subject_allocations WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND academic_session_id = ?");
        $check->execute([$teacherId, $classId, $subjectId, $sessionId]);
        if (!$check->fetch()) {
            $db->prepare("INSERT INTO subject_allocations (teacher_id, class_id, subject_id, academic_session_id) VALUES (?, ?, ?, ?)")
                ->execute([$teacherId, $classId, $subjectId, $sessionId]);
            $msg = 'Allocation added.';
        } else {
            $msg = 'This allocation already exists.';
            $msgType = 'warning';
        }
    } else {
        $msg = 'All fields required.';
        $msgType = 'danger';
    }
}

$deleteId = (int)($_GET['delete_allocation'] ?? 0);
if ($deleteId) {
    $db->prepare("DELETE FROM subject_allocations WHERE id = ?")->execute([$deleteId]);
    redirect('/admin/subjects.php?msg=Allocation removed');
}

$subjects = $db->query("
    SELECT sub.*, c.name as class_name, c.section, u.first_name, u.last_name
    FROM subjects sub
    JOIN classes c ON sub.class_id = c.id
    LEFT JOIN users u ON sub.teacher_id = u.id
    ORDER BY c.name, sub.name
")->fetchAll();

$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$allSubjects = $db->query("SELECT id, name, code FROM subjects ORDER BY name")->fetchAll();
$teachers = $db->query("SELECT u.id, u.first_name, u.last_name FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.status = 'active'")->fetchAll();
$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();

$currentTerm = getCurrentTerm();
$currentSessionId = (int)($currentTerm['session_id'] ?? ($sessions[0]['id'] ?? 0));

$allocations = $db->query("
    SELECT sa.*, u.first_name, u.last_name, c.name as class_name, c.section,
           s.name as subject_name, s.code as subject_code, ac.session_name
    FROM subject_allocations sa
    JOIN users u ON sa.teacher_id = u.id
    JOIN classes c ON sa.class_id = c.id
    JOIN subjects s ON sa.subject_id = s.id
    JOIN academic_sessions ac ON sa.academic_session_id = ac.id
    ORDER BY ac.start_date DESC, c.name, s.name
")->fetchAll();

$msg = sanitizeInput($_GET['msg'] ?? $msg ?? '');
$msgType = $msgType ?? 'success';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-book me-2"></i>Subjects</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectModal">
        <i class="fas fa-plus me-1"></i>Add Subject
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-list me-2"></i>Subjects</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Code</th><th>Subject</th><th>Class</th><th>Teacher</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $s): ?>
                    <tr>
                        <td><strong><?= sanitizeInput($s['code']) ?></strong></td>
                        <td><?= sanitizeInput($s['name']) ?></td>
                        <td><?= sanitizeInput($s['class_name'] . ' ' . ($s['section'] ?? '')) ?></td>
                        <td><?= $s['first_name'] ? sanitizeInput($s['first_name'] . ' ' . $s['last_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editSubject(<?= $s['id'] ?>, '<?= addslashes($s['name']) ?>', '<?= $s['code'] ?>', <?= $s['class_id'] ?>, <?= $s['teacher_id'] ?: 0 ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users-cog me-2"></i>Subject Allocations</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#allocationModal">
            <i class="fas fa-plus me-1"></i>Add Allocation
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Teacher</th><th>Class</th><th>Subject</th><th>Session</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allocations as $a): ?>
                    <tr>
                        <td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td><?= sanitizeInput($a['class_name'] . ' ' . ($a['section'] ?? '')) ?></td>
                        <td><?= sanitizeInput($a['subject_name']) ?> (<?= sanitizeInput($a['subject_code']) ?>)</td>
                        <td><?= sanitizeInput($a['session_name']) ?></td>
                        <td>
                            <a href="?delete_allocation=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this allocation?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($allocations)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No allocations yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="subject_id" id="subject_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectModalTitle">Add Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="name" id="subject_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="code" id="subject_code" class="form-control" required placeholder="e.g., MATH01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" id="subject_class" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Teacher</label>
                        <select name="teacher_id" id="subject_teacher" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="save_subject" value="1">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="allocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Subject Allocation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Teacher</label>
                        <select name="alloc_teacher_id" class="form-select" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="alloc_class_id" class="form-select" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="alloc_subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($allSubjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>"><?= sanitizeInput($sub['name']) ?> (<?= sanitizeInput($sub['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Session</label>
                        <select name="alloc_session_id" class="form-select" required>
                            <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] === $currentSessionId ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="save_allocation" value="1">
                    <button type="submit" class="btn btn-primary">Save Allocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSubject(id, name, code, classId, teacherId) {
    document.getElementById('subject_id').value = id;
    document.getElementById('subject_name').value = name;
    document.getElementById('subject_code').value = code;
    document.getElementById('subject_class').value = classId;
    document.getElementById('subject_teacher').value = teacherId;
    document.getElementById('subjectModalTitle').textContent = 'Edit Subject';
    new bootstrap.Modal(document.getElementById('subjectModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
