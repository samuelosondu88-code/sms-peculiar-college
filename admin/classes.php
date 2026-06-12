<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Manage Classes';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_class'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $section = sanitizeInput($_POST['section'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 40);
    $teacherId = (int)($_POST['class_teacher_id'] ?? 0);

    if (isset($_POST['class_id']) && $_POST['class_id']) {
        $stmt = $db->prepare("UPDATE classes SET name = ?, section = ?, capacity = ?, class_teacher_id = ? WHERE id = ?");
        $stmt->execute([$name, $section, $capacity, $teacherId ?: null, $_POST['class_id']]);
    } else {
        $sessionId = $db->query("SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1")->fetchColumn();
        $stmt = $db->prepare("INSERT INTO classes (name, section, capacity, class_teacher_id, academic_session_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $section, $capacity, $teacherId ?: null, $sessionId]);
    }
    redirect('/admin/classes.php?msg=Class saved');
}

$classes = $db->query("
    SELECT c.*, u.first_name, u.last_name,
    (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 'active') as student_count
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.user_id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY c.name
")->fetchAll();

$teachers = $db->query("SELECT u.id, u.first_name, u.last_name FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.status = 'active'")->fetchAll();
$msg = sanitizeInput($_GET['msg'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chalkboard me-2"></i>Classes</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classModal">
        <i class="fas fa-plus me-1"></i>Add Class
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-success"><?= $msg ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Capacity</th>
                        <th>Students</th>
                        <th>Class Teacher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $c): ?>
                    <tr>
                        <td><strong><?= sanitizeInput($c['name']) ?></strong></td>
                        <td><?= sanitizeInput($c['section'] ?? '-') ?></td>
                        <td><?= $c['capacity'] ?></td>
                        <td><?= $c['student_count'] ?></td>
                        <td><?= $c['first_name'] ? sanitizeInput($c['first_name'] . ' ' . $c['last_name']) : '<span class="text-muted">Not assigned</span>' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editClass(<?= $c['id'] ?>, '<?= addslashes($c['name']) ?>', '<?= addslashes($c['section'] ?? '') ?>', <?= $c['capacity'] ?>, <?= $c['class_teacher_id'] ?: 0 ?>)">
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

<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="class_id" id="class_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="classModalTitle">Add Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="name" id="class_name" class="form-control" required placeholder="e.g., JSS1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Section</label>
                        <input type="text" name="section" id="class_section" class="form-control" placeholder="e.g., A, B, Gold">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" id="class_capacity" class="form-control" value="40" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class Teacher</label>
                        <select name="class_teacher_id" id="class_teacher" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="save_class" value="1">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editClass(id, name, section, capacity, teacherId) {
    document.getElementById('class_id').value = id;
    document.getElementById('class_name').value = name;
    document.getElementById('class_section').value = section;
    document.getElementById('class_capacity').value = capacity;
    document.getElementById('class_teacher').value = teacherId;
    document.getElementById('classModalTitle').textContent = 'Edit Class';
    new bootstrap.Modal(document.getElementById('classModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
