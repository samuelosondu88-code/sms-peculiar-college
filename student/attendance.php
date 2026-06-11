<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Attendance';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT s.id, s.class_id, c.name as class_name FROM students s JOIN classes c ON s.class_id = c.id WHERE s.user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

$attendance = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];

if ($student) {
    $stmt = $db->prepare("SELECT date, status FROM attendance WHERE student_id = ? ORDER BY date DESC");
    $stmt->execute([$student['id']]);
    $attendance = $stmt->fetchAll();
    foreach ($attendance as $a) {
        if (isset($summary[$a['status']])) $summary[$a['status']]++;
    }
}

$total = array_sum($summary);
$percent = $total > 0 ? round(($summary['present'] / $total) * 100) : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-check-circle me-2"></i>My Attendance</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-check stat-icon"></i>
            <div class="stat-value"><?= $summary['present'] ?></div>
            <div class="stat-label">Present</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-danger">
            <i class="fas fa-times stat-icon"></i>
            <div class="stat-value"><?= $summary['absent'] ?></div>
            <div class="stat-label">Absent</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value"><?= $summary['late'] ?></div>
            <div class="stat-label">Late</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-value"><?= $percent ?>%</div>
            <div class="stat-label">Attendance Rate</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Attendance History</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance as $a): ?>
                    <tr>
                        <td><?= formatDate($a['date']) ?></td>
                        <td><?= getStatusBadge($a['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($attendance)): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No attendance records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
