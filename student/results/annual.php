<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Annual Results';
$db = getDB();
$studentId = getStudentId();

$stmt = $db->prepare("
    SELECT s.id, s.admission_no, s.class_id, u.first_name, u.last_name,
           c.name as class_name, c.section
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    redirect('/student/index.php');
}

$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? 0);
if (!$selectedSession && !empty($sessions)) {
    $selectedSession = $sessions[0]['id'];
}

$stmt = $db->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
$stmt->execute([$selectedSession]);
$currentSessionName = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id ASC");
$stmt->execute([$selectedSession]);
$terms = $stmt->fetchAll();

$termSummaries = [];
$allSubjectNames = [];
$subjectTermScores = [];

foreach ($terms as $term) {
    $summary = getStudentTermSummary($db, $studentId, $selectedSession, $term['id']);
    if ($summary['subject_count'] > 0) {
        $termSummaries[$term['id']] = $summary;
        foreach ($summary['results'] as $r) {
            $subj = $r['subject_name'];
            $allSubjectNames[$subj] = true;
            $subjectTermScores[$subj][$term['id']] = [
                'score' => (float)$r['total_score'],
                'grade' => $r['grade'],
                'position' => $r['subject_position'],
            ];
        }
    }
}

$termAverages = [];
foreach ($termSummaries as $tid => $s) {
    $termAverages[] = $s['average'];
}
$annualAverage = computeAnnualAverage($termAverages);

$stmt = $db->prepare("SELECT * FROM promotion_results WHERE student_id = ? AND session_id = ?");
$stmt->execute([$studentId, $selectedSession]);
$promotion = $stmt->fetch();

if (!$promotion) {
    $promotionCheck = determinePromotion($db, $studentId, $student['class_id'], $selectedSession);
} else {
    $promotionCheck = $promotion;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>Annual Results</h4>
    <div>
        <a href="<?= BASE_URL ?>/student/results/index.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-1"><?= sanitizeInput($student['first_name'] . ' ' . $student['last_name']) ?></h5>
                <p class="text-muted mb-0">
                    <?= sanitizeInput($student['class_name'] . ' ' . $student['section']) ?> &middot;
                    Admission: <?= sanitizeInput($student['admission_no']) ?>
                </p>
            </div>
            <div class="col-md-4">
                <form method="GET" class="mb-0">
                    <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>>
                            <?= sanitizeInput($s['session_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (empty($termSummaries)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>No results available for <?= sanitizeInput($currentSessionName) ?>.
</div>
<?php else: ?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary"><?= number_format($annualAverage, 1) ?>%</div>
                <small class="text-muted">Annual Average</small>
            </div>
        </div>
    </div>
    <?php foreach ($termSummaries as $tid => $s): ?>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-4 fw-bold"><?= number_format($s['average'], 1) ?>%</div>
                <small class="text-muted">
                    <?php
                    $tn = array_filter($terms, fn($t) => $t['id'] === $tid);
                    $tn = reset($tn);
                    echo sanitizeInput($tn ? $tn['term_name'] : 'Term');
                    ?>
                </small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-header"><strong><i class="fas fa-table me-2"></i>Term-by-Term Subject Performance</strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Subject</th>
                        <?php foreach ($terms as $term): ?>
                        <th class="text-center"><?= sanitizeInput($term['term_name']) ?> Score</th>
                        <th class="text-center">Grade</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allSubjectNames as $subj => $_): ?>
                    <tr>
                        <td><?= sanitizeInput($subj) ?></td>
                        <?php foreach ($terms as $term): ?>
                        <?php
                        $sd = $subjectTermScores[$subj][$term['id']] ?? null;
                        $score = $sd ? $sd['score'] : '-';
                        $grade = $sd ? $sd['grade'] : '-';
                        ?>
                        <td class="text-center"><?= $score !== '-' ? $score : '-' ?></td>
                        <td class="text-center">
                            <?php if ($grade !== '-'): ?>
                            <span class="badge bg-<?= $grade === 'A' ? 'success' : ($grade === 'F' ? 'danger' : 'primary') ?>">
                                <?= $grade ?>
                            </span>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong><i class="fas fa-trophy me-2"></i>Promotion Status</strong></div>
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-4 text-center">
                <h6 class="fw-bold mb-0">Annual Average</h6>
                <div class="fs-2 fw-bold text-primary"><?= number_format($annualAverage, 1) ?>%</div>
            </div>
            <div class="col-md-4 text-center">
                <h6 class="fw-bold mb-0">Status</h6>
                <?php
                $statusText = $promotionCheck['status'] ?? 'pending';
                $statusBadge = match ($statusText) {
                    'promoted' => 'bg-success',
                    'conditional' => 'bg-warning text-dark',
                    'repeated' => 'bg-danger',
                    'graduated' => 'bg-info',
                    default => 'bg-secondary'
                };
                ?>
                <span class="badge <?= $statusBadge ?> fs-5 mt-2">
                    <?= ucfirst($statusText) ?>
                </span>
            </div>
            <div class="col-md-4 text-center">
                <h6 class="fw-bold mb-0">Remark</h6>
                <p class="mt-2 mb-0"><?= sanitizeInput($promotionCheck['remark'] ?? 'Pending') ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print, #sidebar-wrapper, .navbar, footer, form, .btn { display: none !important; }
    #page-content-wrapper { margin-left: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    body { background: white !important; font-size: 12px; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
