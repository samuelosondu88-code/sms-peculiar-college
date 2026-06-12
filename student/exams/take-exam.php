<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Take Exam';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT te.*, sub.name as subject_name, c.name as class_name, c.section FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id JOIN classes c ON te.class_id = c.id WHERE te.id = ? AND te.is_published = 1");
$stmt->execute([$examId]);
$exam = $stmt->fetch();
if (!$exam) {
    redirect('/student/exams/index.php');
}

$studentStmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch();
if (!$student || $student['class_id'] !== $exam['class_id']) {
    redirect('/student/exams/index.php');
}

$attemptStmt = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1");
$attemptStmt->execute([$examId, $userId]);
$attempt = $attemptStmt->fetch();

if (!$attempt) {
    $db->prepare("INSERT INTO exam_attempts (exam_id, student_id, started_at, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$examId, $userId]);
    $attemptId = $db->lastInsertId();
    $attempt = ['id' => $attemptId, 'status' => 'in_progress'];
    $db->prepare("UPDATE teacher_exams SET status = 'in_progress' WHERE id = ? AND status = 'published'")->execute([$examId]);
} else {
    $attemptId = $attempt['id'];
    if ($attempt['status'] !== 'in_progress') {
        redirect('/student/exams/results.php?exam_id=' . $examId);
    }
}

$questionStmt = $db->prepare("SELECT teq.id as teq_id, teq.question_order, eq.* FROM teacher_exam_questions teq JOIN exam_questions eq ON teq.question_id = eq.id WHERE teq.exam_id = ? ORDER BY teq.question_order");
$questionStmt->execute([$examId]);
$questions = $questionStmt->fetchAll();
$totalQuestions = count($questions);

if ($totalQuestions === 0) {
    $db->prepare("UPDATE exam_attempts SET status = 'submitted', submitted_at = NOW() WHERE id = ?")->execute([$attemptId]);
    redirect('/student/exams/index.php');
}

$respStmt = $db->prepare("SELECT question_id, response FROM exam_responses WHERE attempt_id = ?");
$respStmt->execute([$attemptId]);
$responses = [];
foreach ($respStmt->fetchAll() as $r) { $responses[$r['question_id']] = $r['response']; }

$qTypes = ['mcq'=>'Multiple Choice','true_false'=>'True/False','fill_blank'=>'Fill in the Blank','short_answer'=>'Short Answer','essay'=>'Essay'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_answer') {
        $qid = (int)($_POST['qid'] ?? 0);
        $answer = sanitizeInput($_POST['answer'] ?? '');
        if ($qid) {
            $stmt = $db->prepare("INSERT INTO exam_responses (attempt_id, question_id, response) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE response = VALUES(response), is_correct = FALSE, auto_score = 0");
            $stmt->execute([$attemptId, $qid, $answer]);

            $qStmt = $db->prepare("SELECT * FROM exam_questions WHERE id = ?");
            $qStmt->execute([$qid]);
            $qData = $qStmt->fetch();
            $isCorrect = false;
            $autoScore = 0;
            if ($qData) {
                if ($qData['question_type'] === 'mcq' && strtoupper($answer) === strtoupper($qData['correct_answer'])) {
                    $isCorrect = true; $autoScore = $qData['marks'];
                } elseif ($qData['question_type'] === 'true_false' && strtoupper($answer) === strtoupper($qData['correct_answer'])) {
                    $isCorrect = true; $autoScore = $qData['marks'];
                } elseif ($qData['question_type'] === 'fill_blank' && strtolower(trim($answer)) === strtolower(trim($qData['correct_answer']))) {
                    $isCorrect = true; $autoScore = $qData['marks'];
                }
            }
            $db->prepare("UPDATE exam_responses SET is_correct = ?, auto_score = ?, total_score = ?, manual_score = 0 WHERE attempt_id = ? AND question_id = ?")->execute([$isCorrect, $autoScore, $autoScore, $attemptId, $qid]);

            $autoTotal = $db->prepare("SELECT COALESCE(SUM(auto_score),0) FROM exam_responses WHERE attempt_id = ?");
            $autoTotal->execute([$attemptId]);
            $db->prepare("UPDATE exam_attempts SET auto_score = ?, total_score = auto_score + manual_score WHERE id = ?")->execute([$autoTotal->fetchColumn(), $attemptId]);
        }
        echo 'OK'; exit;
    }

    if (isset($_POST['submit_exam'])) {
        foreach ($responses as $qid => $ans) {
            $stmt = $db->prepare("INSERT INTO exam_responses (attempt_id, question_id, response) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE response = VALUES(response)");
            $stmt->execute([$attemptId, $qid, $ans]);
        }
        $db->prepare("UPDATE exam_attempts SET status = 'submitted', submitted_at = NOW() WHERE id = ?")->execute([$attemptId]);
        redirect('/student/exams/results.php?exam_id=' . $examId);
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.question-nav { position: sticky; top: 80px; }
.question-btn { width: 40px; height: 40px; margin: 3px; padding: 0; font-size: 13px; }
.question-btn.answered { background-color: #059669; color: #fff; border-color: #059669; }
.question-btn.current { border: 2px solid #D4AF37; font-weight: bold; }
#timer { font-size: 1.4rem; font-weight: bold; color: #dc2626; }
.question-card { display: none; }
.question-card.active { display: block; }
</style>

<div class="row">
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-0"><?= sanitizeInput($exam['title']) ?></h4>
                <small class="text-muted"><?= sanitizeInput($exam['subject_name']) ?> - <?= sanitizeInput($exam['class_name'] . ' ' . $exam['section']) ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span><i class="fas fa-clock me-1"></i><span id="timer">--:--</span></span>
                <button type="button" class="btn btn-danger" onclick="submitExam()"><i class="fas fa-check-circle me-1"></i>Submit</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-9">
        <form id="examForm" method="POST">
            <?php foreach ($questions as $i => $q): ?>
            <div class="question-card card mb-3 <?= $i === 0 ? 'active' : '' ?>" data-qid="<?= $q['id'] ?>" data-index="<?= $i ?>">
                <div class="card-header d-flex justify-content-between">
                    <span><strong>Question <?= $i + 1 ?> of <?= $totalQuestions ?></strong> <span class="badge bg-info"><?= $qTypes[$q['question_type']] ?? $q['question_type'] ?></span></span>
                    <span class="badge bg-secondary"><?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
                </div>
                <div class="card-body">
                    <p class="fw-bold fs-5 mb-4"><?= sanitizeInput($q['question_text']) ?></p>

                    <?php if ($q['question_type'] === 'mcq'): ?>
                    <?php foreach (['A','B','C','D'] as $opt): $optKey = 'option_' . strtolower($opt); if (!empty($q[$optKey])): ?>
                    <div class="form-check mb-2">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="<?= $opt ?>" class="form-check-input" id="q<?= $q['id'] ?>_<?= $opt ?>" <?= (($responses[$q['id']] ?? '') === $opt) ? 'checked' : '' ?> onchange="saveAnswer(<?= $q['id'] ?>)">
                        <label class="form-check-label" for="q<?= $q['id'] ?>_<?= $opt ?>"><?= $opt ?>. <?= sanitizeInput($q[$optKey]) ?></label>
                    </div>
                    <?php endif; endforeach; ?>

                    <?php elseif ($q['question_type'] === 'true_false'): ?>
                    <div class="form-check mb-2">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="True" class="form-check-input" id="q<?= $q['id'] ?>_true" <?= (($responses[$q['id']] ?? '') === 'True') ? 'checked' : '' ?> onchange="saveAnswer(<?= $q['id'] ?>)">
                        <label class="form-check-label" for="q<?= $q['id'] ?>_true">True</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="False" class="form-check-input" id="q<?= $q['id'] ?>_false" <?= (($responses[$q['id']] ?? '') === 'False') ? 'checked' : '' ?> onchange="saveAnswer(<?= $q['id'] ?>)">
                        <label class="form-check-label" for="q<?= $q['id'] ?>_false">False</label>
                    </div>

                    <?php elseif ($q['question_type'] === 'fill_blank'): ?>
                    <div class="mb-3">
                        <input type="text" name="q_<?= $q['id'] ?>" class="form-control form-control-lg" placeholder="Type your answer..." value="<?= sanitizeInput($responses[$q['id']] ?? '') ?>" onchange="saveAnswer(<?= $q['id'] ?>)" onkeyup="saveAnswer(<?= $q['id'] ?>)">
                    </div>

                    <?php elseif ($q['question_type'] === 'short_answer'): ?>
                    <div class="mb-3">
                        <textarea name="q_<?= $q['id'] ?>" class="form-control" rows="3" placeholder="Type your answer..." onchange="saveAnswer(<?= $q['id'] ?>)" onkeyup="saveAnswer(<?= $q['id'] ?>)"><?= sanitizeInput($responses[$q['id']] ?? '') ?></textarea>
                    </div>

                    <?php elseif ($q['question_type'] === 'essay'): ?>
                    <div class="mb-3">
                        <textarea name="q_<?= $q['id'] ?>" class="form-control" rows="6" placeholder="Write your essay answer..." onchange="saveAnswer(<?= $q['id'] ?>)" onkeyup="saveAnswer(<?= $q['id'] ?>)"><?= sanitizeInput($responses[$q['id']] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="navigateQ(-1)" <?= $i === 0 ? 'disabled' : '' ?>><i class="fas fa-chevron-left me-1"></i>Previous</button>
                    <button type="button" class="btn btn-primary" onclick="navigateQ(1)" <?= $i === $totalQuestions - 1 ? 'disabled' : '' ?>>Next<i class="fas fa-chevron-right ms-1"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="col-lg-3">
        <div class="question-nav card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Questions</div>
            <div class="card-body p-2 text-center">
                <?php foreach ($questions as $i => $q): ?>
                <button type="button" class="btn btn-outline-secondary question-btn" data-qid="<?= $q['id'] ?>" data-index="<?= $i ?>" onclick="goToQ(<?= $i ?>)"><?= $i + 1 ?></button>
                <?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-center gap-2 small">
                    <span><span class="badge bg-success">●</span> Answered</span>
                    <span><span class="badge bg-secondary">●</span> Unanswered</span>
                </div>
                <hr>
                <button type="button" class="btn btn-danger w-100" onclick="submitExam()"><i class="fas fa-check-circle me-1"></i>Submit Exam</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Submit Exam?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>You have answered <strong id="answeredCount">0</strong> of <?= $totalQuestions ?> questions.</p>
                <p class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Unanswered questions will be marked as incorrect.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Review</button>
                <form method="POST">
                    <button type="submit" name="submit_exam" class="btn btn-danger"><i class="fas fa-check-circle me-1"></i>Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $extraScripts = <<<SCRIPT
<script>
let currentQuestion = 0;
const totalQuestions = $totalQuestions;
const examDuration = {$exam['duration_minutes']};
let answeredSet = new Set();
let timerInterval;

function initAnswered() {
    document.querySelectorAll('.question-btn').forEach(btn => {
        const qid = btn.dataset.qid;
        const inputs = document.querySelectorAll(`[name="q_\${qid}"]`);
        let hasValue = false;
        inputs.forEach(inp => { if (inp.type === 'radio' ? inp.checked : inp.value.trim() !== '') hasValue = true; });
        if (hasValue) { btn.classList.add('answered'); answeredSet.add(parseInt(qid)); }
    });
    updateAnsweredCount();
}

function goToQ(idx) {
    document.querySelectorAll('.question-card').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.question-btn').forEach(b => b.classList.remove('current'));
    document.querySelector(`.question-card[data-index="\${idx}"]`).classList.add('active');
    document.querySelector(`.question-btn[data-index="\${idx}"]`).classList.add('current');
    currentQuestion = idx;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function navigateQ(dir) {
    const next = currentQuestion + dir;
    if (next >= 0 && next < totalQuestions) goToQ(next);
}

function saveAnswer(qid) {
    const inputs = document.querySelectorAll(`[name="q_\${qid}"]`);
    let value = '';
    inputs.forEach(inp => {
        if (inp.type === 'radio' && inp.checked) value = inp.value;
        else if (inp.type !== 'radio') value = inp.value;
    });
    const btn = document.querySelector(`.question-btn[data-qid="\${qid}"]`);
    if (value.trim() !== '') {
        btn.classList.add('answered');
        answeredSet.add(qid);
    } else {
        btn.classList.remove('answered');
        answeredSet.delete(qid);
    }
    updateAnsweredCount();
    fetch('', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=save_answer&qid='+qid+'&answer='+encodeURIComponent(value) });
}

function updateAnsweredCount() {
    document.getElementById('answeredCount').textContent = answeredSet.size;
}

function submitExam() {
    document.getElementById('answeredCount').textContent = answeredSet.size;
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

function updateTimer() {
    const now = new Date();
    const start = new Date(startTime);
    const elapsed = Math.floor((now - start) / 1000);
    const remaining = examDuration * 60 - elapsed;
    if (remaining <= 0) {
        document.getElementById('timer').textContent = '00:00';
        clearInterval(timerInterval);
        alert('Time is up! Your exam will be submitted automatically.');
        document.querySelector('[name="submit_exam"]').click();
        return;
    }
    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    document.getElementById('timer').textContent = String(mins).padStart(2,'0') + ':' + String(secs).padStart(2,'0');
}

const startTime = '{$attempt['started_at']}';
document.addEventListener('DOMContentLoaded', function() {
    initAnswered();
    goToQ(0);
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);
});

window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'You have an exam in progress. Are you sure you want to leave?';
});
</script>
SCRIPT;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
