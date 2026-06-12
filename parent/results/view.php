<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'View Child\'s Result';
$db = getDB();
$parentId = getParentId();

$studentId = (int)($_GET['student_id'] ?? 0);
$sessionId = (int)($_GET['session_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);

if (!$studentId) {
    redirect('/parent/results/index.php');
}

$stmt = $db->prepare("SELECT COUNT(*) FROM student_parents WHERE student_id = ? AND parent_id = ?");
$stmt->execute([$studentId, $parentId]);
if ($stmt->fetchColumn() == 0) {
    redirect('/parent/results/index.php');
}

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
    redirect('/parent/results/index.php');
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

if (!$sessionId && !empty($publishedTerms)) {
    $sessionId = $publishedTerms[0]['session_id'];
    $termId = $publishedTerms[0]['term_id'];
}

$summary = [];
$position = 0;
$attendance = [];
$sessionName = '';
$termName = '';
$psychomotor = [];
$affective = [];
$comments = [];
$insights = [];
$promotion = [];
$pinCode = '';
$qrUrl = '';

if ($sessionId && $termId) {
    $summary = getStudentTermSummary($db, $studentId, $sessionId, $termId);
    $position = getClassPosition($db, $studentId, $student['class_id'], $sessionId, $termId);
    $attendance = getAttendanceStats($db, $studentId, $student['class_id'], $sessionId, $termId);

    $stmt2 = $db->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
    $stmt2->execute([$sessionId]);
    $sessionName = $stmt2->fetchColumn();

    $stmt2 = $db->prepare("SELECT term_name FROM terms WHERE id = ?");
    $stmt2->execute([$termId]);
    $termName = $stmt2->fetchColumn();

    $p = $db->prepare("SELECT * FROM psychomotor_assessments WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $p->execute([$studentId, $sessionId, $termId]);
    $psychomotor = $p->fetch() ?: [];

    $a = $db->prepare("SELECT * FROM affective_assessments WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $a->execute([$studentId, $sessionId, $termId]);
    $affective = $a->fetch() ?: [];

    $c = $db->prepare("SELECT * FROM result_comments WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $c->execute([$studentId, $sessionId, $termId]);
    $comments = $c->fetch() ?: [];

    $i = $db->prepare("SELECT * FROM academic_insights WHERE student_id = ? AND session_id = ? AND term_id = ?");
    $i->execute([$studentId, $sessionId, $termId]);
    $insights = $i->fetch() ?: [];

    $pr = $db->prepare("SELECT * FROM promotion_results WHERE student_id = ? AND session_id = ?");
    $pr->execute([$studentId, $sessionId]);
    $promotion = $pr->fetch() ?: [];

    $pinStmt = $db->prepare("SELECT pin FROM result_pins WHERE student_id = ? AND session_id = ? AND (term_id = ? OR term_id IS NULL) AND is_active = 1 AND is_used = 0 LIMIT 1");
    $pinStmt->execute([$studentId, $sessionId, $termId]);
    $pinCode = $pinStmt->fetchColumn();

    $verifyUrl = $pinCode ? BASE_URL . '/auth/result-verify.php?pin=' . urlencode($pinCode) . '&sid=' . $sessionId . '&tid=' . $termId . '&stid=' . $studentId : '';
    $qrUrl = $verifyUrl ? 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($verifyUrl) : '';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h4 class="fw-bold mb-0">
        <i class="fas fa-file-alt me-2"></i>Result: <?= sanitizeInput($student['first_name'] . ' ' . $student['last_name']) ?>
    </h4>
    <div>
        <a href="<?= BASE_URL ?>/parent/results/index.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print / PDF
        </button>
    </div>
</div>

<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="student_id" value="<?= $studentId ?>">
            <div class="col-md-4">
                <label class="form-label">Student</label>
                <input type="text" class="form-control" value="<?= sanitizeInput($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['admission_no'] . ')') ?>" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" onchange="this.form.submit()">
                    <?php
                    $seenSessions = [];
                    foreach ($publishedTerms as $pt):
                        if (!in_array($pt['session_id'], $seenSessions)):
                        $seenSessions[] = $pt['session_id'];
                    ?>
                    <option value="<?= $pt['session_id'] ?>" <?= $sessionId === $pt['session_id'] ? 'selected' : '' ?>>
                        <?= sanitizeInput($pt['session_name']) ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($publishedTerms as $pt): if ($pt['session_id'] == $sessionId): ?>
                    <option value="<?= $pt['term_id'] ?>" <?= $termId === $pt['term_id'] ? 'selected' : '' ?>>
                        <?= sanitizeInput($pt['term_name']) ?>
                    </option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($summary)): ?>
<div class="card mb-4" id="report-card">
    <div class="card-body">
        <div class="text-center mb-4 border-bottom pb-3">
            <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="School Logo" style="width: 70px; height: 70px; object-fit: cover; border-radius: 50%; border: 2px solid var(--gold);">
            <h4 class="fw-bold mt-2 mb-0"><?= SCHOOL_NAME ?></h4>
            <small class="text-muted"><?= SCHOOL_ADDRESS ?></small>
            <h5 class="mt-3 fw-bold text-uppercase" style="color: var(--gold);">Report Card</h5>
            <span class="badge bg-primary fs-6"><?= sanitizeInput($sessionName . ' - ' . $termName) ?></span>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <table class="table table-sm table-borderless">
                    <tr><td style="width: 140px;"><strong>Student Name:</strong></td><td><?= sanitizeInput($student['first_name'] . ' ' . $student['last_name']) ?></td></tr>
                    <tr><td><strong>Class:</strong></td><td><?= sanitizeInput($student['class_name'] . ' ' . $student['section']) ?></td></tr>
                    <tr><td><strong>Admission No:</strong></td><td><?= sanitizeInput($student['admission_no']) ?></td></tr>
                </table>
            </div>
            <div class="col-md-4 text-center">
                <?php if ($student['avatar']): ?>
                <img src="<?= BASE_URL ?>/uploads/<?= $student['avatar'] ?>" alt="Passport" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border-color);">
                <?php else: ?>
                <div style="width: 80px; height: 80px; border-radius: 8px; border: 2px solid var(--border-color); margin: 0 auto; display: flex; align-items: center; justify-content: center; background: var(--bg-light);">
                    <i class="fas fa-user fa-2x text-muted"></i>
                </div>
                <?php endif; ?>
                <small class="d-block text-muted mt-1">Passport</small>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th><th>Subject</th><th class="text-center">Assignment</th>
                        <th class="text-center">Test</th><th class="text-center">Project</th>
                        <th class="text-center">CA Total</th><th class="text-center">Exam</th>
                        <th class="text-center">Total</th><th class="text-center">Grade</th>
                        <th class="text-center">Position</th><th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach ($summary['results'] as $r): $i++; ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= sanitizeInput($r['subject_name']) ?></td>
                        <td class="text-center"><?= (float)$r['assignment_score'] ?></td>
                        <td class="text-center"><?= (float)$r['test_score'] ?></td>
                        <td class="text-center"><?= (float)$r['project_score'] ?></td>
                        <td class="text-center"><strong><?= (float)$r['ca_total'] ?></strong></td>
                        <td class="text-center"><?= (float)$r['exam_score'] ?></td>
                        <td class="text-center"><strong><?= (float)$r['total_score'] ?></strong></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $r['grade'] === 'A' ? 'success' : ($r['grade'] === 'F' ? 'danger' : ($r['grade'] === 'B' ? 'info' : 'warning')) ?>">
                                <?= $r['grade'] ?: '-' ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $r['subject_position'] ? $r['subject_position'] . niceOrdinal($r['subject_position']) : '-' ?></td>
                        <td><?= getGradeRemark($r['grade'] ?: '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td style="width: 160px;"><strong>Total Marks Obtained:</strong></td><td><?= $summary['total_marks'] ?></td></tr>
                    <tr><td><strong>Number of Subjects:</strong></td><td><?= $summary['subject_count'] ?></td></tr>
                    <tr><td><strong>Student's Average:</strong></td><td><strong><?= number_format($summary['average'], 1) ?>%</strong></td></tr>
                    <tr><td><strong>Class Position:</strong></td><td><strong><?= $position ? $position . niceOrdinal($position) : '-' ?></strong></td></tr>
                    <tr><td><strong>Overall Grade:</strong></td><td><span class="badge bg-<?= $summary['overall_grade'] === 'A' ? 'success' : ($summary['overall_grade'] === 'F' ? 'danger' : 'primary') ?> fs-6"><?= $summary['overall_grade'] ?> - <?= getGradeRemark($summary['overall_grade']) ?></span></td></tr>
                    <tr><td><strong>Pass / Fail:</strong></td><td><?= $summary['pass_count'] ?> Passed / <?= $summary['fail_count'] ?> Failed</td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center py-3">
                        <h6 class="fw-bold mb-2">Attendance</h6>
                        <div class="row g-1">
                            <div class="col-6"><small>Total Days</small><br><strong><?= $attendance['total_days'] ?></strong></div>
                            <div class="col-6"><small>Present</small><br><strong class="text-success"><?= $attendance['present'] ?></strong></div>
                            <div class="col-6"><small>Absent</small><br><strong class="text-danger"><?= $attendance['absent'] ?></strong></div>
                            <div class="col-6"><small>Percentage</small><br><strong><?= $attendance['percentage'] ?>%</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($psychomotor || $affective): ?>
        <div class="row g-3 mb-4">
            <?php if ($psychomotor): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-2"><strong>Psychomotor Assessment</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light"><tr><th>Trait</th><th class="text-center">Rating</th></tr></thead>
                            <tbody>
                                <?php foreach (['creativity' => 'Creativity', 'sports' => 'Sports', 'practical_skills' => 'Practical Skills', 'neatness' => 'Neatness', 'leadership' => 'Leadership'] as $key => $label): ?>
                                <tr><td><?= $label ?></td><td class="text-center"><span class="badge bg-secondary"><?= $psychomotor[$key] ?? 'B' ?></span></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($affective): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-2"><strong>Affective Assessment</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light"><tr><th>Trait</th><th class="text-center">Rating</th></tr></thead>
                            <tbody>
                                <?php foreach (['honesty' => 'Honesty', 'punctuality' => 'Punctuality', 'respect' => 'Respect', 'cooperation' => 'Cooperation', 'responsibility' => 'Responsibility'] as $key => $label): ?>
                                <tr><td><?= $label ?></td><td class="text-center"><span class="badge bg-secondary"><?= $affective[$key] ?? 'B' ?></span></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($comments && ($comments['class_teacher_remark'] || $comments['principal_remark'])): ?>
        <div class="row g-3 mb-4">
            <?php if ($comments['class_teacher_remark']): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-2"><strong>Class Teacher's Remark</strong></div>
                    <div class="card-body"><p class="mb-0"><?= nl2br(sanitizeInput($comments['class_teacher_remark'])) ?></p></div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($comments['principal_remark']): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header py-2"><strong>Principal's Remark</strong></div>
                    <div class="card-body"><p class="mb-0"><?= nl2br(sanitizeInput($comments['principal_remark'])) ?></p></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($insights && ($insights['strengths'] || $insights['weaknesses'] || $insights['recommendations'])): ?>
        <div class="card mb-4 border-info">
            <div class="card-header py-2 bg-info bg-opacity-10"><strong><i class="fas fa-robot me-1"></i>AI Academic Insights</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if ($insights['strengths']): ?>
                    <div class="col-md-4">
                        <h6 class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Strengths</h6>
                        <ul class="small mb-0 ps-3"><?php foreach (explode("\n", $insights['strengths']) as $s): if (trim($s)): ?><li><?= sanitizeInput($s) ?></li><?php endif; endforeach; ?></ul>
                    </div>
                    <?php endif; ?>
                    <?php if ($insights['weaknesses']): ?>
                    <div class="col-md-4">
                        <h6 class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>Areas for Improvement</h6>
                        <ul class="small mb-0 ps-3"><?php foreach (explode("\n", $insights['weaknesses']) as $s): if (trim($s)): ?><li><?= sanitizeInput($s) ?></li><?php endif; endforeach; ?></ul>
                    </div>
                    <?php endif; ?>
                    <?php if ($insights['recommendations']): ?>
                    <div class="col-md-4">
                        <h6 class="text-primary fw-bold"><i class="fas fa-lightbulb me-1"></i>Recommendations</h6>
                        <ul class="small mb-0 ps-3"><?php foreach (explode("\n", $insights['recommendations']) as $s): if (trim($s)): ?><li><?= sanitizeInput($s) ?></li><?php endif; endforeach; ?></ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-3 align-items-end">
            <?php if ($qrUrl): ?>
            <div class="col-md-4 text-center">
                <img src="<?= $qrUrl ?>" alt="QR Code" style="width: 120px; height: 120px;">
                <p class="small text-muted mt-1 mb-0">Scan to verify result</p>
            </div>
            <?php endif; ?>
            <?php if ($promotion): ?>
            <div class="col-md-4 text-center">
                <h6 class="fw-bold mb-2">Promotion Status</h6>
                <?php $promBadge = match ($promotion['promotion_status']) { 'promoted' => 'bg-success', 'conditional' => 'bg-warning text-dark', 'repeated' => 'bg-danger', 'graduated' => 'bg-info', default => 'bg-secondary' }; ?>
                <span class="badge <?= $promBadge ?> fs-6"><?= ucfirst($promotion['promotion_status']) ?></span>
                <?php if ($promotion['remark']): ?><p class="small mt-1 mb-0"><?= sanitizeInput($promotion['remark']) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="col-md-4 text-end">
                <p class="small text-muted mb-0">Generated on: <?= date('d M, Y') ?></p>
                <p class="small text-muted mb-0"><?= SCHOOL_NAME ?></p>
            </div>
        </div>
    </div>
</div>
<?php elseif ($sessionId): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>No results available for the selected term.
</div>
<?php endif; ?>

<?php
function niceOrdinal(int $num): string {
    if ($num >= 11 && $num <= 13) return 'th';
    return match ($num % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
}
?>

<style>
@media print {
    .no-print, #sidebar-wrapper, .navbar, footer, form, .btn { display: none !important; }
    #page-content-wrapper { margin-left: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    body { background: white !important; font-size: 12px; }
    .table { font-size: 11px; }
    #report-card { border: none !important; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
