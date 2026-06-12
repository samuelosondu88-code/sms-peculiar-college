<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Results Dashboard';
$db = getDB();

$currentTerm = getCurrentTerm();
$currentSessionId = $currentTerm['session_id'] ?? 0;
$currentTermId = $currentTerm['id'] ?? 0;

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$tStmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id"); $tStmt->execute([$currentSessionId]); $terms = $tStmt->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();

$totalWithScores = 0;
$passRate = 0;
$failRate = 0;
$promotionRate = 0;
$classPerformance = [];

if ($currentSessionId && $currentTermId) {
    $sStmt = $db->prepare("
        SELECT COUNT(DISTINCT student_id) as total,
            AVG(avg_score) as overall_avg
        FROM (
            SELECT student_id, AVG(total_score) as avg_score
            FROM result_scores
            WHERE session_id = ? AND term_id = ?
            GROUP BY student_id
        ) t
    "); $sStmt->execute([$currentSessionId, $currentTermId]); $stats = $sStmt->fetch();

    $totalWithScores = (int)$stats['total'];
    $overallAvg = round((float)$stats['overall_avg'], 2);

    $settings = $totalWithScores > 0 ? getResultSettings($db, $currentSessionId, $currentTermId) : ['pass_mark' => 40];
    $passMark = $settings['pass_mark'];

    $pfStmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN avg_score >= ? THEN 1 ELSE 0 END) as pass_count,
            SUM(CASE WHEN avg_score < ? THEN 1 ELSE 0 END) as fail_count,
            COUNT(*) as total_count
        FROM (
            SELECT student_id, AVG(total_score) as avg_score
            FROM result_scores
            WHERE session_id = ? AND term_id = ?
            GROUP BY student_id
        ) t
    "); $pfStmt->execute([$passMark, $passMark, $currentSessionId, $currentTermId]); $passFail = $pfStmt->fetch();

    $passCount = (int)$passFail['pass_count'];
    $failCount = (int)$passFail['fail_count'];
    $totalCount = (int)$passFail['total_count'];
    $passRate = $totalCount > 0 ? round(($passCount / $totalCount) * 100) : 0;
    $failRate = $totalCount > 0 ? round(($failCount / $totalCount) * 100) : 0;

    $pcStmt = $db->prepare("
        SELECT COUNT(*) FROM promotion_results
        WHERE session_id = ? AND promotion_status = 'promoted'
    "); $pcStmt->execute([$currentSessionId]); $promoCount = $pcStmt->fetchColumn();
    $tpStmt = $db->prepare("
        SELECT COUNT(*) FROM promotion_results
        WHERE session_id = ?
    "); $tpStmt->execute([$currentSessionId]); $totalPromo = $tpStmt->fetchColumn();
    $promotionRate = $totalPromo > 0 ? round(((int)$promoCount / $totalPromo) * 100) : 0;

    foreach ($classes as $c) {
        $clsStmt = $db->prepare("
            SELECT AVG(avg_score) as cls_avg
            FROM (
                SELECT student_id, AVG(total_score) as avg_score
                FROM result_scores
                WHERE class_id = ? AND session_id = ? AND term_id = ?
                GROUP BY student_id
            ) t
        "); $clsStmt->execute([$c['id'], $currentSessionId, $currentTermId]); $cls = $clsStmt->fetch();
        if ($cls['cls_avg'] !== null) {
            $classPerformance[] = [
                'class' => $c['name'] . ' ' . $c['section'],
                'average' => round((float)$cls['cls_avg'], 2)
            ];
        }
    }
}

$pendingApprovals = $db->query("
    SELECT COUNT(DISTINCT ra.class_id) FROM result_approvals ra
    WHERE ra.status = 'pending'
")->fetchColumn();

$pendingPromotions = $db->query("
    SELECT COUNT(*) FROM promotion_config WHERE is_active = 1
")->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Results Dashboard</h4>
        <p class="text-muted small mb-0">Academic performance overview</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/results/settings.php" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-cog me-1"></i>Settings</a>
        <a href="<?= BASE_URL ?>/admin/results/approve.php" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-check-double me-1"></i>Approvals <?= $pendingApprovals > 0 ? '<span class="badge bg-danger">' . $pendingApprovals . '</span>' : '' ?></a>
        <a href="<?= BASE_URL ?>/admin/results/pins.php" class="btn btn-sm btn-outline-info me-1"><i class="fas fa-key me-1"></i>PINs</a>
        <a href="<?= BASE_URL ?>/admin/results/promotion.php" class="btn btn-sm btn-outline-success"><i class="fas fa-arrow-up me-1"></i>Promotion</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= $totalStudents ?></div>
            <div class="stat-label">Active Students</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $totalWithScores ?> <small class="fs-6">(<?= $passRate ?>%)</small></div>
            <div class="stat-label">Students with Scores / Pass Rate</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-danger">
            <i class="fas fa-times-circle stat-icon"></i>
            <div class="stat-value"><?= $failRate ?>%</div>
            <div class="stat-label">Fail Rate</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-arrow-up stat-icon"></i>
            <div class="stat-value"><?= $promotionRate ?>%</div>
            <div class="stat-label">Promotion Rate</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-tachometer-alt me-2"></i>Session / Term Selector</div>
            <div class="card-body">
                <form method="GET" class="row g-2" id="sessionForm">
                    <div class="col-md-6">
                        <label class="form-label">Session</label>
                        <select name="session_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $currentSessionId == $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Term</label>
                        <select name="term_id" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($terms as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $currentTermId == $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-link me-2"></i>Quick Links</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6"><a href="<?= BASE_URL ?>/admin/results/manage.php" class="btn btn-primary w-100"><i class="fas fa-list me-1"></i>View All Results</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/admin/results/psychomotor.php" class="btn btn-info w-100 text-white"><i class="fas fa-running me-1"></i>Psychomotor</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/admin/results/affective.php" class="btn btn-secondary w-100"><i class="fas fa-heart me-1"></i>Affective</a></div>
                    <div class="col-6"><a href="<?= BASE_URL ?>/admin/results/comments.php" class="btn btn-dark w-100"><i class="fas fa-comment me-1"></i>Comments</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Average Performance by Class</div>
            <div class="card-body">
                <canvas id="classPerformanceChart" height="250"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-tasks me-2"></i>Pending Actions</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Pending Approvals
                        <span class="badge bg-warning rounded-pill"><?= $pendingApprovals ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Active Promotion Configs
                        <span class="badge bg-info rounded-pill"><?= $pendingPromotions ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Classes with Scores
                        <span class="badge bg-primary rounded-pill"><?= count($classPerformance) ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$chartLabels = json_encode(array_map(fn($c) => $c['class'], $classPerformance));
$chartValues = json_encode(array_map(fn($c) => $c['average'], $classPerformance));
$extraScripts = <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('classPerformanceChart');
    if (ctx) {
        var labels = {$chartLabels};
        var dataValues = {$chartValues};
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Average Score',
                    data: dataValues,
                    backgroundColor: 'rgba(11, 31, 58, 0.7)',
                    borderColor: 'rgba(212, 175, 55, 1)',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100 },
                    x: { ticks: { maxRotation: 45 } }
                }
            }
        });
    }
});
</script>
SCRIPT;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
