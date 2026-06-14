<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/includes/result_functions.php';

$pageTitle = 'Check Your Result - ' . SCHOOL_NAME;
$db = getDB();
$error = '';
$showResult = false;
$student = [];
$summary = [];
$position = 0;
$attendance = [];
$sessionName = '';
$termName = '';
$psychomotor = [];
$affective = [];
$comments = [];
$insights = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admissionNo = sanitizeInput($_POST['admission_no'] ?? '');
    $pin = sanitizeInput($_POST['pin'] ?? '');

    if (empty($admissionNo) || empty($pin)) {
        $error = 'Please enter both Admission Number and PIN.';
    } else {
        $stmt = $db->prepare("
            SELECT s.id, s.class_id, s.admission_no, u.first_name, u.last_name,
                   c.name as class_name, c.section
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN classes c ON s.class_id = c.id
            WHERE s.admission_no = ?
        ");
        $stmt->execute([$admissionNo]);
        $student = $stmt->fetch();

        if (!$student) {
            $error = 'Invalid admission number.';
        } else {
            $pinData = $db->prepare("SELECT id, pin, session_id, term_id FROM result_pins WHERE pin = ? AND student_id = ? AND is_active = 1 AND is_used = 0 AND (expires_at IS NULL OR expires_at >= CURDATE()) LIMIT 1");
            $pinData->execute([$pin, $student['id']]);
            $pinRow = $pinData->fetch();

            if (!$pinRow) {
                $error = 'Invalid or expired PIN.';
            } else {
                if (validateResultPin($db, $pin, $student['id'], $pinRow['session_id'], $pinRow['term_id'])) {
                    $sessionId = $pinRow['session_id'];
                    $termId = $pinRow['term_id'];

                    $summary = getStudentTermSummary($db, $student['id'], $sessionId, $termId);
                    $position = getClassPosition($db, $student['id'], $student['class_id'], $sessionId, $termId);
                    $attendance = getAttendanceStats($db, $student['id'], $student['class_id'], $sessionId, $termId);

                    $stmt2 = $db->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
                    $stmt2->execute([$sessionId]); $sessionName = $stmt2->fetchColumn();

                    $stmt2 = $db->prepare("SELECT term_name FROM terms WHERE id = ?");
                    $stmt2->execute([$termId]); $termName = $stmt2->fetchColumn();

                    $p = $db->prepare("SELECT * FROM psychomotor_assessments WHERE student_id = ? AND session_id = ? AND term_id = ?");
                    $p->execute([$student['id'], $sessionId, $termId]); $psychomotor = $p->fetch() ?: [];

                    $a = $db->prepare("SELECT * FROM affective_assessments WHERE student_id = ? AND session_id = ? AND term_id = ?");
                    $a->execute([$student['id'], $sessionId, $termId]); $affective = $a->fetch() ?: [];

                    $c = $db->prepare("SELECT * FROM result_comments WHERE student_id = ? AND session_id = ? AND term_id = ?");
                    $c->execute([$student['id'], $sessionId, $termId]); $comments = $c->fetch() ?: [];

                    $i = $db->prepare("SELECT * FROM academic_insights WHERE student_id = ? AND session_id = ? AND term_id = ?");
                    $i->execute([$student['id'], $sessionId, $termId]); $insights = $i->fetch() ?: [];

                    $showResult = true;
                } else {
                    $error = 'Invalid or expired PIN.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>/assets/images/logo.jpg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body { background: linear-gradient(135deg, #0B1F3A 0%, #1a3555 100%); min-height: 100vh; display: flex; align-items: flex-start; padding-top: 40px; }
        .checker-container { max-width: 500px; margin: 0 auto; }
        .report-container { max-width: 1000px; margin: 0 auto; }
        .card { border: none; border-radius: 16px; }
        @media print { .no-print { display: none !important; } body { background: white !important; padding-top: 0 !important; } .card { box-shadow: none !important; border: 1px solid #ddd !important; } }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$showResult): ?>
        <div class="checker-container">
            <div class="text-center mb-4">
                <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="School Logo" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid #D4AF37; object-fit: cover;">
                <h4 class="text-white mt-3 fw-bold"><?= SCHOOL_NAME ?></h4>
                <p class="text-white-50">Enter your Admission Number and PIN to check your result</p>
            </div>
            <div class="card shadow">
                <div class="card-body p-4">
                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-id-card me-1"></i>Admission Number</label>
                            <input type="text" name="admission_no" class="form-control" placeholder="Enter admission number" required value="<?= sanitizeInput($_POST['admission_no'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-key me-1"></i>Result PIN</label>
                            <input type="text" name="pin" class="form-control" placeholder="Enter your PIN (e.g. ABCD-1234)" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="background: #D4AF37; border-color: #D4AF37; color: #0B1F3A;">
                            <i class="fas fa-check-circle me-1"></i>View Result
                        </button>
                    </form>
                    <hr>
                    <p class="text-center small text-muted mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        PINs are provided by the school administration. Contact your school for a PIN if you do not have one.
                    </p>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>/auth/login.php" class="text-white-50 small"><i class="fas fa-sign-in-alt me-1"></i>Login to Portal</a>
                &middot;
                <a href="<?= BASE_URL ?>" class="text-white-50 small"><i class="fas fa-home me-1"></i>Home</a>
            </div>
        </div>
        <?php else: ?>
        <div class="report-container">
            <div class="text-center mb-3 no-print">
                <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
                <a href="<?= BASE_URL ?>/result-checker.php" class="btn btn-outline-light btn-sm ms-2"><i class="fas fa-times me-1"></i>Close</a>
            </div>
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="text-center mb-4 border-bottom pb-3">
                        <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="School Logo" style="width: 70px; height: 70px; object-fit: cover; border-radius: 50%; border: 2px solid #D4AF37;">
                        <h4 class="fw-bold mt-2 mb-0"><?= SCHOOL_NAME ?></h4>
                        <small class="text-muted"><?= SCHOOL_ADDRESS ?></small>
                        <h5 class="mt-3 fw-bold text-uppercase" style="color: #D4AF37;">Report Card</h5>
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
                                        <span class="badge bg-<?= $r['grade'] === 'A' ? 'success' : ($r['grade'] === 'F' ? 'danger' : ($r['grade'] === 'B' ? 'info' : 'warning')) ?>"><?= $r['grade'] ?: '-' ?></span>
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
                                <tr><td style="width: 160px;"><strong>Total Marks:</strong></td><td><?= $summary['total_marks'] ?></td></tr>
                                <tr><td><strong>Subjects:</strong></td><td><?= $summary['subject_count'] ?></td></tr>
                                <tr><td><strong>Average:</strong></td><td><strong><?= number_format($summary['average'], 1) ?>%</strong></td></tr>
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
                            <div class="card"><div class="card-header py-2"><strong>Psychomotor Assessment</strong></div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light"><tr><th>Trait</th><th class="text-center">Rating</th></tr></thead>
                                        <tbody><?php foreach (['creativity'=>'Creativity','sports'=>'Sports','practical_skills'=>'Practical Skills','neatness'=>'Neatness','leadership'=>'Leadership'] as $k=>$l): ?><tr><td><?=$l?></td><td class="text-center"><span class="badge bg-secondary"><?=$psychomotor[$k]??'B'?></span></td></tr><?php endforeach; ?></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($affective): ?>
                        <div class="col-md-6">
                            <div class="card"><div class="card-header py-2"><strong>Affective Assessment</strong></div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="table-light"><tr><th>Trait</th><th class="text-center">Rating</th></tr></thead>
                                        <tbody><?php foreach (['honesty'=>'Honesty','punctuality'=>'Punctuality','respect'=>'Respect','cooperation'=>'Cooperation','responsibility'=>'Responsibility'] as $k=>$l): ?><tr><td><?=$l?></td><td class="text-center"><span class="badge bg-secondary"><?=$affective[$k]??'B'?></span></td></tr><?php endforeach; ?></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($comments && ($comments['class_teacher_remark'] || $comments['principal_remark'])): ?>
                    <div class="row g-3 mb-4">
                        <?php if ($comments['class_teacher_remark']): ?><div class="col-md-6"><div class="card"><div class="card-header py-2"><strong>Class Teacher's Remark</strong></div><div class="card-body"><p class="mb-0"><?=nl2br(sanitizeInput($comments['class_teacher_remark']))?></p></div></div></div><?php endif; ?>
                        <?php if ($comments['principal_remark']): ?><div class="col-md-6"><div class="card"><div class="card-header py-2"><strong>Principal's Remark</strong></div><div class="card-body"><p class="mb-0"><?=nl2br(sanitizeInput($comments['principal_remark']))?></p></div></div></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($insights && ($insights['strengths'] || $insights['weaknesses'] || $insights['recommendations'])): ?>
                    <div class="card mb-4 border-info">
                        <div class="card-header py-2 bg-info bg-opacity-10"><strong><i class="fas fa-robot me-1"></i>Academic Insights</strong></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php if ($insights['strengths']): ?><div class="col-md-4"><h6 class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Strengths</h6><ul class="small mb-0 ps-3"><?php foreach(explode("\n",$insights['strengths']) as $s): if(trim($s)): ?><li><?=sanitizeInput($s)?></li><?php endif; endforeach; ?></ul></div><?php endif; ?>
                                <?php if ($insights['weaknesses']): ?><div class="col-md-4"><h6 class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>Areas for Improvement</h6><ul class="small mb-0 ps-3"><?php foreach(explode("\n",$insights['weaknesses']) as $s): if(trim($s)): ?><li><?=sanitizeInput($s)?></li><?php endif; endforeach; ?></ul></div><?php endif; ?>
                                <?php if ($insights['recommendations']): ?><div class="col-md-4"><h6 class="text-primary fw-bold"><i class="fas fa-lightbulb me-1"></i>Recommendations</h6><ul class="small mb-0 ps-3"><?php foreach(explode("\n",$insights['recommendations']) as $s): if(trim($s)): ?><li><?=sanitizeInput($s)?></li><?php endif; endforeach; ?></ul></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4 text-center">
                            <p class="small text-muted mb-0">Verified via Result Checker</p>
                        </div>
                        <div class="col-md-4 text-center"></div>
                        <div class="col-md-4 text-end">
                            <p class="small text-muted mb-0">Generated: <?= date('d M, Y') ?></p>
                            <p class="small text-muted mb-0"><?= SCHOOL_NAME ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
function niceOrdinal($num) {
    if ($num >= 11 && $num <= 13) return 'th';
    return match ($num % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
}
?>
