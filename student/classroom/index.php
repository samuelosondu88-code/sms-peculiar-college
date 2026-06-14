<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'My Classrooms';
$db = getDB();
$studentId = getStudentId();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_class'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if ($code) {
        $vc = $db->prepare("SELECT id FROM virtual_classes WHERE code = ? AND status = 'active'");
        $vc->execute([$code]);
        $classId = $vc->fetchColumn();
        if ($classId) {
            $check = $db->prepare("SELECT id FROM class_enrollments WHERE virtual_class_id = ? AND student_id = ?");
            $check->execute([$classId, $studentId]);
            if (!$check->fetch()) {
                $db->prepare("INSERT INTO class_enrollments (virtual_class_id, student_id) VALUES (?, ?)")->execute([$classId, $studentId]);
                $msg = 'Successfully joined the classroom!';
            } else {
                $msg = 'You are already enrolled in this class.';
                $msgType = 'warning';
            }
        } else {
            $msg = 'Invalid or inactive class code.';
            $msgType = 'danger';
        }
    }
}

$classes = $db->prepare("
    SELECT vc.*, s.name as subject_name, s.code as subject_code,
           c.name as class_name, c.section, u.first_name as t_first, u.last_name as t_last,
           (SELECT COUNT(*) FROM class_materials cm WHERE cm.virtual_class_id = vc.id) as material_count,
           (SELECT COUNT(*) FROM class_assignments ca WHERE ca.virtual_class_id = vc.id) as assignment_count
    FROM class_enrollments ce
    JOIN virtual_classes vc ON ce.virtual_class_id = vc.id
    JOIN subjects s ON vc.subject_id = s.id
    JOIN classes c ON vc.class_id = c.id
    JOIN teachers t ON vc.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE ce.student_id = ? AND ce.status = 'active' AND vc.status = 'active'
    ORDER BY vc.created_at DESC
");
$classes->execute([$studentId]);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>My Classrooms</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#joinModal"><i class="fas fa-plus me-1"></i>Join Class</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if ($classes->rowCount() === 0): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-school fa-4x text-muted mb-3"></i>
        <p class="text-muted mb-2">You are not enrolled in any classroom.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#joinModal"><i class="fas fa-plus me-1"></i>Join with Code</button>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($classes as $vc): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-1"><?= sanitizeInput($vc['name']) ?></h5>
                <p class="text-muted small mb-2">
                    <?= sanitizeInput($vc['subject_name']) ?> | <?= sanitizeInput($vc['class_name'] . ' ' . $vc['section']) ?><br>
                    Teacher: <?= sanitizeInput($vc['t_first'] . ' ' . $vc['t_last']) ?>
                </p>
                <span class="badge bg-info me-1"><?= $vc['material_count'] ?> Materials</span>
                <span class="badge bg-warning"><?= $vc['assignment_count'] ?> Assignments</span>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="view.php?id=<?= $vc['id'] ?>" class="btn btn-primary btn-sm w-100"><i class="fas fa-door-open me-1"></i>Enter Classroom</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="joinModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Join Classroom</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-muted">Enter the classroom code provided by your teacher.</p>
                <div class="mb-3">
                    <label class="form-label">Class Code</label>
                    <input type="text" name="code" class="form-control" required placeholder="e.g., A1B2C3D4" style="text-transform:uppercase;font-size:1.2rem;letter-spacing:2px">
                </div>
            </div>
            <div class="modal-footer"><input type="hidden" name="join_class" value="1"><button type="submit" class="btn btn-primary">Join</button></div>
        </form>
    </div></div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
