<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Student PIN Management';
$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pins'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    $expires = sanitizeInput($_POST['expires'] ?? '');
    $maxAttempts = (int)($_POST['max_attempts'] ?? 5);
    $generated = 0;
    foreach ($studentIds as $sid) {
        $sid = (int)$sid;
        $pin = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $db->prepare("INSERT INTO student_pins (student_id, pin, generated_by, expires_at, max_attempts) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sid, $pin, $_SESSION['user_id'], $expires ?: null, $maxAttempts]);
        $generated++;
    }
    $msg = "$generated PIN(s) generated successfully.";
    logAudit('pins_generate', 'student_pins', null, null, "$generated PINs generated");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_single'])) {
    $sid = (int)($_POST['student_id']);
    $expires = sanitizeInput($_POST['expires_single'] ?? '');
    $maxAttempts = (int)($_POST['max_attempts_single'] ?? 5);
    if ($sid) {
        $db->prepare("UPDATE student_pins SET status = 'expired' WHERE student_id = ? AND status = 'active'")->execute([$sid]);
        $pin = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $db->prepare("INSERT INTO student_pins (student_id, pin, generated_by, expires_at, max_attempts) VALUES (?, ?, ?, ?, ?)")->execute([$sid, $pin, $_SESSION['user_id'], $expires ?: null, $maxAttempts]);
        $msg = "PIN generated: <strong>$pin</strong>";
        $msgType = 'success';
        logAudit('pin_generate_single', 'student_pins', $sid, null, "PIN generated for student #$sid");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_pin'])) {
    $pinId = (int)$_POST['pin_id'];
    $db->prepare("UPDATE student_pins SET status = 'revoked' WHERE id = ?")->execute([$pinId]);
    $msg = 'PIN revoked successfully.';
    logAudit('pin_revoke', 'student_pins', $pinId);
}

if (isset($_GET['print']) && $_GET['print'] === 'pins') {
    $ids = $_GET['ids'] ?? '';
    $idArr = array_filter(array_map('intval', explode(',', $ids)));
    if (!empty($idArr)) {
        $placeholders = implode(',', array_fill(0, count($idArr), '?'));
        $stmt = $db->prepare("SELECT sp.*, u.first_name, u.last_name, s.admission_no, c.name as class_name, c.section FROM student_pins sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id JOIN classes c ON s.class_id = c.id WHERE sp.id IN ($placeholders)");
        $stmt->execute($idArr);
        $pins = $stmt->fetchAll();
        ?>
        <!DOCTYPE html>
        <html><head><title>Print PINs</title>
        <style>
            body { font-family: monospace; padding: 20px; }
            .pin-slip { border: 2px solid #000; padding: 15px; margin: 10px; page-break-after: always; text-align: center; }
            .pin-code { font-size: 28px; letter-spacing: 4px; font-weight: bold; margin: 15px 0; }
            .school-name { font-size: 18px; font-weight: bold; }
            @media print { .no-print { display: none; } }
        </style>
        </head><body>
        <div class="no-print" style="margin-bottom:20px"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></div>
        <?php foreach ($pins as $p): ?>
        <div class="pin-slip">
            <div class="school-name"><?= SCHOOL_NAME ?></div>
            <div style="font-size:12px;color:#666">Student PIN Slip</div>
            <hr>
            <div><strong><?= sanitizeInput($p['first_name'] . ' ' . $p['last_name']) ?></strong></div>
            <div>Admission: <?= sanitizeInput($p['admission_no']) ?></div>
            <div>Class: <?= sanitizeInput($p['class_name'] . ' ' . $p['section']) ?></div>
            <div class="pin-code">PIN: <?= sanitizeInput($p['pin']) ?></div>
            <div style="font-size:11px;color:#666">
                Valid until: <?= $p['expires_at'] ? formatDate($p['expires_at']) : 'No expiry' ?>
                | Max attempts: <?= $p['max_attempts'] ?>
            </div>
            <div style="font-size:10px;color:#999;margin-top:10px">Login at: <?= APP_URL ?>/auth/login.php?mode=pin</div>
        </div>
        <?php endforeach; ?>
        </body></html>
        <?php exit;
    }
}

$filterStatus = sanitizeInput($_GET['status'] ?? '');
$filterClass = (int)($_GET['class_id'] ?? 0);
$search = sanitizeInput($_GET['search'] ?? '');

$sql = "SELECT sp.*, u.first_name, u.last_name, s.admission_no, c.name as class_name, c.section FROM student_pins sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id JOIN classes c ON s.class_id = c.id WHERE 1=1";
$params = [];
if ($filterStatus) { $sql .= " AND sp.status = ?"; $params[] = $filterStatus; }
if ($filterClass) { $sql .= " AND s.class_id = ?"; $params[] = $filterClass; }
if ($search) { $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.admission_no LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }
$sql .= " ORDER BY sp.generated_at DESC LIMIT 100";
$pins = $db->prepare($sql);
$pins->execute($params);
$pinList = $pins->fetchAll();

$allStudents = $db->query("SELECT s.id, u.first_name, u.last_name, s.admission_no, c.name as class_name, c.section FROM students s JOIN users u ON s.user_id = u.id JOIN classes c ON s.class_id = c.id WHERE u.status = 'active' ORDER BY u.last_name")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$stats = [
    'active' => (int)$db->query("SELECT COUNT(*) FROM student_pins WHERE status='active'")->fetchColumn(),
    'used' => (int)$db->query("SELECT COUNT(*) FROM student_pins WHERE status='used'")->fetchColumn(),
    'expired' => (int)$db->query("SELECT COUNT(*) FROM student_pins WHERE status='expired'")->fetchColumn(),
    'revoked' => (int)$db->query("SELECT COUNT(*) FROM student_pins WHERE status='revoked'")->fetchColumn(),
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-key me-2"></i>Student PIN Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal"><i class="fas fa-plus me-1"></i>Generate PINs</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach (['active'=>'Active','used'=>'Used','expired'=>'Expired','revoked'=>'Revoked'] as $k => $v): ?>
    <div class="col-md-3">
        <div class="stat-card <?= $k === 'active' ? 'stat-success' : ($k === 'used' ? 'stat-info' : ($k === 'expired' ? 'stat-warning' : 'bg-danger')) ?>">
            <div class="stat-value"><?= $stats[$k] ?></div>
            <div class="stat-label"><?= $v ?> PINs</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['active','used','expired','revoked'] as $st): ?>
                    <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name or admission no..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fas fa-list me-2"></i>PIN List</span>
        <?php if (!empty($pinList)): ?>
        <a href="?print=pins&ids=<?= implode(',', array_column($pinList, 'id')) ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-print me-1"></i>Print All</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Student</th><th>Admission</th><th>Class</th><th>PIN</th><th>Status</th><th>Attempts</th><th>Generated</th><th>Expires</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pinList as $p): ?>
                    <tr>
                        <td><?= sanitizeInput($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= sanitizeInput($p['admission_no']) ?></td>
                        <td><?= sanitizeInput($p['class_name'] . ' ' . $p['section']) ?></td>
                        <td><code><?= sanitizeInput($p['pin']) ?></code></td>
                        <td><?= getStatusBadge($p['status']) ?></td>
                        <td><?= $p['attempts'] ?>/<?= $p['max_attempts'] ?></td>
                        <td><small><?= timeAgo($p['generated_at']) ?></small></td>
                        <td><?= $p['expires_at'] ? formatDate($p['expires_at']) : '-' ?></td>
                        <td>
                            <?php if ($p['status'] === 'active'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this PIN?')">
                                <input type="hidden" name="pin_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="revoke_pin" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pinList)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No PINs found. Generate PINs for students above.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Generate Student PINs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="pinTabs">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#bulk">Bulk Generate</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#single">Single Student</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="bulk">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Select Students</label>
                                <select name="student_ids[]" class="form-select" multiple size="10" required>
                                    <?php foreach ($allStudents as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['admission_no'] . ') - ' . $s['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">PIN Expiry Date (optional)</label>
                                    <input type="date" name="expires" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Max Login Attempts</label>
                                    <input type="number" name="max_attempts" class="form-control" value="5" min="1" max="20">
                                </div>
                            </div>
                            <button type="submit" name="generate_pins" class="btn btn-primary"><i class="fas fa-key me-1"></i>Generate PINs</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="single">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Student</label>
                                <select name="student_id" class="form-select" required>
                                    <option value="">Select student...</option>
                                    <?php foreach ($allStudents as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['admission_no'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Expiry Date (optional)</label>
                                    <input type="date" name="expires_single" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Max Attempts</label>
                                    <input type="number" name="max_attempts_single" class="form-control" value="5" min="1" max="20">
                                </div>
                            </div>
                            <button type="submit" name="generate_single" class="btn btn-primary"><i class="fas fa-key me-1"></i>Generate PIN</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
