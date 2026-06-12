<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Performance Analytics';
$db = getDB();

$studentId = getStudentId();

// All completed attempts with subject data
$attempts = $db->query("
    SELECT ca.*, ce.title as exam_title, ce.pass_score,
           s.name as subject_name, s.code as subject_code
    FROM cbt_attempts ca
    JOIN cbt_exams ce ON ca.exam_id = ce.id
    JOIN cbt_subjects s ON ce.subject_id = s.id
    WHERE ca.student_id = $studentId AND ca.status = 'completed'
    ORDER BY ca.completed_at ASC
")->fetchAll();

// Subject-wise performance
$subjectPerf = $db->query("
    SELECT s.name, s.code,
           COUNT(ca.id) as attempts,
           ROUND(AVG(ca.score), 1) as avg_score,
           SUM(ca.correct_count) as total_correct,
           SUM(ca.total_questions) as total_q,
           ROUND(SUM(ca.correct_count) * 100.0 / NULLIF(SUM(ca.total_questions), 0), 1) as accuracy,
           MAX(ca.score) as best_score,
           MIN(ca.score) as worst_score
    FROM cbt_attempts ca
    JOIN cbt_exams ce ON ca.exam_id = ce.id
    JOIN cbt_subjects s ON ce.subject_id = s.id
    WHERE ca.student_id = $studentId AND ca.status = 'completed'
    GROUP BY s.id, s.name, s.code
    ORDER BY accuracy ASC
")->fetchAll();

// Overall stats
$totalExams = count($attempts);
$avgScore = $totalExams > 0 ? round(array_sum(array_column($attempts, 'score')) / $totalExams, 1) : 0;
$passed = 0;
$failed = 0;
$bestAttempt = null;
$worstAttempt = null;
$recent = array_slice($attempts, -5);

foreach ($attempts as $a) {
    if ($a['score'] >= $a['pass_score']) $passed++; else $failed++;
    if (!$bestAttempt || $a['score'] > $bestAttempt['score']) $bestAttempt = $a;
    if (!$worstAttempt || $a['score'] < $worstAttempt['score']) $worstAttempt = $a;
}

// Score trend data (last 10 attempts)
$trendLabels = [];
$trendScores = [];
$trendPass = [];
$trendSlice = array_slice($attempts, -10);
foreach ($trendSlice as $a) {
    $trendLabels[] = "'" . sanitizeInput($a['subject_code']) . "'";
    $trendScores[] = $a['score'];
    $trendPass[] = $a['pass_score'];
}

// Subject performance data
$subjLabels = [];
$subjScores = [];
$subjAccuracy = [];
$subjAttempts = [];
foreach ($subjectPerf as $s) {
    $subjLabels[] = "'" . sanitizeInput($s['code']) . "'";
    $subjScores[] = $s['avg_score'];
    $subjAccuracy[] = $s['accuracy'];
    $subjAttempts[] = $s['attempts'];
}

$extraStyles = '<style>
.chart-container { position: relative; height: 280px; width: 100%; }
.weak-subject { border-left: 4px solid #dc2626; }
.strong-subject { border-left: 4px solid #16a34a; }
</style>';

$extraScripts = '
<script>
const trendLabels = [' . implode(',', $trendLabels) . '];
const trendScores = [' . implode(',', $trendScores) . '];
const trendPass = [' . implode(',', $trendPass) . '];
const subjLabels = [' . implode(',', $subjLabels) . '];
const subjScores = [' . implode(',', $subjScores) . '];
const subjAccuracy = [' . implode(',', $subjAccuracy) . '];

document.addEventListener("DOMContentLoaded", function() {
    // Score trend chart
    if (trendLabels.length) {
        new Chart(document.getElementById("trendChart"), {
            type: "line",
            data: {
                labels: trendLabels,
                datasets: [{
                    label: "Your Score (%)",
                    data: trendScores,
                    borderColor: "#D4AF37",
                    backgroundColor: "rgba(212,175,55,0.1)",
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: "#D4AF37",
                    pointRadius: 5,
                    pointHoverRadius: 7
                }, {
                    label: "Pass Mark",
                    data: trendPass,
                    borderColor: "#0B1F3A",
                    borderDash: [5, 5],
                    pointRadius: 0,
                    borderWidth: 2,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: "rgba(0,0,0,0.05)" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Subject performance chart
    if (subjLabels.length) {
        new Chart(document.getElementById("subjectChart"), {
            type: "bar",
            data: {
                labels: subjLabels,
                datasets: [{
                    label: "Average Score (%)",
                    data: subjScores,
                    backgroundColor: subjScores.map(s => s >= 50 ? "rgba(22,163,74,0.7)" : "rgba(220,38,38,0.7)"),
                    borderColor: subjScores.map(s => s >= 50 ? "#16a34a" : "#dc2626"),
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, grid: { color: "rgba(0,0,0,0.05)" } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Donut chart
    if (' . $totalExams . ' > 0) {
        new Chart(document.getElementById("overallChart"), {
            type: "doughnut",
            data: {
                labels: ["Passed (' . $passed . ')", "Failed (' . $failed . ')"],
                datasets: [{
                    data: [' . $passed . ', ' . $failed . '],
                    backgroundColor: ["rgba(22,163,74,0.8)", "rgba(220,38,38,0.8)"],
                    borderColor: ["#16a34a", "#dc2626"],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "65%",
                plugins: {
                    legend: { position: "bottom" }
                }
            }
        });
    }
});
</script>';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Performance Analytics</h4>
        <p class="text-muted small mb-0">Track your exam scores and identify areas for improvement</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/student/cbt/index.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>Back to Exams
        </a>
    </div>
</div>

<?php if ($totalExams === 0): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>No completed exams yet. Take your first exam to see performance analytics here!
    <br><a href="<?= BASE_URL ?>/student/cbt/index.php" class="alert-link mt-2 d-inline-block">Browse available exams →</a>
</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-value"><?= $totalExams ?></div>
            <div class="stat-label">Exams Taken</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-gold">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-value"><?= $avgScore ?>%</div>
            <div class="stat-label">Average Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #16a34a, #22c55e);">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $passed ?></div>
            <div class="stat-label">Passed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
            <i class="fas fa-times-circle stat-icon"></i>
            <div class="stat-value"><?= $failed ?></div>
            <div class="stat-label">Failed</div>
        </div>
    </div>
</div>

<!-- Best/Worst -->
<?php if ($bestAttempt): ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-success h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas fa-trophy text-white" style="font-size: 22px;"></i>
                    </div>
                    <div>
                        <small class="text-success fw-bold text-uppercase">Best Performance</small>
                        <h5 class="fw-bold mb-0"><?= sanitizeInput($bestAttempt['exam_title']) ?></h5>
                        <small class="text-muted"><?= sanitizeInput($bestAttempt['subject_name']) ?> — <strong class="text-success"><?= $bestAttempt['score'] ?>%</strong></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-danger h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-danger d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="fas fa-arrow-down text-white" style="font-size: 22px;"></i>
                    </div>
                    <div>
                        <small class="text-danger fw-bold text-uppercase">Needs Improvement</small>
                        <h5 class="fw-bold mb-0"><?= sanitizeInput($worstAttempt['exam_title']) ?></h5>
                        <small class="text-muted"><?= sanitizeInput($worstAttempt['subject_name']) ?> — <strong class="text-danger"><?= $worstAttempt['score'] ?>%</strong></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-line me-2"></i>Score Trend (Last <?= min(10, count($attempts)) ?> Exams)</div>
            <div class="card-body">
                <div class="chart-container"><canvas id="trendChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Pass / Fail Ratio</div>
            <div class="card-body">
                <div class="chart-container" style="height: 220px;"><canvas id="overallChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Performance -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Subject Performance</div>
            <div class="card-body">
                <div class="chart-container"><canvas id="subjectChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-list me-2"></i>Subject Breakdown</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($subjectPerf as $s): ?>
                    <?php $isWeak = $s['accuracy'] < 50; ?>
                    <div class="list-group-item <?= $isWeak ? 'weak-subject' : 'strong-subject' ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= sanitizeInput($s['name']) ?></strong>
                                <span class="badge bg-primary ms-2"><?= sanitizeInput($s['code']) ?></span>
                                <br><small class="text-muted"><?= $s['attempts'] ?> exam(s)</small>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= $isWeak ? 'bg-danger' : 'bg-success' ?>" style="font-size: 14px;"><?= $s['accuracy'] ?>%</span>
                                <?php if ($isWeak): ?>
                                <br><small class="text-danger">Needs work</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($s['attempts'] > 0): ?>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar <?= $isWeak ? 'bg-danger' : 'bg-success' ?>" style="width: <?= $s['accuracy'] ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Results Table -->
<div class="card">
    <div class="card-header"><i class="fas fa-table me-2"></i>All Results</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Subject</th>
                    <th>Score</th>
                    <th>Correct</th>
                    <th>Accuracy</th>
                    <th>Date</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($attempts) as $a): ?>
                <?php $passed = $a['score'] >= $a['pass_score']; ?>
                <tr>
                    <td><?= sanitizeInput($a['exam_title']) ?></td>
                    <td><span class="badge bg-primary"><?= sanitizeInput($a['subject_code']) ?></span></td>
                    <td>
                        <span class="badge <?= $passed ? 'bg-success' : 'bg-danger' ?>"><?= $a['score'] ?>%</span>
                    </td>
                    <td><?= $a['correct_count'] ?>/<?= $a['total_questions'] ?></td>
                    <td>
                        <?php $acc = $a['total_questions'] > 0 ? round($a['correct_count'] * 100 / $a['total_questions'], 1) : 0; ?>
                        <div class="progress" style="height: 8px; width: 100px;">
                            <div class="progress-bar <?= $acc >= 50 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $acc ?>%"></div>
                        </div>
                    </td>
                    <td><?= $a['completed_at'] ? formatDate($a['completed_at']) : '-' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/student/cbt/results.php?attempt_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
