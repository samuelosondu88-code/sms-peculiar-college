<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Exam Results';
$db = getDB();
$userId = $_SESSION['user_id'];

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT te.*, sub.name as subject_name, c.name as class_name, c.section FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id JOIN classes c ON te.class_id = c.id WHERE te.id = ? AND te.teacher_id = ?");
$stmt->execute([$examId, $userId]);
$exam = $stmt->fetch();
if (!$exam) redirect('/teacher/exams/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_results'])) {
    $db->prepare("UPDATE teacher_exams SET status = 'graded' WHERE id = ?")->execute([$examId]);
    $msg = 'Results published.';
}

$filterStudent = sanitizeInput($_GET['student'] ?? '');

$sql = "SELECT ea.*, u.first_name, u.last_name, u.email,
        s.admission_no,
        (SELECT COUNT(*) FROM exam_responses er WHERE er.attempt_id = ea.id) as answered
        FROM exam_attempts ea
        JOIN users u ON ea.student_id = u.id
        LEFT JOIN students s ON u.id = s.user_id
        WHERE ea.exam_id = ?";
$params = [$examId];
if ($filterStudent) { $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)"; $s = "%$filterStudent%"; $params[] = $s; $params[] = $s; }
$sql .= " ORDER BY ea.total_score DESC";
$attempts = $db->prepare($sql);
$attempts->execute($params);
$attemptList = $attempts->fetchAll();

$totalStudents = count($attemptList);
$scores = array_column($attemptList, 'total_score');
$avgScore = $totalStudents > 0 ? round(array_sum($scores) / $totalStudents, 1) : 0;
$maxScore = $totalStudents > 0 ? max($scores) : 0;
$minScore = $totalStudents > 0 ? min($scores) : 0;
$passCount = 0;
$failCount = 0;
$passMark = $exam['total_marks'] > 0 ? 0.5 * $exam['total_marks'] : 50;
$gradeDist = ['A'=>0,'B'=>0,'C'=>0,'D'=>0,'E'=>0,'F'=>0];
foreach ($attemptList as $a) {
    if ($a['total_score'] >= $passMark) $passCount++; else $failCount++;
    if (isset($gradeDist[$a['grade']])) $gradeDist[$a['grade']]++;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Exam Results</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($exam['title']) ?> — <?= sanitizeInput($exam['subject_name']) ?> | <?= sanitizeInput($exam['class_name'] . ' ' . $exam['section']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</button>
        <form method="POST" class="d-inline">
            <button type="submit" name="publish_results" class="btn btn-primary" <?= $exam['status'] === 'graded' ? 'disabled' : '' ?>>
                <i class="fas fa-check-double me-1"></i><?= $exam['status'] === 'graded' ? 'Published' : 'Publish Results' ?>
            </button>
        </form>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-users stat-icon"></i>
            <div class="stat-value"><?= $totalStudents ?></div>
            <div class="stat-label">Participants</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-value"><?= $avgScore ?>/<?= $exam['total_marks'] ?></div>
            <div class="stat-label">Class Average</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-arrow-up stat-icon"></i>
            <div class="stat-value"><?= $maxScore ?></div>
            <div class="stat-label">Highest Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#dc2626,#ef4444)">
            <i class="fas fa-arrow-down stat-icon"></i>
            <div class="stat-value"><?= $minScore ?></div>
            <div class="stat-label">Lowest Score</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Pass / Fail</div>
            <div class="card-body">
                <canvas id="passFailChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Grade Distribution</div>
            <div class="card-body">
                <canvas id="gradeChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fas fa-list me-2"></i>Student Results</span>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="exam_id" value="<?= $examId ?>">
            <input type="text" name="student" class="form-control form-control-sm" placeholder="Search student..." value="<?= sanitizeInput($filterStudent) ?>" style="width:200px">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Student</th><th>Admission No</th><th>Score</th><th>Percentage</th><th>Grade</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($attemptList as $i => $a): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td><?= sanitizeInput($a['admission_no'] ?? '-') ?></td>
                        <td><?= $a['total_score'] ?: 0 ?> / <?= $exam['total_marks'] ?></td>
                        <td><?= $a['percentage'] ?>%</td>
                        <td><strong><?= $a['grade'] ?: '-' ?></strong></td>
                        <td>
                            <?php if ($a['status'] === 'in_progress'): ?><span class="badge bg-warning">In Progress</span>
                            <?php elseif ($a['status'] === 'submitted'): ?><span class="badge bg-info">Submitted</span>
                            <?php else: ?><span class="badge bg-success">Graded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['status'] !== 'in_progress'): ?>
                            <a href="grade.php?attempt_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($attemptList)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No attempts yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $extraScripts = <<<SCRIPT
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('passFailChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pass ($passCount)', 'Fail ($failCount)'],
        datasets: [{ data: [$passCount, $failCount], backgroundColor: ['#059669', '#dc2626'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('gradeChart'), {
    type: 'bar',
    data: {
        labels: ['A','B','C','D','E','F'],
        datasets: [{
            label: 'Students',
            data: [{$gradeDist['A']},{$gradeDist['B']},{$gradeDist['C']},{$gradeDist['D']},{$gradeDist['E']},{$gradeDist['F']}],
            backgroundColor: ['#059669','#16a34a','#eab308','#f97316','#ef4444','#991b1b']
        }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>
SCRIPT;
$extraStyles = '<style>@media print{#sidebar-wrapper,.navbar,.btn,.form-control,form{display:none!important}#page-content-wrapper{margin-left:0!important}}</style>';
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
