<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/exam_security.php';
$pageTitle = 'Live Exam Monitoring';
$db = getDB();
$userId = $_SESSION['user_id'];

$examId = (int)($_GET['exam_id'] ?? 0);
$exam = null;
if ($examId) {
    $stmt = $db->prepare("SELECT * FROM teacher_exams WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$examId, $userId]);
    $exam = $stmt->fetch();
}

$activeExams = $db->prepare("SELECT te.id, te.title, sub.name as subject_name, c.name as class_name, c.section,
    (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = te.id AND ea.status = 'in_progress') as active_count,
    (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = te.id) as total_attempts
    FROM teacher_exams te
    JOIN subjects sub ON te.subject_id = sub.id
    JOIN classes c ON te.class_id = c.id
    WHERE te.teacher_id = ?
    ORDER BY te.created_at DESC");
$activeExams->execute([$userId]);
$examList = $activeExams->fetchAll();

$monitorData = [];
if ($examId && $exam) {
    $monitorData = getActiveExamsForMonitoring($db, $userId, 100);
    $violationSummary = getExamViolationSummary($db, $examId);
    $integrityReport = getExamIntegrityReport($db, $examId);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-tv me-2"></i>Live Exam Monitoring</h4>
        <p class="text-muted small mb-0">Real-time exam integrity monitoring dashboard</p>
    </div>
    <div>
        <select id="examSelector" class="form-select" onchange="if(this.value) window.location.href='monitor.php?exam_id='+this.value;">
            <option value="">Select exam to monitor...</option>
            <?php foreach ($examList as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $examId === $ex['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($ex['title']) ?> (<?= $ex['active_count'] ?> active)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($examId && $exam): ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= count(array_filter($monitorData, fn($m) => $m['status'] === 'in_progress')) ?></div>
            <div class="stat-label">Active Candidates</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-danger">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= count(array_filter($monitorData, fn($m) => ($m['risk_level'] ?? 'low') === 'critical')) ?></div>
            <div class="stat-label">Critical Risk</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-value"><?= count(array_filter($monitorData, fn($m) => ($m['risk_level'] ?? 'low') === 'high' || ($m['risk_level'] ?? 'low') === 'medium')) ?></div>
            <div class="stat-label">Suspicious</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= count(array_filter($monitorData, fn($m) => ($m['risk_level'] ?? 'low') === 'low' || !$m['risk_level'])) ?></div>
            <div class="stat-label">Clean</div>
        </div>
    </div>
</div>

<div class="card mb-4">
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
                        <th>Admission No</th>
                        <th>Started</th>
                        <th>Last Activity</th>
                        <th>Violations</th>
                        <th>Integrity</th>
                        <th>Risk</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monitorData)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No active candidates</td></tr>
                    <?php else: ?>
                    <?php foreach ($monitorData as $m): ?>
                    <?php $riskColor = ['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'dark']; ?>
                    <tr>
                        <td><?= sanitizeInput($m['first_name'] . ' ' . $m['last_name']) ?></td>
                        <td><?= sanitizeInput($m['admission_no'] ?? '—') ?></td>
                        <td><small><?= $m['started_at'] ? date('H:i', strtotime($m['started_at'])) : '—' ?></small></td>
                        <td><small><?= $m['last_activity_at'] ? date('H:i:s', strtotime($m['last_activity_at'])) : '—' ?></small></td>
                        <td><span class="badge bg-<?= ($m['total_violations'] ?? 0) > 0 ? 'danger' : 'success' ?>"><?= (int)($m['total_violations'] ?? 0) ?></span></td>
                        <td><strong><?= $m['integrity_score'] !== null ? $m['integrity_score'] . '%' : '—' ?></strong></td>
                        <td><span class="badge bg-<?= $riskColor[$m['risk_level'] ?? 'low'] ?>"><?= ucfirst($m['risk_level'] ?? 'low') ?></span></td>
                        <td>
                            <a href="results.php?exam_id=<?= $examId ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Violation Summary</div>
            <div class="card-body">
                <?php if (empty($violationSummary)): ?>
                <p class="text-muted text-center mb-0">No violations recorded</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Violation Type</th><th class="text-end">Count</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($violationSummary as $v): ?>
                            <tr>
                                <td><?= sanitizeInput(str_replace('_', ' ', ucfirst($v['event_type']))) ?></td>
                                <td class="text-end"><span class="badge bg-<?= $v['cnt'] > 5 ? 'danger' : ($v['cnt'] > 0 ? 'warning' : 'secondary') ?>"><?= $v['cnt'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-shield-alt me-2"></i>Integrity Scores</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Student</th><th class="text-end">Score</th><th>Risk</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($integrityReport)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No data yet</td></tr>
                            <?php else: ?>
                            <?php foreach (array_slice($integrityReport, 0, 20) as $ir): ?>
                            <?php $rc = ['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'dark']; ?>
                            <tr>
                                <td><small><?= sanitizeInput($ir['first_name'] . ' ' . $ir['last_name']) ?></small></td>
                                <td class="text-end"><strong><?= $ir['overall_score'] !== null ? $ir['overall_score'] . '%' : '—' ?></strong></td>
                                <td><span class="badge bg-<?= $rc[$ir['risk_level'] ?? 'low'] ?>"><?= ucfirst($ir['risk_level'] ?? 'low') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row g-3">
    <?php foreach ($examList as $ex): ?>
    <?php $hasActive = $ex['active_count'] > 0; ?>
    <div class="col-md-4">
        <div class="card h-100 <?= $hasActive ? 'border-warning' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitizeInput($ex['title']) ?></h6>
                    <?php if ($hasActive): ?>
                    <span class="badge bg-warning text-dark pulse-dot"><i class="fas fa-circle me-1"></i>Live</span>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mb-1"><?= sanitizeInput($ex['subject_name']) ?> — <?= sanitizeInput($ex['class_name'] . ' ' . $ex['section']) ?></p>
                <p class="small mb-2">
                    <span class="badge bg-primary"><?= $ex['active_count'] ?> active</span>
                    <span class="badge bg-secondary"><?= $ex['total_attempts'] ?> total</span>
                </p>
                <a href="monitor.php?exam_id=<?= $ex['id'] ?>" class="btn btn-sm btn-<?= $hasActive ? 'warning' : 'outline-secondary' ?> w-100">
                    <i class="fas fa-tv me-1"></i><?= $hasActive ? 'Monitor Live' : 'View Report' ?>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($examList)): ?>
    <div class="col-12"><div class="alert alert-info">No exams found. <a href="create.php">Create one now</a>.</div></div>
    <?php endif; ?>
</div>

<?php endif; ?>

<style>
.pulse-dot .fa-circle { animation: pulseDot 1.5s infinite; font-size: 8px; vertical-align: middle; }
@keyframes pulseDot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
