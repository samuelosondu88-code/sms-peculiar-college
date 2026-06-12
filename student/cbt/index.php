<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'CBT Exams';
$db = getDB();

$studentId = getStudentId();

// Get available exams (published, and student hasn't completed them)
$availExams = $db->query("
    SELECT e.*, s.name as subject_name, s.code as subject_code,
           (SELECT COUNT(*) FROM cbt_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'completed') as completed_count,
           (SELECT COUNT(*) FROM cbt_attempts WHERE exam_id = e.id AND student_id = $studentId AND status = 'in_progress') as in_progress_count
    FROM cbt_exams e
    JOIN cbt_subjects s ON e.subject_id = s.id
    WHERE e.is_published = 1
    ORDER BY e.title
")->fetchAll();

// Get completed attempts
$completedAttempts = $db->query("
    SELECT ca.*, ce.title as exam_title, ce.pass_score, s.name as subject_name
    FROM cbt_attempts ca
    JOIN cbt_exams ce ON ca.exam_id = ce.id
    JOIN cbt_subjects s ON ce.subject_id = s.id
    WHERE ca.student_id = $studentId AND ca.status = 'completed'
    ORDER BY ca.completed_at DESC
")->fetchAll();

// Get in-progress attempts
$inProgress = $db->query("
    SELECT ca.*, ce.title as exam_title, ce.duration_minutes, ce.pass_score, s.name as subject_name
    FROM cbt_attempts ca
    JOIN cbt_exams ce ON ca.exam_id = ce.id
    JOIN cbt_subjects s ON ce.subject_id = s.id
    WHERE ca.student_id = $studentId AND ca.status = 'in_progress'
    ORDER BY ca.started_at DESC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Computer-Based Tests</h4>
        <p class="text-muted small mb-0">Take exams and track your performance</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/student/cbt/analytics.php" class="btn btn-gold">
            <i class="fas fa-chart-bar me-1"></i>Performance Analytics
        </a>
    </div>
</div>

<?php if (!empty($inProgress)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    You have <strong><?= count($inProgress) ?></strong> exam(s) in progress.
    <?php foreach ($inProgress as $ip): ?>
    <br><a href="<?= BASE_URL ?>/student/cbt/exam.php?attempt_id=<?= $ip['id'] ?>" class="alert-link">Resume: <?= sanitizeInput($ip['exam_title']) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-primary">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-value"><?= count($availExams) ?></div>
            <div class="stat-label">Available Exams</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-gold">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= count($completedAttempts) ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-navy">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-value"><?= count($completedAttempts) > 0 ? round(array_sum(array_column($completedAttempts, 'score')) / count($completedAttempts), 1) : 0 ?>%</div>
            <div class="stat-label">Average Score</div>
        </div>
    </div>
</div>

<?php if (!empty($inProgress)): ?>
<h5 class="fw-bold mb-3"><i class="fas fa-play-circle text-warning me-2"></i>In Progress</h5>
<div class="row g-3 mb-4">
    <?php foreach ($inProgress as $ip): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-primary"><?= sanitizeInput($ip['subject_name']) ?></span>
                    <span class="badge bg-warning text-dark">In Progress</span>
                </div>
                <h6 class="fw-bold"><?= sanitizeInput($ip['exam_title']) ?></h6>
                <p class="small text-muted mb-2"><i class="fas fa-clock me-1"></i><?= $ip['duration_minutes'] ?> min</p>
                <a href="<?= BASE_URL ?>/student/cbt/exam.php?attempt_id=<?= $ip['id'] ?>" class="btn btn-warning btn-sm w-100">
                    <i class="fas fa-play me-1"></i>Resume Exam
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h5 class="fw-bold mb-3"><i class="fas fa-book-open text-primary me-2"></i>Available Exams</h5>
<div class="row g-3 mb-4">
    <?php foreach ($availExams as $e): ?>
    <?php
        $hasCompleted = $e['completed_count'] > 0;
        $hasInProgress = $e['in_progress_count'] > 0;
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 <?= $hasCompleted ? 'border-success' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-primary"><?= sanitizeInput($e['subject_code']) ?></span>
                    <?php if ($hasCompleted): ?>
                    <span class="badge bg-success">Completed</span>
                    <?php elseif ($hasInProgress): ?>
                    <span class="badge bg-warning text-dark">In Progress</span>
                    <?php else: ?>
                    <span class="badge bg-info text-dark">New</span>
                    <?php endif; ?>
                </div>
                <h6 class="fw-bold"><?= sanitizeInput($e['title']) ?></h6>
                <p class="small text-muted mb-0">
                    <i class="fas fa-question-circle me-1"></i><?= $e['total_questions'] ?> questions |
                    <i class="fas fa-clock me-1"></i><?= $e['duration_minutes'] ?> min |
                    <i class="fas fa-check-circle me-1"></i>Pass: <?= $e['pass_score'] ?>%
                </p>
                <?php if ($e['instructions']): ?>
                <p class="small text-muted mt-1 mb-2"><?= sanitizeInput($e['instructions']) ?></p>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/student/cbt/exam.php?exam_id=<?= $e['id'] ?>" class="btn <?= $hasCompleted ? 'btn-outline-success' : 'btn-primary' ?> btn-sm w-100 mt-2">
                    <?php if ($hasCompleted): ?>
                    <i class="fas fa-redo me-1"></i>Retake
                    <?php elseif ($hasInProgress): ?>
                    <i class="fas fa-play me-1"></i>Continue
                    <?php else: ?>
                    <i class="fas fa-play me-1"></i>Start Exam
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($availExams)): ?>
    <div class="col-12">
        <div class="alert alert-info">No exams are currently available. Check back later.</div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($completedAttempts)): ?>
<h5 class="fw-bold mb-3"><i class="fas fa-history text-success me-2"></i>Your Results</h5>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Subject</th>
                    <th>Score</th>
                    <th>Correct</th>
                    <th>Passed</th>
                    <th>Date</th>
                    <th>Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completedAttempts as $a): ?>
                <?php $passed = $a['score'] >= $a['pass_score']; ?>
                <tr>
                    <td><?= sanitizeInput($a['exam_title']) ?></td>
                    <td><span class="badge bg-primary"><?= sanitizeInput($a['subject_name']) ?></span></td>
                    <td>
                        <span class="badge <?= $passed ? 'bg-success' : 'bg-danger' ?>" style="font-size: 13px;"><?= $a['score'] ?>%</span>
                    </td>
                    <td><?= $a['correct_count'] ?>/<?= $a['total_questions'] ?></td>
                    <td>
                        <?php if ($passed): ?>
                        <span class="text-success"><i class="fas fa-check-circle"></i></span>
                        <?php else: ?>
                        <span class="text-danger"><i class="fas fa-times-circle"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $a['completed_at'] ? formatDate($a['completed_at']) : '-' ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/student/cbt/results.php?attempt_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>Review
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
