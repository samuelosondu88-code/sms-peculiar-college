<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Cumulative Termly Reports';
$db = getDB();

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedClass = (int)($_GET['class_id'] ?? 0);

$terms = [];
$classStudents = [];
$termSummaries = [];
$annualData = [];

if ($selectedSession) {
    $stmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id");
    $stmt->execute([$selectedSession]);
    $terms = $stmt->fetchAll();
}

if ($selectedClass && $selectedSession) {
    $stmt = $db->prepare("
        SELECT s.id, u.first_name, u.last_name, s.admission_no
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ? AND s.status = 'active'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$selectedClass]);
    $classStudents = $stmt->fetchAll();

    foreach ($classStudents as $st) {
        $studentTermData = [];
        $termAvgs = [];
        foreach ($terms as $term) {
            $summary = getStudentTermSummary($db, $st['id'], $selectedSession, $term['id']);
            $studentTermData[$term['id']] = $summary;
            $termAvgs[] = $summary['average'];
        }
        $annualAvg = computeAnnualAverage($termAvgs);
        $pos = getClassPosition($db, $st['id'], $selectedClass, $selectedSession, $terms[count($terms)-1]['id'] ?? 0);

        $promo = $db->prepare("SELECT * FROM promotion_results WHERE student_id = ? AND session_id = ?");
        $promo->execute([$st['id'], $selectedSession]);
        $promotion = $promo->fetch();

        $annualData[] = [
            'student' => $st,
            'term_data' => $studentTermData,
            'annual_avg' => $annualAvg,
            'position' => $pos,
            'promotion' => $promotion
        ];
    }

    usort($annualData, fn($a, $b) => $b['annual_avg'] <=> $a['annual_avg']);
    $rank = 0; $prevAvg = -1; $prevRank = 0;
    foreach ($annualData as $i => &$ad) {
        $rank++;
        $ad['rank'] = ($ad['annual_avg'] == $prevAvg) ? $prevRank : $rank;
        $prevAvg = $ad['annual_avg'];
        $prevRank = $ad['rank'];
    }
    unset($ad);
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>Cumulative Termly Reports</h4>
    <div>
        <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-secondary btn-sm me-1"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($annualData)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2"></i>Annual Performance Summary</span>
        <span class="badge bg-primary fs-6"><?= count($annualData) ?> Students</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Admission</th>
                        <?php foreach ($terms as $term): ?>
                        <th class="text-center"><?= sanitizeInput($term['term_name']) ?> Avg</th>
                        <?php endforeach; ?>
                        <th class="text-center">Annual Avg</th>
                        <th class="text-center">Position</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($annualData as $ad): $s = $ad['student']; ?>
                    <tr>
                        <td><?= $ad['rank'] ?></td>
                        <td><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?></td>
                        <td><?= sanitizeInput($s['admission_no']) ?></td>
                        <?php foreach ($terms as $term): 
                            $td = $ad['term_data'][$term['id']] ?? null;
                            $avg = $td ? $td['average'] : '-';
                        ?>
                        <td class="text-center"><?= $avg !== '-' ? number_format($avg, 1) : '-' ?></td>
                        <?php endforeach; ?>
                        <td class="text-center"><strong><?= number_format($ad['annual_avg'], 1) ?>%</strong></td>
                        <td class="text-center"><?= $ad['position'] ? $ad['position'] . ($ad['position'] >= 11 && $ad['position'] <= 13 ? 'th' : match ($ad['position'] % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' }) : '-' ?></td>
                        <td class="text-center">
                            <?php if ($ad['promotion']): 
                                $ps = $ad['promotion']['promotion_status'];
                                $badge = match($ps) { 'promoted' => 'bg-success', 'conditional' => 'bg-warning text-dark', 'repeated' => 'bg-danger', 'graduated' => 'bg-info', default => 'bg-secondary' };
                            ?>
                            <span class="badge <?= $badge ?>"><?= ucfirst($ps) ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($selectedClass && $selectedSession): ?>
<div class="row g-3 mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Top 5 Students</div>
            <div class="card-body">
                <canvas id="topStudentsChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Promotion Distribution</div>
            <div class="card-body">
                <canvas id="promoChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
$top5 = array_slice($annualData, 0, 5);
$topLabels = json_encode(array_map(fn($d) => $d['student']['last_name'] . ' ' . $d['student']['first_name'], $top5));
$topValues = json_encode(array_map(fn($d) => $d['annual_avg'], $top5));

$promoCounts = ['promoted' => 0, 'conditional' => 0, 'repeated' => 0, 'pending' => 0];
foreach ($annualData as $ad) {
    if ($ad['promotion']) {
        $ps = $ad['promotion']['promotion_status'];
        $promoCounts[$ps] = ($promoCounts[$ps] ?? 0) + 1;
    } else {
        $promoCounts['pending']++;
    }
}
$promoLabels = json_encode(array_keys($promoCounts));
$promoValues = json_encode(array_values($promoCounts));

$extraScripts = <<<SCRIPT
<script>
new Chart(document.getElementById('topStudentsChart'), {
    type: 'bar',
    data: {
        labels: {$topLabels},
        datasets: [{
            label: 'Annual Average',
            data: {$topValues},
            backgroundColor: ['rgba(212,175,55,0.7)','rgba(11,31,58,0.7)','rgba(40,167,69,0.7)','rgba(0,123,255,0.7)','rgba(108,117,125,0.7)'],
            borderColor: ['#D4AF37','#0B1F3A','#28a745','#007bff','#6c757d'],
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});
new Chart(document.getElementById('promoChart'), {
    type: 'pie',
    data: {
        labels: {$promoLabels},
        datasets: [{
            data: {$promoValues},
            backgroundColor: ['#28a745','#ffc107','#dc3545','#6c757d']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
SCRIPT;
?>
<?php endif; ?>

<?php else: ?>
<div class="card">
    <div class="card-body text-center py-4 text-muted">
        <i class="fas fa-info-circle me-2"></i>Select a class to view cumulative termly reports.
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
