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
$subjectPerformance = [];
$termTrend = [];

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

    $pcStmt = $db->prepare("SELECT COUNT(*) FROM promotion_results WHERE session_id = ? AND promotion_status = 'promoted'");
    $pcStmt->execute([$currentSessionId]); $promoCount = $pcStmt->fetchColumn();
    $tpStmt = $db->prepare("SELECT COUNT(*) FROM promotion_results WHERE session_id = ?");
    $tpStmt->execute([$currentSessionId]); $totalPromo = $tpStmt->fetchColumn();
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

    $subjStmt = $db->prepare("
        SELECT s.name, AVG(rs.total_score) as subj_avg
        FROM result_scores rs
        JOIN subjects s ON rs.subject_id = s.id
        WHERE rs.session_id = ? AND rs.term_id = ?
        GROUP BY rs.subject_id
        ORDER BY subj_avg DESC
        LIMIT 10
    ");
    $subjStmt->execute([$currentSessionId, $currentTermId]);
    $subjectPerformance = $subjStmt->fetchAll();

    $allTermsStmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id");
    $allTermsStmt->execute([$currentSessionId]);
    $allTerms = $allTermsStmt->fetchAll();
    foreach ($allTerms as $at) {
        $avgStmt = $db->prepare("SELECT AVG(avg_score) FROM (SELECT AVG(total_score) as avg_score FROM result_scores WHERE session_id = ? AND term_id = ? GROUP BY student_id) t");
        $avgStmt->execute([$currentSessionId, $at['id']]);
        $termAvg = $avgStmt->fetchColumn();
        if ($termAvg !== null && $termAvg !== false) {
            $termTrend[] = ['term' => $at['term_name'], 'average' => round((float)$termAvg, 2)];
        }
    }
}

$pendingApprovals = $db->query("SELECT COUNT(DISTINCT ra.class_id) FROM result_approvals ra WHERE ra.status = 'pending'")->fetchColumn();
$pendingPromotions = $db->query("SELECT COUNT(*) FROM promotion_config WHERE is_active = 1")->fetchColumn();

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
        <a href="<?= BASE_URL ?>/admin/results/promotion.php" class="btn btn-sm btn-outline-success me-1"><i class="fas fa-arrow-up me-1"></i>Promotion</a>
        <a href="<?= BASE_URL ?>/admin/results/pdf.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf me-1"></i>PDF</a>
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
            <div class="card-header"><i class="fas fa-link me-2"></i>Result Management Links</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-4"><a href="<?= BASE_URL ?>/admin/results/manage.php" class="btn btn-primary w-100 btn-sm"><i class="fas fa-list me-1"></i>Manage</a></div>
                    <div class="col-4"><a href="<?= BASE_URL ?>/admin/results/psychomotor.php" class="btn btn-info w-100 btn-sm text-white"><i class="fas fa-running me-1"></i>Psychomotor</a></div>
                    <div class="col-4"><a href="<?= BASE_URL ?>/admin/results/affective.php" class="btn btn-secondary w-100 btn-sm"><i class="fas fa-heart me-1"></i>Affective</a></div>
                    <div class="col-4"><a href="<?= BASE_URL ?>/admin/results/comments.php" class="btn btn-dark w-100 btn-sm"><i class="fas fa-comment me-1"></i>Comments</a></div>
                    <div class="col-4"><a href="<?= BASE_URL ?>/admin/results/annual.php" class="btn btn-success w-100 btn-sm"><i class="fas fa-calendar-alt me-1"></i>Annual</a></div>
                    <div class="col-4"><a href="<?= BASE_URL ?>/admin/results/import.php" class="btn btn-outline-primary w-100 btn-sm"><i class="fas fa-file-import me-1"></i>Import</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Average Performance by Class</div>
            <div class="card-body">
                <canvas id="classPerformanceChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Pass / Fail Distribution</div>
            <div class="card-body">
                <canvas id="passFailChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line me-2"></i>Term Trend (Session Avg)</div>
            <div class="card-body">
                <canvas id="termTrendChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-book me-2"></i>Subject Performance (Top 10)</div>
            <div class="card-body">
                <canvas id="subjectChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
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
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Broadcast History
                        <a href="<?= BASE_URL ?>/admin/results/broadcast.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-bullhorn me-1"></i>Broadcast</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Quick Actions</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/admin/results/pdf.php" class="btn btn-outline-danger w-100"><i class="fas fa-file-pdf me-1"></i>Download PDF Report Card</a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/admin/results/annual.php" class="btn btn-outline-success w-100"><i class="fas fa-calendar-alt me-1"></i>Cumulative Annual Report</a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/admin/results/import.php" class="btn btn-outline-primary w-100"><i class="fas fa-file-import me-1"></i>Import Scores from CSV</a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/admin/results/broadcast.php" class="btn btn-outline-warning w-100"><i class="fas fa-bullhorn me-1"></i>Broadcast Results</a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/result-checker.php" class="btn btn-outline-info w-100" target="_blank"><i class="fas fa-external-link-alt me-1"></i>Public Result Checker</a>
                    </div>
                    <div class="col-md-4">
                        <a href="<?= BASE_URL ?>/admin/results/pins.php" class="btn btn-outline-secondary w-100"><i class="fas fa-key me-1"></i>Generate Result PINs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chartLabels = json_encode(array_map(fn($c) => $c['class'], $classPerformance));
$chartValues = json_encode(array_map(fn($c) => $c['average'], $classPerformance));

$trendLabels = json_encode(array_map(fn($t) => $t['term'], $termTrend));
$trendValues = json_encode(array_map(fn($t) => $t['average'], $termTrend));

$subjLabels = json_encode(array_map(fn($s) => $s['name'], $subjectPerformance));
$subjValues = json_encode(array_map(fn($s) => round((float)$s['subj_avg'], 1), $subjectPerformance));

$extraScripts = <<<SCRIPT
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx1 = document.getElementById('classPerformanceChart');
    if (ctx1) {
        var labels = {$chartLabels};
        var dataValues = {$chartValues};
        new Chart(ctx1, {
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

    var ctx2 = document.getElementById('passFailChart');
    if (ctx2) {
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Pass ({$passRate}%)', 'Fail ({$failRate}%)'],
                datasets: [{
                    data: [{$passRate}, {$failRate}],
                    backgroundColor: ['#28a745', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    var ctx3 = document.getElementById('termTrendChart');
    if (ctx3) {
        new Chart(ctx3, {
            type: 'line',
            data: {
                labels: {$trendLabels},
                datasets: [{
                    label: 'Session Average',
                    data: {$trendValues},
                    borderColor: 'rgba(212, 175, 55, 1)',
                    backgroundColor: 'rgba(212, 175, 55, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(11, 31, 58, 1)',
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100 }
                }
            }
        });
    }

    var ctx4 = document.getElementById('subjectChart');
    if (ctx4) {
        new Chart(ctx4, {
            type: 'bar',
            data: {
                labels: {$subjLabels},
                datasets: [{
                    label: 'Average Score',
                    data: {$subjValues},
                    backgroundColor: [
                        'rgba(212,175,55,0.7)','rgba(11,31,58,0.7)','rgba(40,167,69,0.7)',
                        'rgba(0,123,255,0.7)','rgba(108,117,125,0.7)','rgba(220,53,69,0.7)',
                        'rgba(23,162,184,0.7)','rgba(255,193,7,0.7)','rgba(111,66,193,0.7)',
                        'rgba(253,126,20,0.7)'
                    ],
                    borderColor: '#fff',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, max: 100 }
                }
            }
        });
    }
});
</script>
SCRIPT;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
