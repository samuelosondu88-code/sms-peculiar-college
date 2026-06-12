<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../includes/result_functions.php';

$pageTitle = 'Result Verification - ' . SCHOOL_NAME;
$db = getDB();

$pin = $_GET['pin'] ?? '';
$sessionId = (int)($_GET['sid'] ?? 0);
$termId = (int)($_GET['tid'] ?? 0);
$studentId = (int)($_GET['stid'] ?? 0);

$verified = false;
$student = [];
$summary = [];
$sessionName = '';
$termName = '';

if ($pin && $sessionId && $termId && $studentId) {
    $cleanPin = sanitizeInput($pin);
    $valid = validateResultPin($db, $cleanPin, $studentId, $sessionId, $termId);

    if ($valid) {
        $verified = true;

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

        if ($student) {
            $summary = getStudentTermSummary($db, $studentId, $sessionId, $termId);
        }

        $stmt = $db->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $sessionName = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT term_name FROM terms WHERE id = ?");
        $stmt->execute([$termId]);
        $termName = $stmt->fetchColumn();
    }
}

$promotion = [];
if ($verified && $studentId) {
    $stmt = $db->prepare("SELECT promotion_status, annual_average FROM promotion_results WHERE student_id = ? AND session_id = ?");
    $stmt->execute([$studentId, $sessionId]);
    $promotion = $stmt->fetch() ?: [];
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
    <style>
        body {
            background: linear-gradient(135deg, #0B1F3A 0%, #1a3555 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .verify-card {
            max-width: 500px;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
        }
        .verify-card .card-body {
            padding: 30px;
        }
        .stamp {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 10px;
        }
        .stamp-valid { background: #d4edda; color: #155724; border: 3px solid #28a745; }
        .stamp-invalid { background: #f8d7da; color: #721c24; border: 3px solid #dc3545; }
    </style>
</head>
<body>
    <div class="verify-card">
        <?php if ($verified && !empty($student)): ?>
        <div class="card shadow">
            <div class="card-body text-center">
                <div class="stamp stamp-valid">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4 class="fw-bold text-success mb-1">Authentic Result</h4>
                <p class="text-muted small mb-3">This result has been verified and is authentic.</p>

                <div class="border-top pt-3 mt-3">
                    <div class="text-center mb-3">
                        <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="School Logo" style="width: 60px; height: 60px; border-radius: 50%; border: 2px solid #D4AF37; object-fit: cover;">
                        <h5 class="fw-bold mt-2"><?= SCHOOL_NAME ?></h5>
                    </div>

                    <table class="table table-sm table-borderless text-start">
                        <tr>
                            <td style="width: 120px;"><strong>Student:</strong></td>
                            <td><?= sanitizeInput($student['first_name'] . ' ' . $student['last_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Admission No:</strong></td>
                            <td><?= sanitizeInput($student['admission_no']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Class:</strong></td>
                            <td><?= sanitizeInput($student['class_name'] . ' ' . $student['section']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Session:</strong></td>
                            <td><?= sanitizeInput($sessionName) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Term:</strong></td>
                            <td><?= sanitizeInput($termName) ?></td>
                        </tr>
                        <?php if (!empty($summary)): ?>
                        <tr>
                            <td><strong>Average:</strong></td>
                            <td><strong><?= number_format($summary['average'], 1) ?>%</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Grade:</strong></td>
                            <td>
                                <span class="badge bg-<?= $summary['overall_grade'] === 'A' || $summary['overall_grade'] === 'B' ? 'success' : ($summary['overall_grade'] === 'F' ? 'danger' : 'warning') ?>">
                                    <?= $summary['overall_grade'] ?> - <?= getGradeRemark($summary['overall_grade']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Subjects:</strong></td>
                            <td><?= $summary['subject_count'] ?> (<?= $summary['pass_count'] ?> Passed)</td>
                        </tr>
                        <?php if ($promotion): ?>
                        <tr>
                            <td><strong>Promotion:</strong></td>
                            <td><span class="badge bg-<?= $promotion['promotion_status'] === 'promoted' ? 'success' : ($promotion['promotion_status'] === 'repeated' ? 'danger' : 'warning') ?>"><?= ucfirst($promotion['promotion_status']) ?></span></td>
                        </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                    </table>

                    <div class="border-top pt-3 mt-2">
                        <p class="small text-muted mb-0">
                            <i class="fas fa-shield-alt me-1"></i>
                            Verified on <?= date('d M, Y \a\t h:i A') ?>
                        </p>
                        <p class="small text-muted mb-0"><?= SCHOOL_NAME ?> &middot; Official Result Verification</p>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="card shadow">
            <div class="card-body text-center">
                <div class="stamp stamp-invalid">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h4 class="fw-bold text-danger mb-1">Invalid Result</h4>
                <p class="text-muted small mb-0">
                    <?php if (!$pin): ?>
                    No verification data provided.
                    <?php else: ?>
                    The result could not be verified. The PIN may be invalid, expired, or already used.
                    <?php endif; ?>
                </p>
                <hr>
                <p class="small text-muted">
                    Please contact <?= SCHOOL_NAME ?> at <?= SCHOOL_PHONE ?> or <?= SCHOOL_EMAIL ?> for assistance.
                </p>
                <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="School Logo" style="width: 50px; height: 50px; border-radius: 50%; opacity: 0.5;">
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>" class="text-white-50 small">
                <i class="fas fa-home me-1"></i><?= SCHOOL_NAME ?>
            </a>
        </div>
    </div>
</body>
</html>
