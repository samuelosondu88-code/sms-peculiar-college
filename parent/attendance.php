<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Attendance Monitoring';
$db = getDB();
$userId = $_SESSION['user_id'];
$studentId = (int)($_GET['student_id'] ?? 0);

$stmt = $db->prepare("SELECT p.id FROM parents p WHERE p.user_id = ?");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

$children = [];
if ($parent) {
    $children = $db->prepare("SELECT s.id, u.first_name, u.last_name FROM student_parents sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE sp.parent_id = ?");
    $children->execute([$parent['id']]);
    $children = $children->fetchAll();
    $childIds = array_column($children, 'id');
    if ($studentId && !in_array($studentId, $childIds)) $studentId = 0;
    if (!$studentId && !empty($children)) $studentId = $children[0]['id'];
}

$attendance = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
if ($studentId) {
    $att = $db->prepare("SELECT date, status FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 50");
    $att->execute([$studentId]);
    $attendance = $att->fetchAll();
    foreach ($attendance as $a) {
        if (isset($summary[$a['status']])) $summary[$a['status']]++;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-check-circle me-2"></i>Attendance Monitoring</h4>
</div>

<?php if (!empty($children)): ?>
<form method="GET" class="row g-3 mb-4">
    <div class="col-md-4">
        <label class="form-label">Select Child</label>
        <select name="student_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $studentId === $c['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($c['first_name'] . ' ' . $c['last_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card stat-success"><i class="fas fa-check stat-icon"></i><div class="stat-value"><?= $summary['present'] ?></div><div class="stat-label">Present</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-danger"><i class="fas fa-times stat-icon"></i><div class="stat-value"><?= $summary['absent'] ?></div><div class="stat-label">Absent</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-warning"><i class="fas fa-clock stat-icon"></i><div class="stat-value"><?= $summary['late'] ?></div><div class="stat-label">Late</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-info"><i class="fas fa-percentage stat-icon"></i><div class="stat-value"><?= array_sum($summary) > 0 ? round(($summary['present'] / array_sum($summary)) * 100) . '%' : '0%' ?></div><div class="stat-label">Rate</div></div></div>
</div>

<div class="card">
    <div class="card-header">Attendance History</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($attendance as $a): ?>
                    <tr><td><?= formatDate($a['date']) ?></td><td><?= getStatusBadge($a['status']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (empty($attendance)): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No records</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">No children linked to your account.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
