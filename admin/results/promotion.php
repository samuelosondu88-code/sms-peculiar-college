<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Promotion Engine';
$db = getDB();
$msg = '';
$msgType = 'success';

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedClass = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $sessionId = (int)$_POST['session_id'];
    $classId = (int)$_POST['class_id'];
    $passMark = (float)($_POST['pass_mark'] ?? 40);
    $minSubjectsPass = (int)($_POST['min_subjects_pass'] ?? 5);
    $conditionalPassMark = (float)($_POST['conditional_pass_mark'] ?? 35);
    $maxFailSubjects = (int)($_POST['max_fail_subjects'] ?? 2);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $db->prepare("SELECT id FROM promotion_config WHERE session_id = ? AND class_id = ?");
    $stmt->execute([$sessionId, $classId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE promotion_config SET pass_mark = ?, min_subjects_pass = ?, conditional_pass_mark = ?, max_fail_subjects = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$passMark, $minSubjectsPass, $conditionalPassMark, $maxFailSubjects, $isActive, $existing['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO promotion_config (session_id, class_id, pass_mark, min_subjects_pass, conditional_pass_mark, max_fail_subjects, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sessionId, $classId, $passMark, $minSubjectsPass, $conditionalPassMark, $maxFailSubjects, $isActive]);
    }

    logAudit('promotion_config_save', 'promotion_config', null, null, "Session=$sessionId, Class=$classId");
    $msg = 'Promotion configuration saved successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_promotion'])) {
    $sessionId = (int)$_POST['session_id'];
    $classId = (int)$_POST['class_id'];

    $stmt = $db->prepare("SELECT s.id, s.admission_no, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.class_id = ? AND s.status = 'active'");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    $processed = 0;
    foreach ($students as $student) {
        $result = determinePromotion($db, $student['id'], $classId, $sessionId);

        $check = $db->prepare("SELECT id FROM promotion_results WHERE student_id = ? AND session_id = ?");
        $check->execute([$student['id'], $sessionId]);
        $existing = $check->fetch();

        if ($existing) {
            $db->prepare("UPDATE promotion_results SET from_class_id = ?, to_class_id = ?, annual_average = ?, promotion_status = ?, remark = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$classId, $result['to_class_id'], $result['annual_average'], $result['status'], $result['remark'], $existing['id']]);
        } else {
            $db->prepare("INSERT INTO promotion_results (student_id, from_class_id, to_class_id, session_id, annual_average, promotion_status, remark) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$student['id'], $classId, $result['to_class_id'], $sessionId, $result['annual_average'], $result['status'], $result['remark']]);
        }
        $processed++;
    }

    logAudit('promotion_run', 'promotion_results', null, null, "Session=$sessionId, Class=$classId, Processed=$processed");
    $msg = "Promotion engine completed. Processed $processed student(s).";
}

$config = [];
if ($selectedSession && $selectedClass) {
    $stmt = $db->prepare("SELECT * FROM promotion_config WHERE session_id = ? AND class_id = ?");
    $stmt->execute([$selectedSession, $selectedClass]);
    $config = $stmt->fetch() ?: ['pass_mark' => 40, 'min_subjects_pass' => 5, 'conditional_pass_mark' => 35, 'max_fail_subjects' => 2, 'is_active' => 0];
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$promoFilterSession = (int)($_GET['psession_id'] ?? $selectedSession);
$promoFilterClass = (int)($_GET['pclass_id'] ?? $selectedClass);
$promoFilterStatus = sanitizeInput($_GET['status'] ?? '');

$promoCount = 0;
$promoResults = [];

$sqlCount = "SELECT COUNT(*) FROM promotion_results pr WHERE 1=1";
$sqlData = "SELECT pr.*, u.first_name, u.last_name, s.admission_no, fc.name as from_class, tc.name as to_class FROM promotion_results pr JOIN students s ON pr.student_id = s.id JOIN users u ON s.user_id = u.id LEFT JOIN classes fc ON pr.from_class_id = fc.id LEFT JOIN classes tc ON pr.to_class_id = tc.id WHERE 1=1";
$params = [];

if ($promoFilterSession) { $sqlCount .= " AND pr.session_id = ?"; $sqlData .= " AND pr.session_id = ?"; $params[] = $promoFilterSession; }
if ($promoFilterClass) { $sqlCount .= " AND pr.from_class_id = ?"; $sqlData .= " AND pr.from_class_id = ?"; $params[] = $promoFilterClass; }
if ($promoFilterStatus) { $sqlCount .= " AND pr.promotion_status = ?"; $sqlData .= " AND pr.promotion_status = ?"; $params[] = $promoFilterStatus; }

$promoCount = (int)$db->prepare($sqlCount);
$promoCount->execute($params);
$promoCount = $promoCount->fetchColumn();

$sqlData .= " ORDER BY pr.annual_average DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sqlData);
$stmt->execute($params);
$promoResults = $stmt->fetchAll();

$totalPages = max(1, ceil($promoCount / $limit));

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-arrow-up me-2"></i>Promotion Engine</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-cog me-2"></i>Promotion Criteria</div>
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
                            <label class="form-label">Class</label>
                            <select name="class_id" class="form-select" required>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Pass Mark (%)</label>
                            <input type="number" name="pass_mark" class="form-control" value="<?= $config['pass_mark'] ?? 40 ?>" min="0" max="100" step="0.5" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Min Subjects to Pass</label>
                            <input type="number" name="min_subjects_pass" class="form-control" value="<?= $config['min_subjects_pass'] ?? 5 ?>" min="1" max="20" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Conditional Pass Mark</label>
                            <input type="number" name="conditional_pass_mark" class="form-control" value="<?= $config['conditional_pass_mark'] ?? 35 ?>" min="0" max="100" step="0.5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Max Fail Subjects</label>
                            <input type="number" name="max_fail_subjects" class="form-control" value="<?= $config['max_fail_subjects'] ?? 2 ?>" min="0" max="20">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" value="1" <?= !empty($config['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active (use this configuration for promotion)</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" name="save_config" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Configuration</button>
                        <button type="submit" name="run_promotion" class="btn btn-warning" onclick="return confirm('Run promotion engine for this class? This will recalculate all student promotions.')"><i class="fas fa-play me-1"></i>Run Promotion Engine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><i class="fas fa-list me-2"></i>Promotion Results</div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Session</label>
                        <select name="psession_id" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $promoFilterSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Class</label>
                        <select name="pclass_id" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $promoFilterClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="promoted" <?= $promoFilterStatus === 'promoted' ? 'selected' : '' ?>>Promoted</option>
                            <option value="conditional" <?= $promoFilterStatus === 'conditional' ? 'selected' : '' ?>>Conditional</option>
                            <option value="repeated" <?= $promoFilterStatus === 'repeated' ? 'selected' : '' ?>>Repeated</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission</th>
                                <th>From Class</th>
                                <th>To Class</th>
                                <th>Annual Avg</th>
                                <th>Status</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promoResults as $pr): ?>
                            <tr>
                                <td><?= sanitizeInput($pr['last_name'] . ', ' . $pr['first_name']) ?></td>
                                <td><?= sanitizeInput($pr['admission_no']) ?></td>
                                <td><?= sanitizeInput($pr['from_class'] ?? '-') ?></td>
                                <td><?= sanitizeInput($pr['to_class'] ?? '-') ?></td>
                                <td><strong><?= $pr['annual_average'] ?></strong></td>
                                <td>
                                    <?php if ($pr['promotion_status'] === 'promoted'): ?>
                                    <span class="badge bg-success">Promoted</span>
                                    <?php elseif ($pr['promotion_status'] === 'conditional'): ?>
                                    <span class="badge bg-warning text-dark">Conditional</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Repeated</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= sanitizeInput($pr['remark']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($promoResults)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No promotion results found. Run the promotion engine above.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&psession_id=<?= $promoFilterSession ?>&pclass_id=<?= $promoFilterClass ?>&status=<?= $promoFilterStatus ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
