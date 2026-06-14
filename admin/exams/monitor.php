<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/exam_security.php';
$pageTitle = 'Exam Monitoring Dashboard';
$db = getDB();

$filterTeacher = (int)($_GET['teacher_id'] ?? 0);
$filterExam = (int)($_GET['exam_id'] ?? 0);

$teachers = $db->query("SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u JOIN teacher_exams te ON u.id = te.teacher_id ORDER BY u.last_name")->fetchAll();

$exams = [];
if ($filterTeacher) {
    $stmt = $db->prepare("SELECT te.id, te.title, sub.name as subject_name FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id WHERE te.teacher_id = ? ORDER BY te.created_at DESC");
    $stmt->execute([$filterTeacher]);
    $exams = $stmt->fetchAll();
}

$allActive = $db->query("SELECT COUNT(*) FROM exam_attempts WHERE status = 'in_progress'")->fetchColumn();
$totalAttempts = $db->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn();
$totalViolations = $db->query("SELECT COUNT(*) FROM exam_activity_logs WHERE event_type IN ('tab_switch','fullscreen_exit','camera_violation','multiple_faces','face_absent','copy_attempt','right_click')")->fetchColumn();
$highRiskCount = $db->query("SELECT COUNT(*) FROM exam_integrity_scores WHERE risk_level IN ('high','critical')")->fetchColumn();

$summaryByTeacher = $db->query("
    SELECT u.first_name, u.last_name,
        COUNT(DISTINCT te.id) as exam_count,
        COUNT(DISTINCT ea.id) as attempt_count,
        SUM(CASE WHEN ea.status = 'in_progress' THEN 1 ELSE 0 END) as active_count
    FROM users u
    JOIN teacher_exams te ON u.id = te.teacher_id
    LEFT JOIN exam_attempts ea ON te.id = ea.exam_id
    GROUP BY u.id
    ORDER BY active_count DESC
")->fetchAll();

$recentActivity = $db->query("
    SELECT eal.*, ea.exam_id, u.first_name, u.last_name
    FROM exam_activity_logs eal
    JOIN exam_attempts ea ON eal.attempt_id = ea.id
    JOIN users u ON ea.student_id = u.id
    ORDER BY eal.created_at DESC
    LIMIT 50
")->fetchAll();

$monitorData = [];
if ($filterExam) {
    $monitorData = getActiveExamsForMonitoring($db, null, 200);
    $monitorData = array_filter($monitorData, fn($m) => $m['exam_id'] == $filterExam);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-tv me-2"></i>Exam Monitoring Dashboard</h4>
        <p class="text-muted small mb-0">System-wide examination integrity overview</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-value"><?= $allActive ?></div>
            <div class="stat-label">Active Candidates</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-value"><?= $totalAttempts ?></div>
            <div class="stat-label">Total Attempts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-danger">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= $totalViolations ?></div>
            <div class="stat-label">Total Violations</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-shield-alt stat-icon"></i>
            <div class="stat-value"><?= $highRiskCount ?></div>
            <div class="stat-label">High/Critical Risk</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chalkboard-teacher me-2"></i>Teachers Summary</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Teacher</th><th>Exams</th><th>Attempts</th><th>Active Now</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaryByTeacher as $t): ?>
                            <tr>
                                <td><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></td>
                                <td><?= $t['exam_count'] ?></td>
                                <td><?= $t['attempt_count'] ?></td>
                                <td><span class="badge bg-<?= $t['active_count'] > 0 ? 'warning' : 'secondary' ?>"><?= $t['active_count'] ?></span></td>
                                <td><a href="?teacher_id=<?= $t['teacher_id'] ?? '' ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-clock me-2"></i>Recent Activity</div>
            <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Time</th><th>Student</th><th>Event</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $act): ?>
                        <tr>
                            <td><small><?= date('H:i:s', strtotime($act['created_at'])) ?></small></td>
                            <td><small><?= sanitizeInput($act['first_name'] . ' ' . $act['last_name']) ?></small></td>
                            <td><span class="badge bg-<?= strpos($act['event_type'], 'violation') !== false || in_array($act['event_type'], ['tab_switch','fullscreen_exit','multiple_faces','copy_attempt','right_click','keyboard_shortcut']) ? 'danger' : 'secondary' ?>"><?= sanitizeInput(str_replace('_', ' ', $act['event_type'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($filterTeacher): ?>
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Teacher</label>
                <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filterTeacher === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Exam</label>
                <select name="exam_id" class="form-select">
                    <option value="">All exams</option>
                    <?php foreach ($exams as $ex): ?>
                    <option value="<?= $ex['id'] ?>" <?= $filterExam === $ex['id'] ? 'selected' : '' ?>><?= sanitizeInput($ex['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($filterExam && !empty($monitorData)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fas fa-list me-2"></i>Active Candidates</span>
        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Refresh</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Started</th>
                        <th>Last Activity</th>
                        <th>Violations</th>
                        <th>Integrity</th>
                        <th>Risk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monitorData as $m): ?>
                    <?php $riskColor = ['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'dark']; ?>
                    <tr>
                        <td><?= sanitizeInput($m['first_name'] . ' ' . $m['last_name']) ?></td>
                        <td><small><?= $m['started_at'] ? date('H:i', strtotime($m['started_at'])) : '—' ?></small></td>
                        <td><small><?= $m['last_activity_at'] ? date('H:i:s', strtotime($m['last_activity_at'])) : '—' ?></small></td>
                        <td><span class="badge bg-<?= ($m['total_violations'] ?? 0) > 0 ? 'danger' : 'success' ?>"><?= (int)($m['total_violations'] ?? 0) ?></span></td>
                        <td><strong><?= $m['integrity_score'] !== null ? $m['integrity_score'] . '%' : '—' ?></strong></td>
                        <td><span class="badge bg-<?= $riskColor[$m['risk_level'] ?? 'low'] ?>"><?= ucfirst($m['risk_level'] ?? 'low') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
