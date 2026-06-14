<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Security Check';
$db = getDB();
$userId = $_SESSION['user_id'];

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT te.*, sub.name as subject_name, c.name as class_name, c.section FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id JOIN classes c ON te.class_id = c.id WHERE te.id = ? AND te.is_published = 1");
$stmt->execute([$examId]);
$exam = $stmt->fetch();
if (!$exam) redirect('/student/exams/index.php');

$studentStmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch();
if (!$student) redirect('/student/exams/index.php');

require_once __DIR__ . '/../../includes/exam_security.php';
$secSettings = getExamSecuritySettings($db, $examId);
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.security-step { display: none; }
.security-step.active { display: block; }
.step-indicator { display: flex; justify-content: center; gap: 8px; margin-bottom: 30px; }
.step-dot { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; background: #e5e7eb; color: #6b7280; }
.step-dot.active { background: #0B1F3A; color: #fff; }
.step-dot.done { background: #059669; color: #fff; }
#cameraPreview { width: 240px; height: 180px; border-radius: 8px; background: #000; object-fit: cover; }
#faceOverlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; }
</style>

<div class="container py-4" style="max-width: 700px;">
    <div class="card">
        <div class="card-header text-center bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Pre-Exam Security Check</h5>
            <small><?= sanitizeInput($exam['title']) ?> — <?= sanitizeInput($exam['subject_name']) ?></small>
        </div>
        <div class="card-body text-center">
            <div class="step-indicator" id="stepIndicator">
                <div class="step-dot active" data-step="1">1</div>
                <div class="step-dot" data-step="2">2</div>
                <?php if ($secSettings['require_camera']): ?>
                <div class="step-dot" data-step="3">3</div>
                <?php endif; ?>
                <div class="step-dot" data-step="4">4</div>
            </div>

            <form id="securityForm" method="POST" action="take-exam.php?exam_id=<?= $examId ?>">
                <input type="hidden" name="security_verified" value="1">
                <input type="hidden" name="device_fp" id="deviceFp" value="">
                <input type="hidden" name="screen_res" id="screenRes" value="">
                <input type="hidden" name="timezone" id="tz" value="">
                <input type="hidden" name="platform" id="platform" value="">

                <div class="security-step active" data-step="1">
                    <div class="mb-3">
                        <div class="display-1 text-primary mb-3"><i class="fas fa-shield-alt"></i></div>
                        <h5>Before You Start</h5>
                        <p class="text-muted">This exam is monitored for integrity. The following will be enforced:</p>
                        <div class="text-start small mx-auto" style="max-width: 400px;">
                            <div class="mb-2"><i class="fas fa-expand text-success me-2"></i>Full-screen mode required</div>
                            <div class="mb-2"><i class="fas fa-ban text-danger me-2"></i>No tab switching allowed</div>
                            <div class="mb-2"><i class="fas fa-ban text-danger me-2"></i>No right-click or copy/paste</div>
                            <div class="mb-2"><i class="fas fa-video text-info me-2"></i>Keyboard shortcuts disabled</div>
                            <?php if ($secSettings['require_camera']): ?>
                            <div class="mb-2"><i class="fas fa-camera text-warning me-2"></i>Camera monitoring active</div>
                            <?php endif; ?>
                            <div class="mb-2"><i class="fas fa-clock text-warning me-2"></i>Auto-save every 5 seconds</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="nextStep(1)">I Understand, Continue</button>
                </div>

                <div class="security-step" data-step="2">
                    <div class="mb-3">
                        <div class="display-1 text-warning mb-3"><i class="fas fa-expand"></i></div>
                        <h5>Enter Full-Screen Mode</h5>
                        <p class="text-muted">Click the button below to enter full-screen mode. You must remain in full-screen throughout the exam.</p>
                        <p id="fsStatus" class="text-danger small"><i class="fas fa-times me-1"></i>Not in full-screen mode</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-lg btn-warning" onclick="requestFullscreen()"><i class="fas fa-expand me-2"></i>Enter Full-Screen</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="nextStep(2)"><i class="fas fa-check me-1"></i>Continue</button>
                </div>

                <?php if ($secSettings['require_camera']): ?>
                <div class="security-step" data-step="3">
                    <div class="mb-3">
                        <div class="display-1 text-info mb-3"><i class="fas fa-camera"></i></div>
                        <h5>Camera Check</h5>
                        <p class="text-muted">Grant camera access to verify your identity. Your webcam will be monitored throughout the exam.</p>
                        <div style="position: relative; display: inline-block;">
                            <video id="cameraPreview" autoplay muted playsinline></video>
                        </div>
                        <p id="camStatus" class="mt-2 text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Waiting for camera...</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="nextStep(3)" disabled id="camNextBtn"><i class="fas fa-check me-1"></i>Continue</button>
                </div>
                <?php endif; ?>

                <div class="security-step" data-step="<?= $secSettings['require_camera'] ? '4' : '3' ?>">
                    <div class="mb-3">
                        <div class="display-1 text-success mb-3"><i class="fas fa-check-circle"></i></div>
                        <h5>Ready to Begin</h5>
                        <p class="text-muted">All checks passed. You can now start the exam.</p>
                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle me-1"></i>
                            Exam: <strong><?= sanitizeInput($exam['title']) ?></strong><br>
                            Duration: <strong><?= $exam['duration_minutes'] ?> minutes</strong><br>
                            Questions: <strong><?= $exam['total_marks'] ?> total marks</strong><br>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-lg btn-success" id="startExamBtn"><i class="fas fa-play me-2"></i>Start Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
const totalSteps = <?= $secSettings['require_camera'] ? 4 : 3 ?>;
let fullscreenOk = false;
let cameraOk = !<?= $secSettings['require_camera'] ? 'true' : 'false' ?>;
let cameraStream = null;

function updateSteps(step) {
    document.querySelectorAll('.step-dot').forEach(function (d) {
        var s = parseInt(d.dataset.step);
        d.classList.toggle('active', s === step);
        d.classList.toggle('done', s < step);
    });
    document.querySelectorAll('.security-step').forEach(function (s) {
        s.classList.toggle('active', parseInt(s.dataset.step) === step);
    });
    currentStep = step;
}

function nextStep(fromStep) {
    if (fromStep === 1) {
        /* ok */
    } else if (fromStep === 2) {
        if (!fullscreenOk) {
            alert('Please enter full-screen mode first.');
            return;
        }
    } else if (fromStep === 3 && <?= $secSettings['require_camera'] ? 'true' : 'false' ?>) {
        if (!cameraOk) {
            alert('Camera check in progress. Please wait...');
            return;
        }
    }
    if (fromStep < totalSteps) {
        updateSteps(fromStep + 1);
    }
}

function requestFullscreen() {
    var el = document.documentElement;
    var fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen || el.mozRequestFullScreen;
    if (fn) fn.call(el);
    setTimeout(checkFullscreen, 500);
}

function checkFullscreen() {
    var fs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || document.mozFullScreenElement;
    fullscreenOk = !!fs;
    var el = document.getElementById('fsStatus');
    if (el) {
        el.className = fullscreenOk ? 'text-success small mt-2' : 'text-danger small mt-2';
        el.innerHTML = fullscreenOk ? '<i class="fas fa-check me-1"></i>Full-screen mode active' : '<i class="fas fa-times me-1"></i>Not in full-screen mode';
    }
}
document.addEventListener('fullscreenchange', checkFullscreen);
document.addEventListener('webkitfullscreenchange', checkFullscreen);
document.addEventListener('msfullscreenchange', checkFullscreen);

/* Camera setup */
<?php if ($secSettings['require_camera']): ?>
if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240, facingMode: 'user' } })
        .then(function (stream) {
            cameraStream = stream;
            var video = document.getElementById('cameraPreview');
            if (video) {
                video.srcObject = stream;
                video.play();
                cameraOk = true;
                document.getElementById('camStatus').innerHTML = '<i class="fas fa-check text-success me-1"></i>Camera working';
                document.getElementById('camNextBtn').disabled = false;
            }
        })
        .catch(function (err) {
            document.getElementById('camStatus').innerHTML = '<i class="fas fa-times text-danger me-1"></i>Camera error: ' + err.message;
            document.getElementById('camNextBtn').disabled = false;
            cameraOk = true;
        });
}
<?php endif; ?>

/* Device fingerprinting */
(function () {
    var fp = [];
    fp.push(navigator.userAgent);
    fp.push(screen.width + 'x' + screen.height);
    fp.push(screen.colorDepth);
    fp.push(navigator.language);
    fp.push(new Date().getTimezoneOffset());
    fp.push(navigator.hardwareConcurrency || '');
    fp.push(navigator.deviceMemory || '');
    fp.push(navigator.platform || '');
    var hash = 0;
    var s = fp.join('|||');
    for (var i = 0; i < s.length; i++) {
        var ch = s.charCodeAt(i);
        hash = ((hash << 5) - hash) + ch;
        hash |= 0;
    }
    document.getElementById('deviceFp').value = Math.abs(hash).toString(16);
    document.getElementById('screenRes').value = screen.width + 'x' + screen.height;
    document.getElementById('tz').value = new Date().getTimezoneOffset();
    document.getElementById('platform').value = navigator.platform || '';
})();

window.addEventListener('beforeunload', function (e) {
    if (cameraStream) cameraStream.getTracks().forEach(function (t) { t.stop(); });
});
</script>

<?php $extraScripts = '<script src="' . BASE_URL . '/includes/exam_security.js"></script>'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
