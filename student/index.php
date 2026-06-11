<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Student Dashboard';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT s.*, c.name as class_name, c.section FROM students s JOIN classes c ON s.class_id = c.id WHERE s.user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present'");
$stmt->execute([$student['id'] ?? 0]);
$presentDays = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
$stmt->execute([$student['id'] ?? 0]);
$totalDays = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM assignments a JOIN subjects s ON a.subject_id = s.id WHERE s.class_id = ? AND a.due_date >= NOW()");
$stmt->execute([$student['class_id'] ?? 0]);
$pendingAssignments = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT r.score, r.grade, sub.name as subject_name, e.name as exam_name FROM results r JOIN exams e ON r.exam_id = e.id JOIN subjects sub ON r.subject_id = sub.id WHERE r.student_id = ? ORDER BY e.created_at DESC LIMIT 5");
$stmt->execute([$student['id'] ?? 0]);
$recentResults = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Student Dashboard</h4>
        <p class="text-muted small">Welcome, <?= sanitizeInput($_SESSION['user_name']) ?>!</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= sanitizeInput($student['admission_no'] ?? '-') ?></div>
            <div class="stat-label">Admission No.</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-school stat-icon"></i>
            <div class="stat-value"><?= sanitizeInput(($student['class_name'] ?? '') . ' ' . ($student['section'] ?? '')) ?></div>
            <div class="stat-label">Class</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-calendar-check stat-icon"></i>
            <div class="stat-value"><?= $totalDays > 0 ? round(($presentDays / $totalDays) * 100) . '%' : '0%' ?></div>
            <div class="stat-label">Attendance</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-tasks stat-icon"></i>
            <div class="stat-value"><?= $pendingAssignments ?></div>
            <div class="stat-label">Pending Assignments</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Quick Links</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6"><a href="<?= BASE_URL ?>/student/timetable.php" class="btn btn-outline-primary w-100"><i class="fas fa-calendar-alt me-1"></i>Timetable</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/student/attendance.php" class="btn btn-outline-success w-100"><i class="fas fa-check-circle me-1"></i>Attendance</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/student/results.php" class="btn btn-outline-warning w-100"><i class="fas fa-file-alt me-1"></i>Results</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/student/assignments.php" class="btn btn-outline-info w-100"><i class="fas fa-tasks me-1"></i>Assignments</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/student/fees.php" class="btn btn-outline-danger w-100"><i class="fas fa-money-bill me-1"></i>Fees</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/student/library.php" class="btn btn-outline-secondary w-100"><i class="fas fa-book-open me-1"></i>Library</a></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Recent Results</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Subject</th><th>Exam</th><th>Score</th><th>Grade</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentResults as $r): ?>
                            <tr>
                                <td><?= sanitizeInput($r['subject_name']) ?></td>
                                <td><?= sanitizeInput($r['exam_name']) ?></td>
                                <td><?= $r['score'] ?? '-' ?></td>
                                <td><strong><?= $r['grade'] ?? '-' ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentResults)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No results yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
