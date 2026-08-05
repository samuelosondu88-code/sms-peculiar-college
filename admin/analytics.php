<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Performance Analytics';
$db = getDB();

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT t.id, t.term_name AS name, s.session_name FROM terms t JOIN academic_sessions s ON t.session_id = s.id ORDER BY t.start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$subjects = $db->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();

$sessionId = (int)($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$termId = (int)($_GET['term_id'] ?? 0);
$classId = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

$chartLabels = '[]';
$chartAverages = '[]';

if ($classId && $sessionId) {
    $termData = $db->prepare("SELECT t.id, t.term_name AS name FROM terms t WHERE t.session_id = ? ORDER BY t.start_date");
    $termData->execute([$sessionId]);
    $termRows = $termData->fetchAll();
    $labels = []; $avgs = [];
    foreach ($termRows as $tr) {
        $labels[] = $tr['name'];
        $avgStmt = $db->prepare("SELECT COALESCE(AVG(r.total_score), 0) FROM results r JOIN exams e ON r.exam_id = e.id JOIN students s ON r.student_id = s.id WHERE e.term_id = ? AND s.class_id = ?");
        $avgStmt->execute([$tr['id'], $classId]);
        $avgs[] = round((float)$avgStmt->fetchColumn(), 1);
    }
    $chartLabels = json_encode($labels);
    $chartAverages = json_encode($avgs);
}

$subjectLabels = '[]';
$subjectAverages = '[]';
if ($classId && $termId) {
    $subjData = $db->prepare("SELECT sub.id, sub.name, COALESCE(AVG(r.total_score), 0) as avg_score FROM results r JOIN exams e ON r.exam_id = e.id JOIN subjects sub ON r.subject_id = sub.id JOIN students s ON r.student_id = s.id WHERE e.term_id = ? AND s.class_id = ? GROUP BY sub.id, sub.name ORDER BY avg_score DESC");
    $subjData->execute([$termId, $classId]);
    $subjRows = $subjData->fetchAll();
    $sLabels = []; $sAvgs = [];
    foreach ($subjRows as $sr) {
        $sLabels[] = $sr['name'];
        $sAvgs[] = round((float)$sr['avg_score'], 1);
    }
    $subjectLabels = json_encode($sLabels);
    $subjectAverages = json_encode($sAvgs);
}

$gradeDistLabels = '[]';
$gradeDistData = '[]';
if ($classId && $termId) {
    $gradeQuery = $db->prepare("SELECT CASE WHEN r.total_score >= 75 THEN 'A' WHEN r.total_score >= 60 THEN 'B' WHEN r.total_score >= 50 THEN 'C' WHEN r.total_score >= 40 THEN 'D' ELSE 'F' END as grade, COUNT(*) as c FROM results r JOIN exams e ON r.exam_id = e.id JOIN students s ON r.student_id = s.id WHERE e.term_id = ? AND s.class_id = ? GROUP BY grade ORDER BY grade");
    $gradeQuery->execute([$termId, $classId]);
    $gLabels = []; $gData = [];
    foreach ($gradeQuery->fetchAll() as $g) {
        $gLabels[] = 'Grade ' . $g['grade'];
        $gData[] = (int)$g['c'];
    }
    $gradeDistLabels = json_encode($gLabels);
    $gradeDistData = json_encode($gData);
}

$where = 'WHERE 1=1'; $params = [];
if ($sessionId) { $where .= ' AND e.term_id IN (SELECT id FROM terms WHERE session_id = ?)'; $params[] = $sessionId; }
if ($termId) { $where .= ' AND e.term_id = ?'; $params[] = $termId; }
if ($classId) { $where .= ' AND s.class_id = ?'; $params[] = $classId; }
if ($subjectId) { $where .= ' AND r.subject_id = ?'; $params[] = $subjectId; }

$topStmt = $db->prepare("SELECT u.first_name, u.last_name, s.admission_no, c.name as class_name, c.section, COALESCE(SUM(r.total_score), 0) as total_score FROM results r JOIN exams e ON r.exam_id = e.id JOIN students s ON r.student_id = s.id JOIN users u ON s.user_id = u.id JOIN classes c ON s.class_id = c.id $where GROUP BY s.id, u.first_name, u.last_name, s.admission_no, c.name, c.section ORDER BY total_score DESC LIMIT 20");
$topStmt->execute($params);
$topStudents = $topStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Performance Analytics</h4>
</div>

<form method="GET" class="row g-2 mb-4">
    <div class="col-md-3">
        <select name="session_id" class="form-select form-select-sm">
            <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $sessionId === (int)$s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="term_id" class="form-select form-select-sm">
            <option value="0">All Terms</option>
            <?php foreach ($terms as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $termId === (int)$t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['name'] . ' - ' . $t['session_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="class_id" class="form-select form-select-sm">
            <option value="0">All Classes</option>
            <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $classId === (int)$c['id'] ? 'selected' : '' ?>><?= sanitizeInput(className($c['name'], $c['section'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="subject_id" class="form-select form-select-sm">
            <option value="0">All Subjects</option>
            <?php foreach ($subjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Load</button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line me-2"></i>Class Average by Term</div>
            <div class="card-body"><canvas id="trendChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Subject Performance</div>
            <div class="card-body"><canvas id="subjectChart" height="200"></canvas></div>
        </div>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Grade Distribution</div>
            <div class="card-body"><canvas id="gradeChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-trophy me-2"></i>Top Performers</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Student</th><th>Admission No</th><th>Class</th><th>Total Score</th></tr></thead>
                    <tbody>
                        <?php if (empty($topStudents)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Select filters to load data.</td></tr>
                        <?php else: $rank = 1; foreach ($topStudents as $ts): ?>
                        <tr>
                            <td><?= $rank ?></td>
                            <td><?= sanitizeInput($ts['first_name'] . ' ' . $ts['last_name']) ?></td>
                            <td><?= sanitizeInput($ts['admission_no']) ?></td>
                            <td><?= sanitizeInput(className($ts['class_name'], $ts['section'])) ?></td>
                            <td><strong><?= number_format((float)$ts['total_score'], 1) ?></strong></td>
                        </tr>
                        <?php $rank++; endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var blue = '#0B1F3A', gold = '#D4AF37';

    var trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: { labels: <?= $chartLabels ?>, datasets: [{ label: 'Average Score', data: <?= $chartAverages ?>, borderColor: gold, backgroundColor: gold.replace(')', ',0.2)'), fill: true, tension: 0.3 }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
        });
    }

    var subjCtx = document.getElementById('subjectChart');
    if (subjCtx) {
        new Chart(subjCtx, {
            type: 'bar',
            data: { labels: <?= $subjectLabels ?>, datasets: [{ label: 'Average Score', data: <?= $subjectAverages ?>, backgroundColor: 'rgba(11,31,58,0.7)', borderColor: blue, borderWidth: 1 }] },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
        });
    }

    var gradeCtx = document.getElementById('gradeChart');
    if (gradeCtx) {
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: { labels: <?= $gradeDistLabels ?>, datasets: [{ data: <?= $gradeDistData ?>, backgroundColor: ['#059669', '#2563eb', '#d97706', '#dc2626', '#6b7280'] }] },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
