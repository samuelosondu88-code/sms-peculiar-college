<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Result PIN Management';
$db = getDB();
$msg = '';
$msgType = 'success';

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT id, term_name FROM terms ORDER BY session_id, id")->fetchAll();
$students = $db->query("SELECT s.id, u.first_name, u.last_name, s.admission_no, c.name as class_name FROM students s JOIN users u ON s.user_id = u.id LEFT JOIN classes c ON s.class_id = c.id WHERE u.status = 'active' ORDER BY u.last_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pins'])) {
    $sessionId = (int)$_POST['session_id'];
    $termId = (int)($_POST['term_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $assignStudentId = (int)($_POST['student_id'] ?? 0);
    $expiresAt = sanitizeInput($_POST['expires_at'] ?? '');
    $generatedBy = $_SESSION['user_id'];
    $generated = 0;

    $expiresValue = $expiresAt ?: null;

    for ($i = 0; $i < $quantity; $i++) {
        generateResultPin($db, $sessionId, $assignStudentId ?: null, $termId ?: null, $expiresValue, $generatedBy);
        $generated++;
    }

    logAudit('result_pins_generate', 'result_pins', null, null, "$generated PINs generated for session=$sessionId");
    $msg = "$generated result PIN(s) generated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $pinId = (int)$_POST['pin_id'];
    $stmt = $db->prepare("SELECT is_active FROM result_pins WHERE id = ?");
    $stmt->execute([$pinId]);
    $pin = $stmt->fetch();
    if ($pin) {
        $newActive = $pin['is_active'] ? 0 : 1;
        $db->prepare("UPDATE result_pins SET is_active = ? WHERE id = ?")->execute([$newActive, $pinId]);
        logAudit('result_pin_toggle', 'result_pins', $pinId, $pin['is_active'], $newActive);
        $msg = 'PIN ' . ($newActive ? 'activated' : 'deactivated') . ' successfully.';
    }
}

$search = sanitizeInput($_GET['search'] ?? '');
$filterSession = (int)($_GET['session_id'] ?? 0);
$filterStatus = sanitizeInput($_GET['status'] ?? '');

$sql = "SELECT rp.*, u.first_name, u.last_name, s.admission_no, ac.session_name FROM result_pins rp LEFT JOIN students s ON rp.student_id = s.id LEFT JOIN users u ON s.user_id = u.id LEFT JOIN academic_sessions ac ON rp.session_id = ac.id WHERE 1=1";
$params = [];

if ($filterSession) { $sql .= " AND rp.session_id = ?"; $params[] = $filterSession; }
if ($filterStatus === 'active') { $sql .= " AND rp.is_active = 1 AND rp.is_used = 0"; }
elseif ($filterStatus === 'used') { $sql .= " AND rp.is_used = 1"; }
elseif ($filterStatus === 'inactive') { $sql .= " AND rp.is_active = 0"; }
elseif ($filterStatus === 'expired') { $sql .= " AND rp.expires_at IS NOT NULL AND rp.expires_at < NOW() AND rp.is_used = 0"; }

if ($search) { $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.admission_no LIKE ? OR rp.pin LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; }
$sql .= " ORDER BY rp.id DESC LIMIT 200";

$pins = $db->prepare($sql);
$pins->execute($params);
$pinList = $pins->fetchAll();

$stats = [
    'total' => (int)$db->query("SELECT COUNT(*) FROM result_pins")->fetchColumn(),
    'active' => (int)$db->query("SELECT COUNT(*) FROM result_pins WHERE is_active = 1 AND is_used = 0")->fetchColumn(),
    'used' => (int)$db->query("SELECT COUNT(*) FROM result_pins WHERE is_used = 1")->fetchColumn(),
    'expired' => (int)$db->query("SELECT COUNT(*) FROM result_pins WHERE is_active = 0 AND is_used = 0")->fetchColumn(),
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-key me-2"></i>Result PIN Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal"><i class="fas fa-plus me-1"></i>Generate PINs</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total PINs</div></div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['active'] ?></div><div class="stat-label">Active</div></div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['used'] ?></div><div class="stat-label">Used</div></div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning"><div class="stat-value"><?= $stats['expired'] ?></div><div class="stat-label">Inactive/Expired</div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select">
                    <option value="">All Sessions</option>
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="used" <?= $filterStatus === 'used' ? 'selected' : '' ?>>Used</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="expired" <?= $filterStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="PIN, student name or admission..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>PIN List</span>
        <?php if (!empty($pinList)): ?>
        <a href="?export=csv&<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>PIN</th>
                        <th>Student</th>
                        <th>Admission</th>
                        <th>Session</th>
                        <th>Status</th>
                        <th>Used At</th>
                        <th>Expires</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pinList as $p): ?>
                    <?php
                    $pinStatus = 'active';
                    if ($p['is_used']) $pinStatus = 'used';
                    elseif (!$p['is_active']) $pinStatus = 'inactive';
                    elseif ($p['expires_at'] && strtotime($p['expires_at']) < time()) $pinStatus = 'expired';
                    ?>
                    <tr>
                        <td><code><?= sanitizeInput($p['pin']) ?></code></td>
                        <td><?= $p['first_name'] ? sanitizeInput($p['last_name'] . ', ' . $p['first_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                        <td><?= sanitizeInput($p['admission_no'] ?? '-') ?></td>
                        <td><?= sanitizeInput($p['session_name'] ?? '-') ?></td>
                        <td>
                            <?php if ($pinStatus === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php elseif ($pinStatus === 'used'): ?>
                            <span class="badge bg-info">Used</span>
                            <?php elseif ($pinStatus === 'expired'): ?>
                            <span class="badge bg-warning text-dark">Expired</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['used_at'] ? formatDate($p['used_at']) : '-' ?></td>
                        <td><?= $p['expires_at'] ? formatDate($p['expires_at']) : '<span class="text-muted">No expiry</span>' ?></td>
                        <td>
                            <?php if (!$p['is_used']): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="pin_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="toggle_active" class="btn btn-sm btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>" title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fas fa-<?= $p['is_active'] ? 'ban' : 'check' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pinList)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No PINs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Generate Result PINs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Session <span class="text-danger">*</span></label>
                        <select name="session_id" class="form-select" required>
                            <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term (optional)</label>
                        <select name="term_id" class="form-select">
                            <option value="">All Terms</option>
                            <?php foreach ($terms as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= sanitizeInput($t['term_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" class="form-control" value="10" min="1" max="500" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign to Student (optional)</label>
                        <select name="student_id" class="form-select">
                            <option value="">No specific student</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['admission_no'] . ') - ' . $s['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Leave empty for general-purpose PINs</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date (optional)</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_pins" class="btn btn-primary"><i class="fas fa-key me-1"></i>Generate PINs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($pinList)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="result_pins_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['PIN', 'Student', 'Admission No', 'Session', 'Status', 'Used At', 'Expires At']);
    foreach ($pinList as $p) {
        fputcsv($output, [$p['pin'], $p['first_name'] ? $p['last_name'] . ', ' . $p['first_name'] : 'Unassigned', $p['admission_no'] ?? '', $p['session_name'] ?? '', $p['is_used'] ? 'Used' : ($p['is_active'] ? 'Active' : 'Inactive'), $p['used_at'] ?? '', $p['expires_at'] ?? '']);
    }
    fclose($output);
    exit;
}
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
