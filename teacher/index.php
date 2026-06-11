<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Teacher Dashboard';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT c.id, c.name, c.section, (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 'active') as student_count FROM classes c WHERE c.class_teacher_id = ?");
$stmt->execute([$userId]);
$myClasses = $stmt->fetchAll();

$stmt = $db->prepare("SELECT s.name FROM subjects s WHERE s.teacher_id = ?");
$stmt->execute([$userId]);
$mySubjects = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON a.student_id = s.id JOIN classes c ON s.class_id = c.id WHERE c.class_teacher_id = ? AND a.date = CURDATE()");
$stmt->execute([$userId]);
$todayAttendance = (int)$stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Teacher Dashboard</h4>
        <p class="text-muted small">Welcome back, <?= sanitizeInput($_SESSION['user_name']) ?>!</p>
    </div>
    <a href="<?= BASE_URL ?>/teacher/attendance.php" class="btn btn-primary">
        <i class="fas fa-check-circle me-1"></i>Mark Attendance
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-primary">
            <i class="fas fa-chalkboard stat-icon"></i>
            <div class="stat-value"><?= count($myClasses) ?></div>
            <div class="stat-label">My Classes</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <i class="fas fa-book stat-icon"></i>
            <div class="stat-value"><?= count($mySubjects) ?></div>
            <div class="stat-label">Subjects Taught</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-info">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $todayAttendance ?></div>
            <div class="stat-label">Today's Attendance</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($myClasses as $class): ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold"><?= sanitizeInput($class['name']) ?> <?= sanitizeInput($class['section'] ?? '') ?></h5>
                <p class="text-muted"><?= $class['student_count'] ?> Students</p>
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/teacher/attendance.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-primary">Attendance</a>
                    <a href="<?= BASE_URL ?>/teacher/grades.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-success">Grades</a>
                    <a href="<?= BASE_URL ?>/teacher/assignments.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-info">Assignments</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($myClasses)): ?>
    <div class="col-12">
        <div class="alert alert-info">You haven't been assigned to any classes yet.</div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
