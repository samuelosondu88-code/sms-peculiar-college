<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Exam Results';
$db = getDB();

$studentId = getStudentId();
$attempt_id = (int)($_GET['attempt_id'] ?? 0);

$stmt = $db->prepare("
    SELECT ca.*, ce.title as exam_title, ce.duration_minutes, ce.pass_score, ce.instructions,
           s.name as subject_name, s.code as subject_code
    FROM cbt_attempts ca
    JOIN cbt_exams ce ON ca.exam_id = ce.id
    JOIN cbt_subjects s ON ce.subject_id = s.id
    WHERE ca.id = ? AND ca.student_id = ?
");
$stmt->execute([$attempt_id, $studentId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    redirect('/student/cbt/index.php');
}

$questions = $db->prepare("
    SELECT q.*, a.selected_answer, a.is_correct,
           eq.question_order
    FROM cbt_exam_questions eq
    JOIN cbt_questions q ON eq.question_id = q.id
    LEFT JOIN cbt_answers a ON a.question_id = q.id AND a.attempt_id = ?
    WHERE eq.exam_id = ?
    ORDER BY eq.question_order
");
$questions->execute([$attempt_id, $attempt['exam_id']]);
$questions = $questions->fetchAll();

$passed = $attempt['score'] >= $attempt['pass_score'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Exam Results</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($attempt['exam_title']) ?></p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/student/cbt/index.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-1"></i>Back to Exams
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card <?= $passed ? 'stat-gold' : 'stat-dark' ?>">
            <i class="fas fa-percentage stat-icon"></i>
            <div class="stat-value"><?= $attempt['score'] ?>%</div>
            <div class="stat-label">Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #16a34a, #22c55e);">
            <i class="fas fa-check stat-icon"></i>
            <div class="stat-value"><?= $attempt['correct_count'] ?>/<?= $attempt['total_questions'] ?></div>
            <div class="stat-label">Correct</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
            <i class="fas fa-times stat-icon"></i>
            <div class="stat-value"><?= $attempt['wrong_count'] ?></div>
            <div class="stat-label">Wrong</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #6b7280, #9ca3af);">
            <i class="fas fa-minus stat-icon"></i>
            <div class="stat-value"><?= $attempt['unanswer_count'] ?></div>
            <div class="stat-label">Unanswered</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row text-center">
            <div class="col-4">
                <small class="text-muted">Subject</small>
                <p class="fw-bold mb-0"><?= sanitizeInput($attempt['subject_name']) ?></p>
            </div>
            <div class="col-4">
                <small class="text-muted">Pass Mark</small>
                <p class="fw-bold mb-0"><?= $attempt['pass_score'] ?>%</p>
            </div>
            <div class="col-4">
                <small class="text-muted">Time Spent</small>
                <p class="fw-bold mb-0"><?= gmdate('i:s', $attempt['time_spent_seconds']) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Answer Review</span>
        <span class="badge bg-info text-dark"><?= count($questions) ?> Questions</span>
    </div>
    <div class="card-body p-0">
        <?php foreach ($questions as $i => $q): ?>
        <?php
            $optionKeys = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'];
            $isCorrect = (int)$q['is_correct'];
            $isUnanswered = empty($q['selected_answer']);
            $cardClass = $isCorrect ? 'border-success' : ($isUnanswered ? 'border-secondary' : 'border-danger');
        ?>
        <div class="p-3 border-bottom <?= $cardClass ?>" style="border-left: 4px solid <?= $isCorrect ? '#16a34a' : ($isUnanswered ? '#6b7280' : '#dc2626') ?>;">
            <div class="d-flex justify-content-between mb-2">
                <strong>Question <?= $i + 1 ?></strong>
                <span>
                    <?php if ($isCorrect): ?>
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Correct</span>
                    <?php elseif ($isUnanswered): ?>
                    <span class="badge bg-secondary">Unanswered</span>
                    <?php else: ?>
                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Wrong</span>
                    <?php endif; ?>
                </span>
            </div>
            <p class="fw-bold"><?= sanitizeInput($q['question_text']) ?></p>

            <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
            <?php
                $opt = $q[$optionKeys[$letter]];
                $isSelected = $q['selected_answer'] === $letter;
                $isAnswer = $q['correct_answer'] === $letter;
                $optClass = '';
                if ($isAnswer) $optClass = 'border-success bg-success-subtle';
                elseif ($isSelected && !$isCorrect) $optClass = 'border-danger bg-danger-subtle';
            ?>
            <div class="p-2 mb-1 rounded border <?= $optClass ?>" style="<?= $isAnswer ? 'border-width: 2px;' : '' ?>">
                <strong><?= $letter ?>.</strong> <?= sanitizeInput($opt) ?>
                <?php if ($isAnswer): ?>
                <span class="badge bg-success float-end"><i class="fas fa-check"></i> Correct Answer</span>
                <?php elseif ($isSelected && !$isCorrect): ?>
                <span class="badge bg-danger float-end">Your Answer</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if ($q['explanation']): ?>
            <div class="mt-2 p-2 bg-light rounded small">
                <strong><i class="fas fa-info-circle me-1 text-primary"></i>Explanation:</strong>
                <?= sanitizeInput($q['explanation']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="text-center mt-4">
    <a href="<?= BASE_URL ?>/student/cbt/index.php" class="btn btn-primary">
        <i class="fas fa-arrow-left me-1"></i>Back to Exam Dashboard
    </a>
    <a href="<?= BASE_URL ?>/student/cbt/exam.php?exam_id=<?= $attempt['exam_id'] ?>" class="btn btn-gold ms-2">
        <i class="fas fa-redo me-1"></i>Retake Exam
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
