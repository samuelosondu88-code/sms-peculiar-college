<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Result Settings';
$db = getDB();
$msg = '';
$msgType = 'success';

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT id, term_name, session_id FROM terms ORDER BY session_id, id")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_GET['term_id'] ?? $_POST['term_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $sessionId = (int)$_POST['session_id'];
    $termId = (int)$_POST['term_id'];

    $settings = [
        'ca_weight' => (float)($_POST['ca_weight'] ?? 40),
        'exam_weight' => (float)($_POST['exam_weight'] ?? 60),
        'pass_mark' => (float)($_POST['pass_mark'] ?? 40),
        'grade_a_min' => (float)($_POST['grade_a_min'] ?? 75),
        'grade_b_min' => (float)($_POST['grade_b_min'] ?? 60),
        'grade_c_min' => (float)($_POST['grade_c_min'] ?? 50),
        'grade_d_min' => (float)($_POST['grade_d_min'] ?? 40),
        'grade_e_min' => (float)($_POST['grade_e_min'] ?? 30),
        'max_assign1' => (float)($_POST['max_assign1'] ?? 10),
        'max_assign2' => (float)($_POST['max_assign2'] ?? 10),
        'max_test1' => (float)($_POST['max_test1'] ?? 10),
        'max_test2' => (float)($_POST['max_test2'] ?? 10),
        'max_exam' => (float)($_POST['max_exam'] ?? 60),
        'ca_max' => (float)($_POST['ca_max'] ?? 40),
    ];

    $stmt = $db->prepare("SELECT id FROM result_settings WHERE session_id = ? AND term_id = ?");
    $stmt->execute([$sessionId, $termId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE result_settings SET ca_weight = ?, exam_weight = ?, pass_mark = ?, grade_a_min = ?, grade_b_min = ?, grade_c_min = ?, grade_d_min = ?, grade_e_min = ?, max_assign1 = ?, max_assign2 = ?, max_test1 = ?, max_test2 = ?, max_exam = ?, ca_max = ? WHERE session_id = ? AND term_id = ?");
        $stmt->execute([$settings['ca_weight'], $settings['exam_weight'], $settings['pass_mark'], $settings['grade_a_min'], $settings['grade_b_min'], $settings['grade_c_min'], $settings['grade_d_min'], $settings['grade_e_min'], $settings['max_assign1'], $settings['max_assign2'], $settings['max_test1'], $settings['max_test2'], $settings['max_exam'], $settings['ca_max'], $sessionId, $termId]);
    } else {
        $stmt = $db->prepare("INSERT INTO result_settings (session_id, term_id, ca_weight, exam_weight, pass_mark, grade_a_min, grade_b_min, grade_c_min, grade_d_min, grade_e_min, max_assign1, max_assign2, max_test1, max_test2, max_exam, ca_max) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sessionId, $termId, $settings['ca_weight'], $settings['exam_weight'], $settings['pass_mark'], $settings['grade_a_min'], $settings['grade_b_min'], $settings['grade_c_min'], $settings['grade_d_min'], $settings['grade_e_min'], $settings['max_assign1'], $settings['max_assign2'], $settings['max_test1'], $settings['max_test2'], $settings['max_exam'], $settings['ca_max']]);
    }

    logAudit('result_settings_update', 'result_settings', null, null, "Session=$sessionId, Term=$termId");
    $msg = 'Result settings saved successfully.';
}

$currentSettings = [];
if ($selectedSession && $selectedTerm) {
    $currentSettings = getResultSettings($db, $selectedSession, $selectedTerm);
}

$allSettings = $db->query("
    SELECT rs.*, ac.session_name, t.term_name
    FROM result_settings rs
    JOIN academic_sessions ac ON rs.session_id = ac.id
    JOIN terms t ON rs.term_id = t.id
    ORDER BY ac.start_date DESC, t.id
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-sliders-h me-2"></i>Result Settings</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-edit me-2"></i>Configure Settings</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Session</label>
                            <select name="session_id" class="form-select" required>
                                <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Term</label>
                            <select name="term_id" class="form-select" required>
                                <?php foreach ($terms as $t): ?>
                                <?php if ($t['session_id'] == $selectedSession): ?>
                                <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">CA Weight (%)</label>
                            <input type="number" name="ca_weight" class="form-control" value="<?= $currentSettings['ca_weight'] ?? 40 ?>" min="0" max="100" step="0.5" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Exam Weight (%)</label>
                            <input type="number" name="exam_weight" class="form-control" value="<?= $currentSettings['exam_weight'] ?? 60 ?>" min="0" max="100" step="0.5" required>
                        </div>
                    </div>
                    <h6 class="fw-bold mt-4 mb-2">CA Component Max Scores</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label">Assign 1</label><input type="number" name="max_assign1" class="form-control" value="<?= $currentSettings['max_assign1'] ?? 10 ?>" min="0" max="100" step="0.5" required></div>
                        <div class="col-4"><label class="form-label">Assign 2</label><input type="number" name="max_assign2" class="form-control" value="<?= $currentSettings['max_assign2'] ?? 10 ?>" min="0" max="100" step="0.5" required></div>
                        <div class="col-4"><label class="form-label">Test 1</label><input type="number" name="max_test1" class="form-control" value="<?= $currentSettings['max_test1'] ?? 10 ?>" min="0" max="100" step="0.5" required></div>
                        <div class="col-4"><label class="form-label">Test 2</label><input type="number" name="max_test2" class="form-control" value="<?= $currentSettings['max_test2'] ?? 10 ?>" min="0" max="100" step="0.5" required></div>
                        <div class="col-4"><label class="form-label">CA Total Cap</label><input type="number" name="ca_max" class="form-control" value="<?= $currentSettings['ca_max'] ?? 40 ?>" min="0" max="100" step="0.5" required></div>
                        <div class="col-4"><label class="form-label">Exam</label><input type="number" name="max_exam" class="form-control" value="<?= $currentSettings['max_exam'] ?? 60 ?>" min="0" max="100" step="0.5" required></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pass Mark (%)</label>
                        <input type="number" name="pass_mark" class="form-control" value="<?= $currentSettings['pass_mark'] ?? 40 ?>" min="0" max="100" step="0.5" required>
                    </div>
                    <h6 class="fw-bold mt-4 mb-2">Grade Boundaries</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-4"><label class="form-label">A (min)</label><input type="number" name="grade_a_min" class="form-control" value="<?= $currentSettings['grade_a_min'] ?? 75 ?>" min="0" max="100" required></div>
                        <div class="col-4"><label class="form-label">B (min)</label><input type="number" name="grade_b_min" class="form-control" value="<?= $currentSettings['grade_b_min'] ?? 60 ?>" min="0" max="100" required></div>
                        <div class="col-4"><label class="form-label">C (min)</label><input type="number" name="grade_c_min" class="form-control" value="<?= $currentSettings['grade_c_min'] ?? 50 ?>" min="0" max="100" required></div>
                        <div class="col-6"><label class="form-label">D (min)</label><input type="number" name="grade_d_min" class="form-control" value="<?= $currentSettings['grade_d_min'] ?? 40 ?>" min="0" max="100" required></div>
                        <div class="col-6"><label class="form-label">E (min)</label><input type="number" name="grade_e_min" class="form-control" value="<?= $currentSettings['grade_e_min'] ?? 30 ?>" min="0" max="100" required></div>
                    </div>
                    <button type="submit" name="save_settings" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i>Save Settings</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>All Configured Settings</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Term</th>
                                <th>CA</th>
                                <th>Exam</th>
                                <th>Max A1</th>
                                <th>Max A2</th>
                                <th>Max T1</th>
                                <th>Max T2</th>
                                <th>CA Cap</th>
                                <th>Max Ex</th>
                                <th>Pass</th>
                                <th>A</th>
                                <th>B</th>
                                <th>C</th>
                                <th>D</th>
                                <th>E</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allSettings as $s): ?>
                            <tr>
                                <td><?= sanitizeInput($s['session_name']) ?></td>
                                <td><?= sanitizeInput($s['term_name']) ?></td>
                                <td><?= $s['ca_weight'] ?>%</td>
                                <td><?= $s['exam_weight'] ?>%</td>
                                <td><?= $s['max_assign1'] ?></td>
                                <td><?= $s['max_assign2'] ?></td>
                                <td><?= $s['max_test1'] ?></td>
                                <td><?= $s['max_test2'] ?></td>
                                <td><?= $s['ca_max'] ?></td>
                                <td><?= $s['max_exam'] ?></td>
                                <td><span class="badge bg-<?= $s['pass_mark'] >= 40 ? 'success' : 'warning' ?>"><?= $s['pass_mark'] ?></span></td>
                                <td><?= $s['grade_a_min'] ?>+</td>
                                <td><?= $s['grade_b_min'] ?>+</td>
                                <td><?= $s['grade_c_min'] ?>+</td>
                                <td><?= $s['grade_d_min'] ?>+</td>
                                <td><?= $s['grade_e_min'] ?>+</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($allSettings)): ?>
                            <tr><td colspan="16" class="text-center text-muted py-3">No settings configured yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
