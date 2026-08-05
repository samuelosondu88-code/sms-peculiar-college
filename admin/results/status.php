<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Result Processing Status';
$db = getDB();

$currentTerm = getCurrentTerm();
$currentSessionId = (int)($currentTerm['session_id'] ?? 0);
$currentTermId = (int)($currentTerm['id'] ?? 0);

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? $currentSessionId);
$selectedTerm = (int)($_GET['term_id'] ?? $currentTermId);

$tStmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id");
$tStmt->execute([$selectedSession]);
$terms = $tStmt->fetchAll();

$subjectData = [];

if ($selectedSession && $selectedTerm) {
    $stmt = $db->prepare("
        SELECT
            s.id, s.name, s.code,
            COALESCE((
                SELECT COUNT(DISTINCT st.id)
                FROM students st
                WHERE st.status = 'active'
                AND st.class_id IN (
                    SELECT s2.class_id FROM subjects s2 WHERE s2.id = s.id
                    UNION
                    SELECT sa.class_id FROM subject_allocations sa WHERE sa.subject_id = s.id
                )
            ), 0) AS expected_students,
            COALESCE((
                SELECT COUNT(DISTINCT rs.student_id)
                FROM result_scores rs
                WHERE rs.subject_id = s.id AND rs.session_id = ? AND rs.term_id = ?
            ), 0) AS completed_results
        FROM subjects s
        ORDER BY s.name
    ");
    $stmt->execute([$selectedSession, $selectedTerm]);
    $subjectData = $stmt->fetchAll();
}

$totalExpected = array_sum(array_column($subjectData, 'expected_students'));
$totalCompleted = array_sum(array_column($subjectData, 'completed_results'));
$overallPercent = $totalExpected > 0 ? round(($totalCompleted / $totalExpected) * 100) : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-tasks me-2"></i>Result Processing Status</h4>
        <p class="text-muted small mb-0">Monitor result entry progress across subjects</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-arrow-left me-1"></i>Back to Results
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Academic Session</label>
                <select name="session_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === (int)$s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === (int)$t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="<?= BASE_URL ?>/admin/results/status.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-sync me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-book stat-icon"></i>
            <div class="stat-value"><?= count($subjectData) ?></div>
            <div class="stat-label">Total Subjects</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= $totalExpected ?></div>
            <div class="stat-label">Expected Results</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $totalCompleted ?></div>
            <div class="stat-label">Completed Results</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card <?= $overallPercent >= 100 ? 'stat-success' : ($overallPercent >= 70 ? 'stat-info' : ($overallPercent >= 40 ? 'stat-warning' : 'stat-danger')) ?>">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-value"><?= $overallPercent ?>%</div>
            <div class="stat-label">Overall Progress</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-bar me-2"></i>Subject Progress Breakdown</span>
        <span class="text-muted small">Sorted alphabetically</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($subjectData)): ?>
        <div class="p-4 text-center text-muted">
            <i class="fas fa-info-circle me-2"></i>No subjects found for the selected session and term.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Completed</th>
                        <th>Expected</th>
                        <th style="min-width: 200px;">Progress</th>
                        <th>Percentage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $barColors = [
                        '#0B1F3A', '#D4AF37', '#059669', '#0284c7',
                        '#dc2626', '#7c3aed', '#ea580c', '#0891b2',
                        '#4f46e5', '#be185d', '#65a30d', '#ca8a04'
                    ];
                    $i = 1;
                    foreach ($subjectData as $subj):
                        $expected = (int)$subj['expected_students'];
                        $completed = (int)$subj['completed_results'];
                        $percent = $expected > 0 ? round(($completed / $expected) * 100) : 0;

                        if ($percent >= 100) {
                            $statusLabel = 'Complete';
                            $statusClass = 'bg-success';
                            $barColor = '#059669';
                        } elseif ($percent >= 70) {
                            $statusLabel = 'Almost Complete';
                            $statusClass = 'bg-primary';
                            $barColor = '#0284c7';
                        } elseif ($percent >= 40) {
                            $statusLabel = 'In Progress';
                            $statusClass = 'bg-warning text-dark';
                            $barColor = '#d97706';
                        } else {
                            $statusLabel = $percent > 0 ? 'Low Progress' : 'Not Started';
                            $statusClass = 'bg-danger';
                            $barColor = '#dc2626';
                        }

                        $colorIdx = ($i - 1) % count($barColors);
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="fw-semibold"><?= sanitizeInput($subj['name']) ?></td>
                        <td><strong><?= $completed ?></strong></td>
                        <td><?= $expected ?></td>
                        <td>
                            <div class="progress" style="height: 20px; border-radius: 10px; background: #e9ecef;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated<?= $percent >= 100 ? '' : '' ?>"
                                     role="progressbar"
                                     style="width: <?= $percent ?>%; background-color: <?= $barColor ?>; border-radius: 10px; transition: width 1s ease;"
                                     aria-valuenow="<?= $percent ?>"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </td>
                        <td><strong class="<?= $percent >= 100 ? 'text-success' : ($percent >= 70 ? 'text-primary' : ($percent >= 40 ? 'text-warning' : 'text-danger')) ?>"><?= $percent ?>%</strong></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mt-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Status Legend</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <span class="badge bg-danger me-1">&nbsp;&nbsp;&nbsp;</span>
                        0–39% — Not Started / Low Progress
                    </div>
                    <div class="col-6">
                        <span class="badge bg-warning text-dark me-1">&nbsp;&nbsp;&nbsp;</span>
                        40–69% — In Progress
                    </div>
                    <div class="col-6">
                        <span class="badge bg-primary me-1">&nbsp;&nbsp;&nbsp;</span>
                        70–99% — Almost Complete
                    </div>
                    <div class="col-6">
                        <span class="badge bg-success me-1">&nbsp;&nbsp;&nbsp;</span>
                        100% — Complete
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-calculator me-2"></i>How It's Calculated</div>
            <div class="card-body">
                <p class="mb-1 small">
                    <strong>Processing Percentage</strong> = (Completed Results ÷ Expected Results) × 100
                </p>
                <p class="mb-0 small text-muted">
                    "Completed Results" counts students whose scores have been entered for a subject.
                    "Expected Results" is the total number of active students in classes where the subject is taught.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
