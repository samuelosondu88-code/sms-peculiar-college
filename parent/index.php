<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Parent Dashboard';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT p.id as parent_id FROM parents p WHERE p.user_id = ?");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

$children = [];
if ($parent) {
    $stmt = $db->prepare("
        SELECT u.first_name, u.last_name, u.email, u.phone, s.admission_no, c.name as class_name, c.section, s.id as student_id
        FROM student_parents sp
        JOIN students s ON sp.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON s.class_id = c.id
        WHERE sp.parent_id = ?
    ");
    $stmt->execute([$parent['parent_id']]);
    $children = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Parent Dashboard</h4>
        <p class="text-muted small">Welcome, <?= sanitizeInput($_SESSION['user_name']) ?>!</p>
    </div>
</div>

<?php if (empty($children)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>No children linked to your account yet. Contact the school administration.
</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($children as $child): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar-circle bg-primary me-3"><?= strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)) ?></div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= sanitizeInput($child['first_name'] . ' ' . $child['last_name']) ?></h5>
                        <small class="text-muted"><?= sanitizeInput($child['class_name'] . ' ' . ($child['section'] ?? '')) ?></small>
                    </div>
                </div>
                <p class="small text-muted mb-2"><i class="fas fa-id-card me-1"></i>Admission: <?= sanitizeInput($child['admission_no']) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="/parent/attendance.php?student_id=<?= $child['student_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-check-circle me-1"></i>Attendance</a>
                    <a href="/parent/results.php?student_id=<?= $child['student_id'] ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-alt me-1"></i>Results</a>
                    <a href="/parent/fees.php?student_id=<?= $child['student_id'] ?>" class="btn btn-sm btn-outline-warning"><i class="fas fa-money-bill me-1"></i>Fees</a>
                    <a href="/parent/timetable.php?student_id=<?= $child['student_id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-calendar-alt me-1"></i>Timetable</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
