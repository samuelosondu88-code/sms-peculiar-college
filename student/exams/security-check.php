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
                        <h5>Camera Verification</h5>
                        <p class="text-muted">Grant camera access so we can verify your setup before the exam begins.</p>

                        <p id="camStatus" class="mt-2 text-muted small">Click <strong>Start Camera</strong> to begin.</p>

                        <div style="position: relative; display: inline-block;">
                            <video id="cameraPreview" autoplay muted playsinline style="display:none;width:240px;height:180px;border-radius:8px;background:#000;object-fit:cover;"></video>
                            <div id="cameraPlaceholder" style="width:240px;height:180px;border-radius:8px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;border:2px dashed #d1d5db;">
                                <i class="fas fa-camera" style="font-size:36px;color:#9ca3af;"></i>
                                <span style="color:#9ca3af;font-size:13px;">Camera preview appears here</span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                            <button type="button" class="btn btn-info" onclick="camStart()"><i class="fas fa-camera me-1"></i>Start Camera</button>
                            <button type="button" class="btn btn-success" id="camVerifyBtn" style="display:none;" onclick="camVerify()"><i class="fas fa-check me-1"></i>Verify Camera</button>
                            <button type="button" class="btn btn-outline-secondary" id="camRetryBtn" style="display:none;" onclick="camStart()"><i class="fas fa-redo me-1"></i>Retry</button>
                            <button type="button" class="btn btn-link text-muted small" onclick="camSkip()"><i class="fas fa-forward me-1"></i>Skip camera check</button>
                        </div>

                        <div class="mt-3" id="camNextDiv" style="display:none;">
                            <button type="button" class="btn btn-primary" onclick="nextStep(3)"><i class="fas fa-arrow-right me-1"></i>Continue</button>
                        </div>
                    </div>
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
var currentStep = 1;
var totalSteps = <?= $secSettings['require_camera'] ? 4 : 3 ?>;
var fullscreenOk = false;
var cameraStream = null;

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

function stopCamera() {
    if (typeof camStop === 'function') { camStop(); }
    else if (cameraStream) {
        cameraStream.getTracks().forEach(function (t) { t.stop(); });
        cameraStream = null;
    }
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
        stopCamera();
    }
    if (fromStep < totalSteps) {
        updateSteps(fromStep + 1);
    }
}

function requestFullscreen() {
    var el = document.documentElement;
    if (el.requestFullscreen) {
        el.requestFullscreen()['catch'](function(){});
    } else if (el.webkitRequestFullscreen) {
        el.webkitRequestFullscreen();
    } else if (el.msRequestFullscreen) {
        el.msRequestFullscreen();
    } else if (el.mozRequestFullScreen) {
        el.mozRequestFullScreen();
    }
    pollFullscreen();
}

var fsPollCount = 0;

function pollFullscreen() {
    fsPollCount++;
    checkFullscreen();
    if (fullscreenOk) {
        if (currentStep === 2) nextStep(2);
    } else if (fsPollCount < 30) {
        setTimeout(pollFullscreen, 250);
    }
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
(function() {
    var h = function() { checkFullscreen(); if (fullscreenOk && currentStep === 2) { setTimeout(function() { nextStep(2); }, 200); } };
    document.addEventListener('fullscreenchange', h);
    document.addEventListener('webkitfullscreenchange', h);
    document.addEventListener('msfullscreenchange', h);
    document.addEventListener('mozfullscreenchange', h);
    if (document.documentElement) {
        document.documentElement.addEventListener('fullscreenchange', h);
        document.documentElement.addEventListener('webkitfullscreenchange', h);
        document.documentElement.addEventListener('msfullscreenchange', h);
        document.documentElement.addEventListener('mozfullscreenchange', h);
    }
})();

/* Camera verification — inline functions */
<?php if ($secSettings['require_camera']): ?>
var _camStream = null;
var _camVerified = false;

function camLog(msg) {
}

function camSetStatus(html, cls) {
    var el = document.getElementById('camStatus');
    if (!el) return;
    el.innerHTML = html;
    el.className = 'mt-2 small ' + (cls || 'text-muted');
}

function camShowPreview(stream) {
    var v = document.getElementById('cameraPreview');
    var p = document.getElementById('cameraPlaceholder');
    if (!v) return;
    v.srcObject = stream;
    v.style.display = '';
    if (p) p.style.display = 'none';
    v.play()['catch'](function(){});
    camLog('preview shown');
}

function camStop() {
    if (_camStream) {
        _camStream.getTracks().forEach(function (t) { t.stop(); });
        _camStream = null;
        camLog('stream stopped');
    }
    var v = document.getElementById('cameraPreview');
    var p = document.getElementById('cameraPlaceholder');
    if (v) v.style.display = 'none';
    if (p) p.style.display = '';
}

function camStart() {
    if (_camStream) {
        camLog('already have stream');
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        camSetStatus('<i class="fas fa-times-circle text-danger me-1"></i>Camera API not available.', 'text-danger');
        document.getElementById('camRetryBtn').style.display = '';
        camLog('getUserMedia not available');
        return;
    }
    camSetStatus('<i class="fas fa-spinner fa-spin me-1"></i>Requesting camera access...', 'text-info');
    camLog('requesting camera...');
    try {
        navigator.mediaDevices.getUserMedia({ video: { width: { ideal: 320 }, height: { ideal: 240 }, facingMode: 'user' } })
            .then(function (s) {
                _camStream = s;
                camLog('stream obtained, active=' + s.active + ' tracks=' + s.getTracks().length);
                camShowPreview(s);
                camSetStatus('Camera working. Click <strong>Verify Camera</strong>.', 'text-success');
                document.getElementById('camVerifyBtn').style.display = '';
            })
            .catch(function (err) {
                var msg = 'Camera access denied or unavailable.';
                if (err.name === 'NotAllowedError') msg = 'Permission denied. Allow camera in browser settings, then Retry.';
                else if (err.name === 'NotFoundError') msg = 'No camera found. You can Skip this step.';
                else if (err.name === 'NotReadableError') msg = 'Camera busy (another app using it). Close it and Retry.';
                camLog('error: ' + err.name + ' - ' + err.message);
                camSetStatus('<i class="fas fa-times-circle text-danger me-1"></i>' + msg, 'text-danger');
                document.getElementById('camRetryBtn').style.display = '';
            });
    } catch (e) {
        camLog('sync error: ' + e.message);
        camSetStatus('<i class="fas fa-times-circle text-danger me-1"></i>Camera error.', 'text-danger');
        document.getElementById('camRetryBtn').style.display = '';
    }
}

function camVerify() {
    if (!_camStream) {
        camSetStatus('Start the camera first, then verify.', 'text-warning');
        return;
    }
    _camVerified = true;
    try { sessionStorage.setItem('cam_verified', '1'); } catch (e) {}
    camLog('verified');
    camSetStatus('<i class="fas fa-check-circle text-success me-1"></i>Camera verified!', 'text-success');
    document.getElementById('camVerifyBtn').style.display = 'none';
    document.getElementById('camRetryBtn').style.display = 'none';
    document.getElementById('camNextDiv').style.display = '';
    camStop();
}

function camSkip() {
    _camVerified = true;
    try { sessionStorage.setItem('cam_skipped', '1'); } catch (e) {}
    camLog('skipped');
    camSetStatus('<i class="fas fa-check-circle text-muted me-1"></i>Skipped. You can continue.', 'text-muted');
    document.getElementById('camNextDiv').style.display = '';
    camStop();
}

/* Check if already verified in this session */
(function () {
    try {
        if (sessionStorage.getItem('cam_verified') === '1' || sessionStorage.getItem('cam_skipped') === '1') {
            _camVerified = true;
            camSetStatus('Already verified.', 'text-success');
            document.getElementById('camNextDiv').style.display = '';
        }
    } catch (e) {}
})();
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

window.addEventListener('beforeunload', stopCamera);


</script>

<?php $extraScripts = ''; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
