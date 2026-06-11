<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Children';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT p.id as parent_id FROM parents p WHERE p.user_id = ?");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

$children = [];
if ($parent) {
    $children = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, s.admission_no, c.name as class_name, c.section, s.id as student_id
        FROM student_parents sp
        JOIN students s ON sp.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON s.class_id = c.id
        WHERE sp.parent_id = ?
    ");
    $children->execute([$parent['parent_id']]);
    $children = $children->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-child me-2"></i>My Children</h4>
</div>

<?php if (empty($children)): ?>
<div class="alert alert-info">No children linked to your account. Please contact the school administration.</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($children as $c): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="avatar-circle bg-primary mx-auto mb-3" style="width:70px;height:70px;font-size:28px;">
                    <?= strtoupper(substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold"><?= sanitizeInput($c['first_name'] . ' ' . $c['last_name']) ?></h5>
                <p class="text-muted mb-1"><?= sanitizeInput($c['class_name'] . ' ' . ($c['section'] ?? '')) ?></p>
                <p class="small text-muted">Admission: <?= sanitizeInput($c['admission_no']) ?></p>
                <hr>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="/parent/attendance.php?student_id=<?= $c['student_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-check-circle me-1"></i>Attendance</a>
                    <a href="/parent/results.php?student_id=<?= $c['student_id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-alt me-1"></i>Results</a>
                    <a href="/parent/fees.php?student_id=<?= $c['student_id'] ?>" class="btn btn-sm btn-outline-warning"><i class="fas fa-money-bill me-1"></i>Fees</a>
                    <a href="/parent/timetable.php?student_id=<?= $c['student_id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-calendar-alt me-1"></i>Timetable</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
