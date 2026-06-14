<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/exam_security.php';
$pageTitle = 'Take Exam';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT te.*, sub.name as subject_name, c.name as class_name, c.section FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id JOIN classes c ON te.class_id = c.id WHERE te.id = ? AND te.is_published = 1");
$stmt->execute([$examId]);
$exam = $stmt->fetch();
if (!$exam) redirect('/student/exams/index.php');

$studentStmt = $db->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch();
if (!$student) redirect('/student/exams/index.php');

$secSettings = getExamSecuritySettings($db, $examId);

$attemptStmt = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1");
$attemptStmt->execute([$examId, $userId]);
$attempt = $attemptStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['save_answer','log_event','auto_submit','capture_evidence'])) {
        if (!$attempt || $attempt['status'] !== 'in_progress') { echo 'ERR'; exit; }
        $attemptId = $attempt['id'];
    }

    if ($action === 'save_answer') {
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
                if ($qData['question_type'] === 'mcq' && strtoupper($answer) === strtoupper($qData['correct_answer'])) { $isCorrect = true; $autoScore = $qData['marks']; }
                elseif ($qData['question_type'] === 'true_false' && strtoupper($answer) === strtoupper($qData['correct_answer'])) { $isCorrect = true; $autoScore = $qData['marks']; }
                elseif ($qData['question_type'] === 'fill_blank' && strtolower(trim($answer)) === strtolower(trim($qData['correct_answer']))) { $isCorrect = true; $autoScore = $qData['marks']; }
            }
            $db->prepare("UPDATE exam_responses SET is_correct = ?, auto_score = ?, total_score = ?, manual_score = 0 WHERE attempt_id = ? AND question_id = ?")->execute([$isCorrect, $autoScore, $autoScore, $attemptId, $qid]);
            $autoTotal = $db->prepare("SELECT COALESCE(SUM(auto_score),0) FROM exam_responses WHERE attempt_id = ?");
            $autoTotal->execute([$attemptId]);
            $db->prepare("UPDATE exam_attempts SET auto_score = ?, total_score = auto_score + manual_score WHERE id = ?")->execute([$autoTotal->fetchColumn(), $attemptId]);
            updateLastActivity($db, $attemptId);
        }
        echo 'OK'; exit;
    }

    if ($action === 'log_event') {
        $eventType = sanitizeInput($_POST['event_type'] ?? '');
        $eventData = $_POST['event_data'] ?? null;
        $eventDataArr = $eventData ? json_decode($eventData, true) : null;
        logExamActivity($db, $attemptId, $eventType, $eventDataArr);
        if (in_array($eventType, ['tab_switch','fullscreen_exit','camera_violation','multiple_faces','face_absent','face_obstructed','copy_attempt','right_click','keyboard_shortcut'])) {
            logViolation($db, $attemptId, $eventType);
            if ($eventType === 'fullscreen_exit' || $eventType === 'tab_switch') {
                $secSettings = getExamSecuritySettings($db, $examId);
            }
        }
        if ($eventType === 'heartbeat') updateLastActivity($db, $attemptId);
        echo 'OK'; exit;
    }

    if ($action === 'auto_submit') {
        $reason = sanitizeInput($_POST['reason'] ?? 'auto_submit');
        autoSubmitExam($db, $attemptId, $reason);
        echo 'OK'; exit;
    }

    if ($action === 'capture_evidence') {
        $vType = sanitizeInput($_POST['violation_type'] ?? '');
        $faceCount = (int)($_POST['face_count'] ?? 0);
        $imageData = $_POST['image_data'] ?? '';
        if ($imageData) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $imageData = base64_decode($imageData);
        }
        $stmt = $db->prepare("INSERT INTO exam_proctoring_evidence (attempt_id, violation_type, face_count, image_data) VALUES (?, ?, ?, ?)");
        $stmt->execute([$attemptId, $vType, $faceCount, $imageData ?? null]);
        logViolation($db, $attemptId, $vType, ['face_count' => $faceCount]);
        echo 'OK'; exit;
    }

    if (isset($_POST['security_verified'])) {
        if ($attempt && $attempt['status'] !== 'in_progress') {
            redirect('/student/exams/results.php?exam_id=' . $examId);
        }
        $fpHash = sanitizeInput($_POST['device_fp'] ?? '');
        $screenRes = sanitizeInput($_POST['screen_res'] ?? '');
        $tz = (int)($_POST['timezone'] ?? 0);
        $platform = sanitizeInput($_POST['platform'] ?? '');

        if ($secSettings['restrict_device'] && $fpHash) {
            if (checkDeviceRestriction($db, $examId, $fpHash)) {
                redirect('/student/exams/index.php?error=device_in_use');
            }
        }

        if (!$attempt) {
            $db->prepare("INSERT INTO exam_attempts (exam_id, student_id, started_at, status, ip_address, device_fingerprint) VALUES (?, ?, NOW(), 'in_progress', ?, ?)")->execute([$examId, $userId, $_SERVER['REMOTE_ADDR'] ?? '', $fpHash]);
            $attemptId = $db->lastInsertId();
            $attempt = ['id' => $attemptId, 'status' => 'in_progress', 'started_at' => date('Y-m-d H:i:s')];
            $db->prepare("UPDATE teacher_exams SET status = 'in_progress' WHERE id = ? AND status = 'published'")->execute([$examId]);

            registerDeviceFingerprint($db, $attemptId, [
                'hash' => $fpHash,
                'resolution' => $screenRes,
                'timezone' => $tz,
                'platform' => $platform,
                'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
                'concurrency' => (int)($_POST['hardware_concurrency'] ?? 0),
                'memory' => (float)($_POST['device_memory'] ?? 0)
            ]);
            logExamActivity($db, $attemptId, 'login', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            logExamActivity($db, $attemptId, 'device_fingerprint', ['hash' => $fpHash]);
        }

        $questionStmt = $db->prepare("SELECT teq.id as teq_id, teq.question_order, eq.* FROM teacher_exam_questions teq JOIN exam_questions eq ON teq.question_id = eq.id WHERE teq.exam_id = ? ORDER BY teq.question_order");
        $questionStmt->execute([$examId]);
        $questions = $questionStmt->fetchAll();
        $totalQuestions = count($questions);
    }

    if (isset($_POST['submit_exam'])) {
        $db->prepare("UPDATE exam_attempts SET status = 'submitted', submitted_at = NOW(), submit_reason = 'student_submitted' WHERE id = ?")->execute([$attemptId]);
        computeIntegrityScore($db, $attemptId);
        redirect('/student/exams/results.php?exam_id=' . $examId);
    }
}

$attemptStmt = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 1");
$attemptStmt->execute([$examId, $userId]);
$attempt = $attemptStmt->fetch();

if (!isset($_POST['security_verified'])) {
    if (!$attempt || $attempt['status'] === '') {
        redirect('security-check.php?exam_id=' . $examId);
    }
    if ($attempt['status'] !== 'in_progress') {
        redirect('/student/exams/results.php?exam_id=' . $examId);
    }
    $attemptId = $attempt['id'];
}

$questionStmt = $db->prepare("SELECT teq.id as teq_id, teq.question_order, eq.* FROM teacher_exam_questions teq JOIN exam_questions eq ON teq.question_id = eq.id WHERE teq.exam_id = ? ORDER BY teq.question_order");
$questionStmt->execute([$examId]);
$questions = $questionStmt->fetchAll();
$totalQuestions = count($questions);

if ($totalQuestions === 0) {
    $db->prepare("UPDATE exam_attempts SET status = 'submitted', submitted_at = NOW() WHERE id = ?")->execute([$attemptId]);
    redirect('/student/exams/index.php');
}

if ($secSettings['shuffle_questions'] && $totalQuestions > 1) {
    $seed = crc32($examId . '_' . $userId);
    $keys = array_keys($questions);
    mt_srand($seed);
    shuffle($keys);
    $shuffled = [];
    foreach ($keys as $k) $shuffled[] = $questions[$k];
    $questions = $shuffled;
    mt_srand();
}

$respStmt = $db->prepare("SELECT question_id, response FROM exam_responses WHERE attempt_id = ?");
$respStmt->execute([$attemptId]);
$responses = [];
foreach ($respStmt->fetchAll() as $r) { $responses[$r['question_id']] = $r['response']; }

$qTypes = ['mcq'=>'Multiple Choice','true_false'=>'True/False','fill_blank'=>'Fill in the Blank','short_answer'=>'Short Answer','essay'=>'Essay'];
$optionLabels = ['A','B','C','D'];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.question-nav { position: sticky; top: 80px; }
.question-btn { width: 40px; height: 40px; margin: 3px; padding: 0; font-size: 13px; }
.question-btn.answered { background-color: #059669; color: #fff; border-color: #059669; }
.question-btn.current { border: 2px solid #D4AF37; font-weight: bold; }
.question-btn.flagged { border-color: #d97706; position: relative; }
.question-btn.flagged::after { content: '!'; position: absolute; top: -5px; right: -5px; background: #d97706; color: #fff; border-radius: 50%; width: 16px; height: 16px; font-size: 10px; line-height: 16px; }
#timer { font-size: 1.4rem; font-weight: bold; color: #dc2626; min-width: 70px; display: inline-block; }
#timer.warning { color: #d97706; }
#timer.danger { color: #dc2626; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
.question-card { display: none; }
.question-card.active { display: block; }
body {-webkit-user-select: none;-moz-user-select:none;-ms-user-select:none;user-select:none;}
.exam-warning-overlay { position: fixed; top: 0; left: 0; right: 0; z-index: 99999; padding: 12px; }
.exam-warning-box { max-width: 600px; margin: 0 auto; padding: 16px 20px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
.exam-warning-danger { background: #fef2f2; border-left: 4px solid #dc2626; color: #991b1b; }
.exam-warning-warning { background: #fffbeb; border-left: 4px solid #d97706; color: #92400e; }
.exam-warning-info { background: #eff6ff; border-left: 4px solid #2563eb; color: #1e40af; }
.exam-warning-icon { font-size: 24px; flex-shrink: 0; }
.exam-warning-msg { flex: 1; font-size: 14px; font-weight: 500; }
.exam-warning-close { cursor: pointer; font-size: 20px; opacity: 0.5; padding: 0 4px; }
.exam-warning-close:hover { opacity: 1; }
.connection-badge { position: fixed; bottom: 16px; right: 16px; z-index: 9999; font-size: 12px; padding: 6px 12px; border-radius: 20px; }
.option-shuffled-label { display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; border-radius: 50%; font-weight: bold; font-size: 13px; margin-right: 8px; background: #e5e7eb; }
.option-shuffled-label.selected { background: #0B1F3A; color: #fff; }
</style>

<div class="connection-badge bg-success text-white" id="connBadge"><i class="fas fa-wifi me-1"></i>Online</div>

<div class="row">
    <div class="col-12 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-0"><?= sanitizeInput($exam['title']) ?></h4>
                <small class="text-muted"><?= sanitizeInput($exam['subject_name']) ?> - <?= sanitizeInput($exam['class_name'] . ' ' . $exam['section']) ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-muted"><i class="fas fa-shield-alt me-1"></i>Secured</span>
                <span><i class="fas fa-clock me-1"></i><span id="timer">--:--</span></span>
                <button type="button" class="btn btn-danger" onclick="confirmSubmit()"><i class="fas fa-check-circle me-1"></i>Submit</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-9">
        <form id="examForm" method="POST">
            <?php foreach ($questions as $i => $q):
                $optOrder = ['option_a','option_b','option_c','option_d'];
                if ($secSettings['shuffle_options'] && $q['question_type'] === 'mcq') {
                    $seed2 = crc32($examId . '_q' . $q['id'] . '_' . $userId);
                    mt_srand($seed2);
                    shuffle($optOrder);
                    mt_srand();
                }
            ?>
            <div class="question-card card mb-3 <?= $i === 0 ? 'active' : '' ?>" data-qid="<?= $q['id'] ?>" data-index="<?= $i ?>">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Question <?= $i + 1 ?> of <?= $totalQuestions ?></strong> <span class="badge bg-info"><?= $qTypes[$q['question_type']] ?? $q['question_type'] ?></span></span>
                    <span class="badge bg-secondary"><?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></span>
                </div>
                <div class="card-body">
                    <p class="fw-bold fs-5 mb-4"><?= sanitizeInput($q['question_text']) ?></p>

                    <?php if ($q['question_type'] === 'mcq'): $optIdx = 0; ?>
                    <?php foreach ($optOrder as $optKey): $optLabel = $optionLabels[$optIdx]; if (!empty($q[$optKey])): ?>
                    <div class="form-check mb-2">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="<?= $optLabel ?>" class="form-check-input" id="q<?= $q['id'] ?>_<?= $optLabel ?>" data-save-qid="<?= $q['id'] ?>" <?= (($responses[$q['id']] ?? '') === $optLabel) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="q<?= $q['id'] ?>_<?= $optLabel ?>"><span class="option-shuffled-label <?= (($responses[$q['id']] ?? '') === $optLabel) ? 'selected' : '' ?>"><?= $optLabel ?></span> <?= sanitizeInput($q[$optKey]) ?></label>
                    </div>
                    <?php endif; $optIdx++; endforeach; ?>

                    <?php elseif ($q['question_type'] === 'true_false'): ?>
                    <div class="form-check mb-2">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="True" class="form-check-input" id="q<?= $q['id'] ?>_true" data-save-qid="<?= $q['id'] ?>" <?= (($responses[$q['id']] ?? '') === 'True') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="q<?= $q['id'] ?>_true">True</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="False" class="form-check-input" id="q<?= $q['id'] ?>_false" data-save-qid="<?= $q['id'] ?>" <?= (($responses[$q['id']] ?? '') === 'False') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="q<?= $q['id'] ?>_false">False</label>
                    </div>

                    <?php elseif ($q['question_type'] === 'fill_blank'): ?>
                    <div class="mb-3">
                        <input type="text" name="q_<?= $q['id'] ?>" class="form-control form-control-lg" placeholder="Type your answer..." value="<?= sanitizeInput($responses[$q['id']] ?? '') ?>" data-save-qid="<?= $q['id'] ?>">
                    </div>

                    <?php elseif ($q['question_type'] === 'short_answer'): ?>
                    <div class="mb-3">
                        <textarea name="q_<?= $q['id'] ?>" class="form-control" rows="3" placeholder="Type your answer..." data-save-qid="<?= $q['id'] ?>"><?= sanitizeInput($responses[$q['id']] ?? '') ?></textarea>
                    </div>

                    <?php elseif ($q['question_type'] === 'essay'): ?>
                    <div class="mb-3">
                        <textarea name="q_<?= $q['id'] ?>" class="form-control" rows="6" placeholder="Write your essay answer..." data-save-qid="<?= $q['id'] ?>"><?= sanitizeInput($responses[$q['id']] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" onclick="navigateQ(-1)" <?= $i === 0 ? 'disabled' : '' ?>><i class="fas fa-chevron-left me-1"></i>Previous</button>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="toggleFlag(<?= $i ?>)" title="Flag for review"><i class="fas fa-flag"></i></button>
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
                <div class="d-flex justify-content-center gap-2 small flex-wrap">
                    <span><span class="badge bg-success">●</span> Answered</span>
                    <span><span class="badge bg-secondary">●</span> Unanswered</span>
                    <span><span class="badge bg-warning text-dark">●</span> Flagged</span>
                </div>
                <hr>
                <button type="button" class="btn btn-danger w-100" onclick="confirmSubmit()"><i class="fas fa-check-circle me-1"></i>Submit Exam</button>
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
                <p id="unansweredWarning" class="text-danger mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Unanswered questions will be marked as incorrect.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Review</button>
                <form method="POST" id="submitForm">
                    <button type="submit" name="submit_exam" class="btn btn-danger"><i class="fas fa-check-circle me-1"></i>Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $extraScripts = '<script src="' . BASE_URL . '/includes/exam_security.js"></script>';
$extraScripts .= <<<SCRIPT
<script>
var answeredSet = new Set();
var flaggedSet = new Set();

function initAnswered() {
    document.querySelectorAll('.question-btn').forEach(function (btn) {
        var qid = btn.dataset.qid;
        var inputs = document.querySelectorAll('[name="q_' + qid + '"]');
        var hasValue = false;
        inputs.forEach(function (inp) {
            if (inp.type === 'radio' ? inp.checked : inp.value.trim() !== '') hasValue = true;
        });
        if (hasValue) { btn.classList.add('answered'); answeredSet.add(parseInt(qid)); }
    });
    updateAnsweredCount();
}

function goToQ(idx) {
    document.querySelectorAll('.question-card').forEach(function (c) { c.classList.remove('active'); });
    document.querySelectorAll('.question-btn').forEach(function (b) { b.classList.remove('current'); });
    var card = document.querySelector('.question-card[data-index="' + idx + '"]');
    if (card) card.classList.add('active');
    var btn = document.querySelector('.question-btn[data-index="' + idx + '"]');
    if (btn) btn.classList.add('current');
    currentQuestion = idx;
    if (ExamSecurity) ExamSecurity.goToQuestion(idx);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function navigateQ(dir) {
    var next = currentQuestion + dir;
    if (next >= 0 && next < totalQuestions) goToQ(next);
}

function saveAnswer(qid) {
    var inputs = document.querySelectorAll('[name="q_' + qid + '"]');
    var value = '';
    inputs.forEach(function (inp) {
        if (inp.type === 'radio' && inp.checked) value = inp.value;
        else if (inp.type !== 'radio') value = inp.value;
    });
    var btn = document.querySelector('.question-btn[data-qid="' + qid + '"]');
    if (value.trim() !== '') {
        btn.classList.add('answered');
        answeredSet.add(qid);
    } else {
        btn.classList.remove('answered');
        answeredSet.delete(qid);
    }
    updateAnsweredCount();
    ExamSecurity.saveAnswer(qid, value);
}

function updateAnsweredCount() {
    document.getElementById('answeredCount').textContent = answeredSet.size;
}

function toggleFlag(idx) {
    var btn = document.querySelector('.question-btn[data-index="' + idx + '"]');
    if (flaggedSet.has(idx)) {
        flaggedSet.delete(idx);
        btn.classList.remove('flagged');
    } else {
        flaggedSet.add(idx);
        btn.classList.add('flagged');
    }
}

function confirmSubmit() {
    document.getElementById('answeredCount').textContent = answeredSet.size;
    var unanswered = totalQuestions - answeredSet.size;
    document.getElementById('unansweredWarning').innerHTML = unanswered > 0
        ? '<i class="fas fa-exclamation-triangle me-1"></i>' + unanswered + ' question(s) unanswered. They will be marked incorrect.'
        : '<i class="fas fa-check text-success me-1"></i>All questions answered.';
    new bootstrap.Modal(document.getElementById('confirmModal')).show();
}

/* Keyboard navigation */
document.addEventListener('keydown', function (e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'ArrowRight') { e.preventDefault(); navigateQ(1); }
    if (e.key === 'ArrowLeft') { e.preventDefault(); navigateQ(-1); }
});

/* Init ExamSecurity */
var startTime = '{$attempt['started_at']}';
document.addEventListener('DOMContentLoaded', function () {
    initAnswered();
    goToQ(0);

    ExamSecurity.init({
        examId: {$examId},
        attemptId: {$attemptId},
        saveAnswerUrl: '',
        logEventUrl: '',
        submitUrl: '',
        requireFullscreen: true,
        requireCamera: {$secSettings['require_camera']},
        maxTabSwitches: {$secSettings['max_tab_switches']},
        maxFullscreenExits: {$secSettings['max_fullscreen_exits']},
        maxCameraErrors: {$secSettings['max_camera_errors']},
        maxFaceViolations: {$secSettings['max_face_violations']},
        durationMinutes: {$exam['duration_minutes']},
        startTime: new Date(startTime.replace(' ', 'T') + 'Z'),
        inactivityWarningAfter: 240,
    });

    ExamSecurity.on('autosubmit', function (reason) {
        if (reason === 'timer_expired') {
            document.querySelector('[name="submit_exam"]').click();
        }
    });
});

/* Connection monitoring */
window.addEventListener('online', function () {
    document.getElementById('connBadge').className = 'connection-badge bg-success text-white';
    document.getElementById('connBadge').innerHTML = '<i class="fas fa-wifi me-1"></i>Online';
});
window.addEventListener('offline', function () {
    document.getElementById('connBadge').className = 'connection-badge bg-danger text-white';
    document.getElementById('connBadge').innerHTML = '<i class="fas fa-wifi-slash me-1"></i>Offline';
});

window.addEventListener('beforeunload', function (e) {
    if (!ExamSecurity._state.autoSubmitted) {
        e.preventDefault();
        e.returnValue = 'You have an exam in progress. Are you sure you want to leave?';
    }
});
</script>
SCRIPT;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
