<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Exam Results';
$db = getDB();
$userId = $_SESSION['user_id'];

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT te.*, sub.name as subject_name, c.name as class_name, c.section FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id JOIN classes c ON te.class_id = c.id WHERE te.id = ?");
$stmt->execute([$examId]);
$exam = $stmt->fetch();
if (!$exam) redirect('/student/exams/index.php');

$attemptStmt = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1");
$attemptStmt->execute([$examId, $userId]);
$attempt = $attemptStmt->fetch();
if (!$attempt) redirect('/student/exams/index.php');

$responses = $db->prepare("SELECT er.*, eq.question_type, eq.question_text, eq.correct_answer, eq.marks, eq.explanation, eq.option_a, eq.option_b, eq.option_c, eq.option_d FROM exam_responses er JOIN exam_questions eq ON er.question_id = eq.id WHERE er.attempt_id = ? ORDER BY er.id");
$responses->execute([$attempt['id']]);
$responseList = $responses->fetchAll();

$qTypes = ['mcq'=>'Multiple Choice','true_false'=>'True/False','fill_blank'=>'Fill in the Blank','short_answer'=>'Short Answer','essay'=>'Essay'];
$correctCount = 0; $wrongCount = 0; $pendingCount = 0;
foreach ($responseList as $r) {
    if ($r['question_type'] === 'essay' || $r['question_type'] === 'short_answer') {
        if ($r['manual_score'] > 0) $correctCount++;
        elseif ($r['manual_score'] === null || $r['total_score'] === null) $pendingCount++;
        else $wrongCount++;
    } else {
        if ($r['is_correct']) $correctCount++; else $wrongCount++;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>Exam Results</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($exam['title']) ?> — <?= sanitizeInput($exam['subject_name']) ?> | <?= sanitizeInput($exam['class_name'] . ' ' . $exam['section']) ?></p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-star stat-icon"></i>
            <div class="stat-value"><?= $attempt['total_score'] ?: 0 ?> / <?= $exam['total_marks'] ?></div>
            <div class="stat-label">Total Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-value"><?= $attempt['percentage'] ?>%</div>
            <div class="stat-label">Percentage</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card <?= ($attempt['grade'] ?? 'F') === 'F' ? 'bg-danger' : 'stat-success' ?>" style="background:linear-gradient(135deg,#16a34a,#22c55e)">
            <i class="fas fa-award stat-icon"></i>
            <div class="stat-value"><?= $attempt['grade'] ?: '-' ?></div>
            <div class="stat-label">Grade</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#2563eb,#3b82f6)">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value"><?= $attempt['status'] === 'graded' ? 'Graded' : 'Pending' ?></div>
            <div class="stat-label">Status</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h2><?= $correctCount ?></h2>
                <small>Correct</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h2><?= $wrongCount ?></h2>
                <small>Incorrect</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h2><?= $pendingCount ?></h2>
                <small>Pending Review</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="fas fa-list me-2"></i>Answer Review</div>
    <div class="card-body p-0">
        <?php foreach ($responseList as $i => $r): ?>
        <div class="border-bottom p-3 <?= $i % 2 === 0 ? 'bg-light' : '' ?>">
            <div class="d-flex justify-content-between">
                <strong>Q<?= $i + 1 ?>. <?= $qTypes[$r['question_type']] ?? $r['question_type'] ?></strong>
                <span class="badge bg-secondary"><?= $r['marks'] ?> mk</span>
            </div>
            <p class="mb-2"><?= sanitizeInput($r['question_text']) ?></p>

            <?php if (in_array($r['question_type'], ['mcq','true_false','fill_blank'])): ?>
            <div class="row g-2 small">
                <div class="col-md-4"><strong>Your answer:</strong> <span class="<?= $r['is_correct'] ? 'text-success' : 'text-danger' ?>"><?= sanitizeInput($r['response'] ?: '(No answer)') ?></span></div>
                <div class="col-md-4"><strong>Correct answer:</strong> <span class="text-success"><?= sanitizeInput($r['correct_answer'] ?: '-') ?></span></div>
                <div class="col-md-4">
                    <?php if ($r['is_correct']): ?><span class="badge bg-success">+<?= $r['auto_score'] ?></span>
                    <?php else: ?><span class="badge bg-danger">0</span><?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <div class="small">
                <div><strong>Your answer:</strong><br><?= nl2br(sanitizeInput($r['response'] ?: '(No answer)')) ?></div>
                <?php if ($r['manual_score'] > 0 || $r['manual_score'] === '0'): ?>
                <div class="mt-1"><strong>Score:</strong> <?= $r['manual_score'] ?: 0 ?> / <?= $r['marks'] ?></div>
                <?php else: ?>
                <div class="mt-1 text-warning"><i class="fas fa-clock me-1"></i>Awaiting grading</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($r['explanation'])): ?>
            <small class="text-muted d-block mt-1"><i class="fas fa-info-circle me-1"></i><?= sanitizeInput($r['explanation']) ?></small>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
