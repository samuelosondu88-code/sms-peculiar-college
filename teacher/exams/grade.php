<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Grade Responses';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$attemptId = (int)($_GET['attempt_id'] ?? 0);
$stmt = $db->prepare("SELECT ea.*, te.title, te.teacher_id, te.total_marks, te.subject_id, te.class_id,
    u.first_name, u.last_name, u.email, sub.name as subject_name
    FROM exam_attempts ea
    JOIN teacher_exams te ON ea.exam_id = te.id
    JOIN users u ON ea.student_id = u.id
    JOIN subjects sub ON te.subject_id = sub.id
    WHERE ea.id = ? AND te.teacher_id = ?");
$stmt->execute([$attemptId, $userId]);
$attempt = $stmt->fetch();
if (!$attempt) redirect('/teacher/exams/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $totalManual = 0;
    foreach ($_POST['manual_score'] as $rId => $score) {
        $rId = (int)$rId;
        $score = (float)$score;
        $stmt = $db->prepare("UPDATE exam_responses SET manual_score = ?, total_score = auto_score + ?, graded_by = ?, graded_at = NOW() WHERE id = ? AND attempt_id = ?");
        $stmt->execute([$score, $score, $userId, $rId, $attemptId]);
        $totalManual += $score;
    }
    $stmt = $db->prepare("UPDATE exam_attempts SET manual_score = ?, total_score = auto_score + ?, status = 'graded' WHERE id = ?");
    $stmt->execute([$totalManual, $totalManual, $attemptId]);
    $stmt = $db->prepare("SELECT total_score, ? as total_marks FROM exam_attempts WHERE id = ?");
    $stmt->execute([$attempt['total_marks'], $attemptId]);
    $upd = $stmt->fetch();
    $pct = $upd['total_marks'] > 0 ? round(($upd['total_score'] / $upd['total_marks']) * 100, 1) : 0;
    $grade = $pct >= 70 ? 'A' : ($pct >= 60 ? 'B' : ($pct >= 50 ? 'C' : ($pct >= 45 ? 'D' : ($pct >= 40 ? 'E' : 'F'))));
    $db->prepare("UPDATE exam_attempts SET percentage = ?, grade = ? WHERE id = ?")->execute([$pct, $grade, $attemptId]);
    redirect("/teacher/exams/results.php?exam_id={$attempt['exam_id']}");
}

$responses = $db->prepare("SELECT er.*, eq.question_type, eq.question_text, eq.correct_answer, eq.marks, eq.explanation
    FROM exam_responses er
    JOIN exam_questions eq ON er.question_id = eq.id
    WHERE er.attempt_id = ?
    ORDER BY er.id");
$responses->execute([$attemptId]);
$responseList = $responses->fetchAll();

$qTypes = ['mcq'=>'Multiple Choice','true_false'=>'True/False','fill_blank'=>'Fill in the Blank','short_answer'=>'Short Answer','essay'=>'Essay'];
$totalAuto = 0;
$totalManual = 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-edit me-2"></i>Grade Responses</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($attempt['first_name'] . ' ' . $attempt['last_name']) ?> — <?= sanitizeInput($attempt['subject_name']) ?></p>
    </div>
    <a href="results.php?exam_id=<?= $attempt['subject_id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<form method="POST">
    <?php foreach ($responseList as $r): $totalAuto += $r['auto_score']; ?>
    <div class="card mb-3 <?= $r['question_type'] === 'essay' || $r['question_type'] === 'short_answer' ? 'border-warning' : '' ?>">
        <div class="card-header d-flex justify-content-between">
            <span><i class="fas fa-question-circle me-2"></i><?= $qTypes[$r['question_type']] ?? $r['question_type'] ?></span>
            <span class="badge bg-secondary"><?= $r['marks'] ?> marks</span>
        </div>
        <div class="card-body">
            <p class="fw-bold"><?= sanitizeInput($r['question_text']) ?></p>

            <?php if (in_array($r['question_type'], ['mcq','true_false','fill_blank'])): ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Student's Answer</label>
                    <div class="p-2 bg-light rounded <?= $r['is_correct'] ? 'border border-success' : 'border border-danger' ?>">
                        <?= sanitizeInput($r['response'] ?: '(No answer)') ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correct Answer</label>
                    <div class="p-2 bg-light rounded border border-success"><?= sanitizeInput($r['correct_answer'] ?: '-') ?></div>
                </div>
            </div>
            <div class="mt-2">
                <?php if ($r['is_correct']): ?>
                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Correct (+<?= $r['auto_score'] ?>)</span>
                <?php else: ?>
                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Incorrect</span>
                <?php endif; ?>
                <?php if ($r['explanation']): ?>
                <small class="text-muted ms-2"><i class="fas fa-info-circle me-1"></i><?= sanitizeInput($r['explanation']) ?></small>
                <?php endif; ?>
                <input type="hidden" name="manual_score[<?= $r['id'] ?>]" value="0">
            </div>

            <?php else: ?>
            <div class="mb-3">
                <label class="form-label">Student's Answer</label>
                <div class="p-3 bg-light rounded"><?= nl2br(sanitizeInput($r['response'] ?: '(No answer)')) ?></div>
            </div>
            <?php if ($r['correct_answer']): ?>
            <div class="mb-3">
                <label class="form-label">Expected Answer / Rubric</label>
                <div class="p-2 bg-light rounded border border-info"><?= nl2br(sanitizeInput($r['correct_answer'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Award Marks (max <?= $r['marks'] ?>)</label>
                    <input type="number" name="manual_score[<?= $r['id'] ?>]" class="form-control" min="0" max="<?= $r['marks'] ?>" step="0.5" value="<?= $r['manual_score'] ?: 0 ?>">
                </div>
                <div class="col-md-4">
                    <span class="text-muted small">Auto: <?= $r['auto_score'] ?> | Manual: <?= $r['manual_score'] ?> | Total: <?= $r['total_score'] ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($responseList)): ?>
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <strong>Auto-graded: <?= $totalAuto ?> / <?= $attempt['total_marks'] ?></strong>
            </div>
            <button type="submit" name="save_grades" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Grades</button>
        </div>
    </div>
    <?php endif; ?>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
