<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/exam_security.php';
$pageTitle = 'Exam Security Settings';
$db = getDB();
$userId = $_SESSION['user_id'];

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM teacher_exams WHERE id = ? AND teacher_id = ?");
$stmt->execute([$examId, $userId]);
$exam = $stmt->fetch();
if (!$exam) redirect('/teacher/exams/index.php');

$settings = getExamSecuritySettings($db, $examId);
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security'])) {
    $data = [
        'require_fullscreen' => isset($_POST['require_fullscreen']) ? 1 : 0,
        'require_camera' => isset($_POST['require_camera']) ? 1 : 0,
        'max_tab_switches' => (int)($_POST['max_tab_switches'] ?? 3),
        'max_fullscreen_exits' => (int)($_POST['max_fullscreen_exits'] ?? 3),
        'max_camera_errors' => (int)($_POST['max_camera_errors'] ?? 5),
        'max_face_violations' => (int)($_POST['max_face_violations'] ?? 5),
        'inactivity_timeout_minutes' => (int)($_POST['inactivity_timeout_minutes'] ?? 5),
        'auto_submit_on_violation' => isset($_POST['auto_submit_on_violation']) ? 1 : 0,
        'restrict_device' => isset($_POST['restrict_device']) ? 1 : 0,
        'allowed_ips' => $_POST['allowed_ips'] ?? '',
        'shuffle_questions' => isset($_POST['shuffle_questions']) ? 1 : 0,
        'shuffle_options' => isset($_POST['shuffle_options']) ? 1 : 0,
    ];
    saveExamSecuritySettings($db, $examId, $data);
    $settings = array_merge($settings, $data);
    $msg = 'Security settings saved.';
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($exam['title']) ?></p>
    </div>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-lock me-2"></i>Security Enforcement</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="require_fullscreen" class="form-check-input" id="chkFS" value="1" <?= $settings['require_fullscreen'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="chkFS">Require Full-Screen</label>
                                <small class="d-block text-muted">Student must remain in full-screen mode</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="require_camera" class="form-check-input" id="chkCam" value="1" <?= $settings['require_camera'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="chkCam">Require Camera</label>
                                <small class="d-block text-muted">Webcam monitoring for proctoring</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="auto_submit_on_violation" class="form-check-input" id="chkAuto" value="1" <?= $settings['auto_submit_on_violation'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="chkAuto">Auto-Submit on Violation</label>
                                <small class="d-block text-muted">Auto-submit when violation limit reached</small>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold">Violation Thresholds</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Max Tab Switches</label>
                            <input type="number" name="max_tab_switches" class="form-control" min="1" max="20" value="<?= $settings['max_tab_switches'] ?>">
                            <small class="text-muted">0 = unlimited</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Full-Screen Exits</label>
                            <input type="number" name="max_fullscreen_exits" class="form-control" min="1" max="20" value="<?= $settings['max_fullscreen_exits'] ?>">
                            <small class="text-muted">0 = unlimited</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Camera Errors</label>
                            <input type="number" name="max_camera_errors" class="form-control" min="1" max="20" value="<?= $settings['max_camera_errors'] ?>">
                            <small class="text-muted">Denied/unavailable</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Face Violations</label>
                            <input type="number" name="max_face_violations" class="form-control" min="1" max="20" value="<?= $settings['max_face_violations'] ?>">
                            <small class="text-muted">No face / multiple faces</small>
                        </div>
                    </div>

                    <hr>
                    <h6 class="fw-bold">Inactivity</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Inactivity Timeout (minutes)</label>
                            <input type="number" name="inactivity_timeout_minutes" class="form-control" min="1" max="30" value="<?= $settings['inactivity_timeout_minutes'] ?>">
                            <small class="text-muted">Auto-submit after this period of no activity</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-random me-2"></i>Question & Answer Settings</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="shuffle_questions" class="form-check-input" id="chkSQ" value="1" <?= $settings['shuffle_questions'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="chkSQ">Shuffle question order</label>
                                <small class="d-block text-muted">Unique question sequence per student</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="shuffle_options" class="form-check-input" id="chkSO" value="1" <?= $settings['shuffle_options'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="chkSO">Shuffle answer options</label>
                                <small class="d-block text-muted">Randomize MCQ option order</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-desktop me-2"></i>Device Restrictions</div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="restrict_device" class="form-check-input" id="chkDevice" value="1" <?= $settings['restrict_device'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkDevice">Restrict to one device</label>
                        <small class="d-block text-muted">Prevents multiple device logins</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Allowed IP Addresses</label>
                        <textarea name="allowed_ips" class="form-control" rows="3" placeholder="One per line, e.g.: 192.168.1.0/24"><?= sanitizeInput($settings['allowed_ips'] ?? '') ?></textarea>
                        <small class="text-muted">Leave empty to allow all</small>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-save me-2"></i>Save</div>
                <div class="card-body">
                    <button type="submit" name="save_security" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Security Settings</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
