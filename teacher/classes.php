<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Classes';
$db = getDB();
$teacherId = getTeacherId();

$classes = $db->prepare("
    SELECT c.id, c.name, c.section, c.capacity,
        (SELECT COUNT(*) FROM students WHERE class_id = c.id AND status = 'active') as student_count,
        (SELECT COUNT(*) FROM subject_allocations WHERE teacher_id = ? AND class_id = c.id) as subject_count
    FROM classes c
    WHERE c.id IN (SELECT DISTINCT class_id FROM subject_allocations WHERE teacher_id = ?)
    ORDER BY c.name
");
$classes->execute([$teacherId, $teacherId]);
$myClasses = $classes->fetchAll();

$totalStudents = $db->prepare("SELECT COUNT(DISTINCT s.id) FROM students s JOIN subject_allocations sa ON s.class_id = sa.class_id WHERE sa.teacher_id = ? AND s.status = 'active'");
$totalStudents->execute([$teacherId]);
$totalStudents = $totalStudents->fetchColumn();

$subjects = $db->prepare("SELECT s.*, c.name as class_name FROM subject_allocations sa JOIN subjects s ON sa.subject_id = s.id JOIN classes c ON sa.class_id = c.id WHERE sa.teacher_id = ?");
$subjects->execute([$teacherId]);
$mySubjects = $subjects->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chalkboard me-2"></i>My Classes</h4>
    <span class="badge bg-primary fs-6"><?= count($myClasses) ?> Classes | <?= $totalStudents ?> Students</span>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($myClasses as $c): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="fw-bold mb-0"><?= sanitizeInput($c['name']) ?></h5>
                    <span class="badge bg-info"><?= sanitizeInput($c['section'] ?? 'N/A') ?></span>
                </div>
                <p class="small text-muted mb-1">Students: <?= $c['student_count'] ?? 0 ?> / <?= $c['capacity'] ?? '-' ?></p>
                <p class="small text-muted mb-3">Subjects: <?= $c['subject_count'] ?? 0 ?></p>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/teacher/attendance.php?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-check-circle me-1"></i>Attendance</a>
                    <a href="<?= BASE_URL ?>/teacher/grades.php?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-star me-1"></i>Grades</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($myClasses)): ?>
    <div class="col-12">
        <div class="card"><div class="card-body text-center py-5">
            <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
            <p class="text-muted mb-0">You have not been assigned to any classes yet.</p>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-book me-2"></i>My Subjects</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Subject</th><th>Code</th><th>Class</th></tr></thead>
            <tbody>
                <?php foreach ($mySubjects as $s): ?>
                <tr><td><?= sanitizeInput($s['name']) ?></td><td><small><?= sanitizeInput($s['code'] ?? '-') ?></small></td><td><span class="badge bg-secondary"><?= sanitizeInput($s['class_name']) ?></span></td></tr>
                <?php endforeach; ?>
                <?php if (empty($mySubjects)): ?><tr><td colspan="3" class="text-center text-muted py-3">No subjects assigned.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
