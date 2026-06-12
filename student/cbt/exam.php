<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Take Exam';
$db = getDB();

$studentId = getStudentId();
$exam_id = (int)($_GET['exam_id'] ?? 0);
$attempt_id = (int)($_GET['attempt_id'] ?? 0);
$message = '';
$error = '';

// Handle answer submission (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json');
    try {
        $qid = (int)($_POST['question_id'] ?? 0);
        $aid = (int)($_POST['attempt_id'] ?? 0);
        $answer = strtoupper(trim($_POST['answer'] ?? ''));
        $time_spent = (int)($_POST['time_spent'] ?? 0);

        // Verify attempt belongs to student
        $stmt = $db->prepare("SELECT id FROM cbt_attempts WHERE id = ? AND student_id = ? AND status = 'in_progress'");
        $stmt->execute([$aid, $studentId]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Invalid attempt']);
            exit;
        }

        // Check if correct
        $stmt = $db->prepare("SELECT correct_answer FROM cbt_questions WHERE id = ?");
        $stmt->execute([$qid]);
        $q = $stmt->fetch();
        $isCorrect = $q && $q['correct_answer'] === $answer;

        // Upsert answer
        $stmt = $db->prepare("INSERT INTO cbt_answers (attempt_id, question_id, selected_answer, is_correct, time_spent_seconds) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE selected_answer=?, is_correct=?, time_spent_seconds=?");
        $stmt->execute([$aid, $qid, $answer, $isCorrect ? 1 : 0, $time_spent, $answer, $isCorrect ? 1 : 0, $time_spent]);

        echo json_encode(['saved' => true, 'correct' => $isCorrect]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle exam submission (finish)
if (isset($_POST['submit_exam'])) {
    $attempt_id = (int)($_POST['attempt_id'] ?? 0);
    $stmt = $db->prepare("SELECT id FROM cbt_attempts WHERE id = ? AND student_id = ? AND status = 'in_progress'");
    $stmt->execute([$attempt_id, $studentId]);
    if (!$stmt->fetch()) {
        $error = 'Invalid attempt.';
    } else {
        // Calculate score
        $stmt = $db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct,
                   SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as wrong,
                   SUM(CASE WHEN selected_answer IS NULL OR selected_answer = '' THEN 1 ELSE 0 END) as unanswered
            FROM cbt_answers WHERE attempt_id = ?
        ");
        $stmt->execute([$attempt_id]);
        $stats = $stmt->fetch();

        $total = (int)$stats['total'];
        $correct = (int)$stats['correct'];
        $wrong = (int)$stats['wrong'];
        $unanswered = (int)$stats['unanswered'];
        $score = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

        // Get exam data for performance
        $stmt = $db->prepare("
            SELECT e.title, s.name as subject_name
            FROM cbt_exams e
            JOIN cbt_subjects s ON e.subject_id = s.id
            WHERE e.id = (SELECT exam_id FROM cbt_attempts WHERE id = ?)
        ");
        $stmt->execute([$attempt_id]);
        $examData = $stmt->fetch();

        $performanceData = json_encode([
            'exam_title' => $examData['title'],
            'subject' => $examData['subject_name'],
            'score' => $score,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'total' => $total,
        ]);

        $stmt = $db->prepare("UPDATE cbt_attempts SET score=?, total_questions=?, correct_count=?, wrong_count=?, unanswer_count=?, status='completed', completed_at=NOW(), time_spent_seconds=?, performance_data=? WHERE id=?");
        $stmt->execute([$score, $total, $correct, $wrong, $unanswered, (int)($_POST['time_spent_seconds'] ?? 0), $performanceData, $attempt_id]);

        logActivity($_SESSION['user_id'], 'complete_cbt_exam', 'cbt_attempts', $attempt_id);
        redirect('/student/cbt/results.php?attempt_id=' . $attempt_id);
    }
}

// Start a new attempt
if ($exam_id && !$attempt_id) {
    // Check if there's an existing in-progress attempt
    $stmt = $db->prepare("SELECT id FROM cbt_attempts WHERE exam_id = ? AND student_id = ? AND status = 'in_progress'");
    $stmt->execute([$exam_id, $studentId]);
    $existing = $stmt->fetch();
    if ($existing) {
        redirect('/student/cbt/exam.php?attempt_id=' . $existing['id']);
    }

    // Check exam exists and is published
    $stmt = $db->prepare("SELECT id, total_questions FROM cbt_exams WHERE id = ? AND is_published = 1");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    if (!$exam) {
        $error = 'Exam not found or not available.';
    } else {
        // Create attempt
        $stmt = $db->prepare("INSERT INTO cbt_attempts (exam_id, student_id, total_questions, status) VALUES (?,?,?,'in_progress')");
        $stmt->execute([$exam_id, $studentId, $exam['total_questions']]);
        $attempt_id = (int)$db->lastInsertId();
        logActivity($_SESSION['user_id'], 'start_cbt_exam', 'cbt_attempts', $attempt_id);

        // Pre-populate answer rows
        $questions = $db->prepare("SELECT q.id FROM cbt_exam_questions eq JOIN cbt_questions q ON eq.question_id = q.id WHERE eq.exam_id = ? ORDER BY eq.question_order");
        $questions->execute([$exam_id]);
        $aStmt = $db->prepare("INSERT INTO cbt_answers (attempt_id, question_id) VALUES (?,?)");
        foreach ($questions->fetchAll() as $q) {
            $aStmt->execute([$attempt_id, (int)$q['id']]);
        }
    }
}

// Load attempt data
$attempt = null;
$exam = null;
$questions = [];

if ($attempt_id) {
    $stmt = $db->prepare("
        SELECT ca.*, ce.title as exam_title, ce.duration_minutes, ce.total_questions, ce.pass_score, ce.instructions,
               s.name as subject_name, s.code as subject_code
        FROM cbt_attempts ca
        JOIN cbt_exams ce ON ca.exam_id = ce.id
        JOIN cbt_subjects s ON ce.subject_id = s.id
        WHERE ca.id = ? AND ca.student_id = ?
    ");
    $stmt->execute([$attempt_id, $studentId]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        $error = 'Attempt not found.';
    } elseif ($attempt['status'] === 'completed') {
        redirect('/student/cbt/results.php?attempt_id=' . $attempt_id);
    } else {
        // Load questions with answers
        $stmt = $db->prepare("
            SELECT q.*, a.selected_answer, a.is_correct, a.time_spent_seconds,
                   eq.question_order
            FROM cbt_exam_questions eq
            JOIN cbt_questions q ON eq.question_id = q.id
            LEFT JOIN cbt_answers a ON a.question_id = q.id AND a.attempt_id = ?
            WHERE eq.exam_id = ?
            ORDER BY eq.question_order
        ");
        $stmt->execute([$attempt_id, $attempt['exam_id']]);
        $questions = $stmt->fetchAll();

        $exam = $db->prepare("SELECT * FROM cbt_exams WHERE id = ?");
        $exam->execute([$attempt['exam_id']]);
        $exam = $exam->fetch();
    }
}

if ($error) {
    require_once __DIR__ . '/../../includes/header.php';
    echo "<div class='alert alert-danger'>$error</div>";
    echo "<a href='" . BASE_URL . "/student/cbt/index.php' class='btn btn-primary'>Back to Exams</a>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$extraStyles = '<style>
.question-nav { position: sticky; top: 20px; }
.question-nav .nav-btn { width: 40px; height: 40px; margin: 3px; font-size: 13px; font-weight: 600; border-radius: 8px; }
.question-nav .nav-btn.answered { background: #16a34a; color: white; border-color: #16a34a; }
.question-nav .nav-btn.current { border: 3px solid #0B1F3A; font-weight: 800; }
.question-card { display: none; }
.question-card.active { display: block; }
.timer-danger { color: #dc2626; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>';

$extraScripts = '
<script>
let attemptId = ' . ($attempt_id ?: 0) . ';
let totalQuestions = ' . count($questions) . ';
let currentQ = 0;
let timeLeft = ' . (($attempt['duration_minutes'] ?? 30) * 60) . ';
let timeSpent = 0;
let autoSaveInterval;
let timerInterval;

function showQuestion(idx) {
    document.querySelectorAll(".question-card").forEach((el, i) => {
        el.classList.toggle("active", i === idx);
    });
    document.querySelectorAll(".nav-btn").forEach((el, i) => {
        el.classList.toggle("current", i === idx);
    });
    currentQ = idx;
    document.getElementById("prevBtn").style.display = idx === 0 ? "none" : "inline-block";
    document.getElementById("nextBtn").style.display = idx === totalQuestions - 1 ? "none" : "inline-block";
}

function saveAnswer(questionId) {
    const selected = document.querySelector(`input[name="q_${questionId}"]:checked`);
    if (!selected) return;
    fetch("", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "ajax_submit=1&attempt_id=" + attemptId + "&question_id=" + questionId + "&answer=" + selected.value + "&time_spent=" + timeSpent
    })
    .then(r => r.json())
    .then(d => {
        if (d.saved) {
            document.querySelector(`.nav-btn[data-q="${questionId}"]`).classList.add("answered");
        }
    })
    .catch(() => {});
}

function autoSave() {
    document.querySelectorAll(".question-card").forEach(card => {
        const qid = card.dataset.questionId;
        if (qid) saveAnswer(qid);
    });
}

function finishExam() {
    if (!confirm("Are you sure you want to submit your exam? This action cannot be undone.")) return;
    autoSave();
    document.getElementById("timeSpentInput").value = timeSpent;
    document.getElementById("submitExamForm").submit();
}

function updateTimer() {
    if (timeLeft <= 0) { finishExam(); return; }
    timeLeft--;
    timeSpent++;
    const m = Math.floor(timeLeft / 60);
    const s = timeLeft % 60;
    const timerEl = document.getElementById("timer");
    timerEl.textContent = String(m).padStart(2,"0") + ":" + String(s).padStart(2,"0");
    if (timeLeft < 300) timerEl.classList.add("timer-danger");
}

document.addEventListener("DOMContentLoaded", function() {
    showQuestion(0);
    timerInterval = setInterval(updateTimer, 1000);
    autoSaveInterval = setInterval(autoSave, 30000);

    document.querySelectorAll("input[type=radio]").forEach(el => {
        el.addEventListener("change", function() {
            const qid = this.name.replace("q_", "");
            saveAnswer(qid);
        });
    });

    // Keyboard navigation
    document.addEventListener("keydown", function(e) {
        if (e.key === "ArrowRight" && currentQ < totalQuestions - 1) showQuestion(currentQ + 1);
        if (e.key === "ArrowLeft" && currentQ > 0) showQuestion(currentQ - 1);
    });

    // Warn on close
    window.addEventListener("beforeunload", function(e) {
        e.preventDefault();
        e.returnValue = "";
    });
});
</script>';

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($attempt): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0"><?= sanitizeInput($attempt['exam_title']) ?></h4>
        <p class="text-muted small mb-0">
            <span class="badge bg-primary me-2"><?= sanitizeInput($attempt['subject_code']) ?></span>
            <?= count($questions) ?> Questions
        </p>
    </div>
    <div class="text-end">
        <div id="timer" class="h3 fw-bold text-primary mb-0"><?= str_pad($attempt['duration_minutes'], 2, '0', STR_PAD_LEFT) ?>:00</div>
        <small class="text-muted">Time Remaining</small>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-9">
        <?php if ($attempt['instructions']): ?>
        <div class="alert alert-info py-2 mb-3">
            <small><i class="fas fa-info-circle me-1"></i><?= sanitizeInput($attempt['instructions']) ?></small>
        </div>
        <?php endif; ?>

        <form id="submitExamForm" method="post" action="">
            <input type="hidden" name="attempt_id" value="<?= $attempt_id ?>">
            <input type="hidden" name="submit_exam" value="1">
            <input type="hidden" name="time_spent_seconds" id="timeSpentInput" value="0">

            <?php foreach ($questions as $i => $q): ?>
            <div class="card question-card mb-3" data-question-id="<?= $q['id'] ?>" data-index="<?= $i ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge bg-secondary">Question <?= $i + 1 ?> of <?= count($questions) ?></span>
                        <small class="text-muted"><?= ucfirst($q['difficulty']) ?></small>
                    </div>
                    <h5 class="fw-bold"><?= sanitizeInput($q['question_text']) ?></h5>
                    <div class="mt-3">
                        <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                        <?php $optionKey = 'option_' . strtolower($letter); ?>
                        <div class="form-check mb-2 p-3 border rounded <?= $q['selected_answer'] === $letter ? 'border-primary bg-light' : '' ?>">
                            <input class="form-check-input" type="radio" name="q_<?= $q['id'] ?>"
                                   id="q_<?= $q['id'] ?>_<?= $letter ?>" value="<?= $letter ?>"
                                   <?= $q['selected_answer'] === $letter ? 'checked' : '' ?>>
                            <label class="form-check-label w-100" for="q_<?= $q['id'] ?>_<?= $letter ?>">
                                <strong><?= $letter ?>.</strong> <?= sanitizeInput($q[$optionKey]) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-between mt-3">
                <button type="button" id="prevBtn" class="btn btn-outline-primary" onclick="showQuestion(currentQ - 1)">
                    <i class="fas fa-chevron-left me-1"></i>Previous
                </button>
                <button type="button" id="nextBtn" class="btn btn-primary" onclick="showQuestion(currentQ + 1)">
                    Next<i class="fas fa-chevron-right ms-1"></i>
                </button>
                <button type="button" class="btn btn-success" onclick="finishExam()">
                    <i class="fas fa-check-circle me-1"></i>Submit Exam
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-3">
        <div class="card question-nav">
            <div class="card-body">
                <h6 class="fw-bold mb-2">Question Navigator</h6>
                <p class="small text-muted mb-2">Click to jump to a question</p>
                <div>
                    <?php foreach ($questions as $i => $q): ?>
                    <button class="btn btn-outline-secondary nav-btn <?= $q['selected_answer'] ? 'answered' : '' ?>"
                            data-q="<?= $q['id'] ?>"
                            onclick="showQuestion(<?= $i ?>)">
                        <?= $i + 1 ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <hr>
                <div class="d-flex gap-2 small">
                    <span><span class="badge bg-success">&nbsp;</span> Answered</span>
                    <span><span class="badge bg-secondary">&nbsp;</span> Unanswered</span>
                </div>
                <button class="btn btn-success w-100 mt-3" onclick="finishExam()">
                    <i class="fas fa-check-circle me-1"></i>Submit
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
