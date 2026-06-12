<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'My Results';
$db = getDB();
$studentId = getStudentId();

$stmt = $db->prepare("
    SELECT s.id, s.admission_no, s.class_id, u.first_name, u.last_name, u.avatar,
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

$stmt = $db->prepare("
    SELECT DISTINCT rs.session_id, rs.term_id, s.session_name, t.term_name
    FROM result_scores rs
    JOIN academic_sessions s ON rs.session_id = s.id
    JOIN terms t ON rs.term_id = t.id
    WHERE rs.student_id = ? AND rs.status = 'published'
    ORDER BY s.start_date DESC, t.id ASC
");
$stmt->execute([$studentId]);
$publishedTerms = $stmt->fetchAll();

$groups = [];
foreach ($publishedTerms as $pt) {
    $groups[$pt['session_name']]['session_id'] = $pt['session_id'];
    $groups[$pt['session_name']]['terms'][] = $pt;
}

$latestSummary = [];
if (!empty($publishedTerms)) {
    $latest = $publishedTerms[0];
    $summary = getStudentTermSummary($db, $studentId, $latest['session_id'], $latest['term_id']);
    $summary['position'] = getClassPosition($db, $studentId, $student['class_id'], $latest['session_id'], $latest['term_id']);
    $summary['session_name'] = $latest['session_name'];
    $summary['term_name'] = $latest['term_name'];
    $latestSummary = $summary;
}

$stmt = $db->prepare("SELECT * FROM promotion_results WHERE student_id = ? ORDER BY session_id DESC LIMIT 1");
$stmt->execute([$studentId]);
$promotion = $stmt->fetch();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>My Results</h4>
    <a href="<?= BASE_URL ?>/student/results/annual.php" class="btn btn-gold btn-sm">
        <i class="fas fa-calendar-alt me-1"></i>Annual Results
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <img src="<?= $student['avatar'] ? BASE_URL . '/uploads/' . $student['avatar'] : BASE_URL . '/assets/images/logo.jpg' ?>"
                     alt="Student" class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover; border: 3px solid var(--gold);">
                <h5 class="fw-bold"><?= sanitizeInput($student['first_name'] . ' ' . $student['last_name']) ?></h5>
                <p class="text-muted mb-1"><?= sanitizeInput($student['class_name'] . ' ' . $student['section']) ?></p>
                <p class="text-muted mb-0"><small>Admission No: <?= sanitizeInput($student['admission_no']) ?></small></p>
            </div>
        </div>

        <?php if ($promotion): ?>
        <div class="card mt-3">
            <div class="card-body text-center">
                <h6 class="fw-bold mb-2">Promotion Status</h6>
                <?php
                $badgeClass = match ($promotion['promotion_status']) {
                    'promoted' => 'bg-success',
                    'conditional' => 'bg-warning text-dark',
                    'repeated' => 'bg-danger',
                    'graduated' => 'bg-info',
                    default => 'bg-secondary'
                };
                ?>
                <span class="badge <?= $badgeClass ?> fs-6">
                    <?= ucfirst($promotion['promotion_status']) ?>
                </span>
                <?php if ($promotion['annual_average']): ?>
                <p class="mt-2 mb-0"><small>Annual Average: <strong><?= $promotion['annual_average'] ?>%</strong></small></p>
                <?php endif; ?>
                <?php if ($promotion['remark']): ?>
                <p class="mt-1 mb-0"><small><?= sanitizeInput($promotion['remark']) ?></small></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <?php if (!empty($latestSummary)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-star text-gold me-2"></i>Latest Result</span>
                <span class="badge bg-primary"><?= sanitizeInput($latestSummary['session_name'] . ' - ' . $latestSummary['term_name']) ?></span>
            </div>
            <div class="card-body">
                <div class="row text-center g-3">
                    <div class="col-4 col-md-2">
                        <div class="p-3 rounded bg-light">
                            <div class="fs-4 fw-bold text-primary"><?= $latestSummary['subject_count'] ?></div>
                            <small class="text-muted">Subjects</small>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="p-3 rounded bg-light">
                            <div class="fs-4 fw-bold text-primary"><?= $latestSummary['total_marks'] ?></div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="p-3 rounded bg-light">
                            <div class="fs-4 fw-bold text-primary"><?= number_format($latestSummary['average'], 1) ?>%</div>
                            <small class="text-muted">Average</small>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="p-3 rounded bg-light">
                            <div class="fs-4 fw-bold text-<?= $latestSummary['overall_grade'] === 'A' || $latestSummary['overall_grade'] === 'B' ? 'success' : ($latestSummary['overall_grade'] === 'F' ? 'danger' : 'warning') ?>">
                                <?= $latestSummary['overall_grade'] ?>
                            </div>
                            <small class="text-muted">Grade</small>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="p-3 rounded bg-light">
                            <div class="fs-4 fw-bold text-primary"><?= $latestSummary['position'] ?>th</div>
                            <small class="text-muted">Position</small>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="p-3 rounded bg-light">
                            <div class="fs-4 fw-bold text-success"><?= $latestSummary['pass_count'] ?>/<?= $latestSummary['fail_count'] ?></div>
                            <small class="text-muted">Pass/Fail</small>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="<?= BASE_URL ?>/student/results/view.php?session_id=<?= $publishedTerms[0]['session_id'] ?>&term_id=<?= $publishedTerms[0]['term_id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>View Full Report Card
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($groups)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No published results available yet. Check back later.
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Academic History</div>
            <div class="card-body p-0">
                <div class="accordion" id="resultsAccordion">
                    <?php $first = true; foreach ($groups as $sessionName => $group): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#session<?= $group['session_id'] ?>">
                                <strong><?= sanitizeInput($sessionName) ?></strong>
                            </button>
                        </h2>
                        <div id="session<?= $group['session_id'] ?>" class="accordion-collapse collapse <?= $first ? 'show' : '' ?>">
                            <div class="accordion-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($group['terms'] as $pt): ?>
                                    <a href="<?= BASE_URL ?>/student/results/view.php?session_id=<?= $pt['session_id'] ?>&term_id=<?= $pt['term_id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-file-alt text-primary me-2"></i><?= sanitizeInput($pt['term_name']) ?> Results</span>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
