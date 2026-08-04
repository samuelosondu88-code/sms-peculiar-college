<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Audit Logs';
$db = getDB();

$action = sanitizeInput($_GET['action'] ?? '');
$table = sanitizeInput($_GET['table'] ?? '');
$userId = (int)($_GET['user_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sqlWhere = 'WHERE 1=1';
$params = [];
if ($action) { $sqlWhere .= ' AND al.action = ?'; $params[] = $action; }
if ($table) { $sqlWhere .= ' AND al.table_name = ?'; $params[] = $table; }
if ($userId) { $sqlWhere .= ' AND al.user_id = ?'; $params[] = $userId; }
if ($dateFrom) { $sqlWhere .= ' AND al.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo) { $sqlWhere .= ' AND al.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }

$countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al $sqlWhere");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$pagi = paginate($total, $page, $limit);

$stmt = $db->prepare("SELECT al.*, u.first_name, u.last_name, u.role FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id $sqlWhere ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$tables = $db->query("SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL AND table_name != '' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("SELECT DISTINCT al.user_id, u.first_name, u.last_name, u.role FROM audit_logs al JOIN users u ON al.user_id = u.id ORDER BY u.first_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-history me-2"></i>Audit Logs</h4>
    <small class="text-muted"><?= number_format($total) ?> total entries</small>
</div>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-2">
        <select name="action" class="form-select form-select-sm">
            <option value="">All Actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= sanitizeInput($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= sanitizeInput($a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="table" class="form-select form-select-sm">
            <option value="">All Tables</option>
            <?php foreach ($tables as $t): ?>
            <option value="<?= sanitizeInput($t) ?>" <?= $table === $t ? 'selected' : '' ?>><?= sanitizeInput($t) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="user_id" class="form-select form-select-sm">
            <option value="">All Users</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['user_id'] ?>" <?= $userId === (int)$u['user_id'] ? 'selected' : '' ?>><?= sanitizeInput($u['first_name'] . ' ' . $u['last_name']) ?> (<?= $u['role'] ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>" placeholder="From">
    </div>
    <div class="col-md-2">
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>" placeholder="To">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-dark">
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record</th>
                <th>Changes</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No audit log entries found.</td></tr>
            <?php else: foreach ($logs as $log): ?>
            <tr>
                <td class="small" style="white-space:nowrap;"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
                <td class="small"><?= sanitizeInput($log['first_name'] ?? '') . ' ' . sanitizeInput($log['last_name'] ?? '') ?><?= $log['user_id'] ? '' : ' <span class="text-muted">(deleted)</span>' ?></td>
                <td><span class="badge bg-secondary"><?= sanitizeInput($log['action']) ?></span></td>
                <td class="small"><?= sanitizeInput($log['table_name'] ?? '-') ?></td>
                <td class="small"><?= $log['record_id'] ?? '-' ?></td>
                <td class="small" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?php if ($log['old_value'] || $log['new_value']): ?>
                    <span title="Old: <?= sanitizeInput($log['old_value'] ?? '') ?>"><?= sanitizeInput(substr($log['old_value'] ?? '', 0, 50)) ?></span>
                    <i class="fas fa-arrow-right text-muted mx-1"></i>
                    <span title="New: <?= sanitizeInput($log['new_value'] ?? '') ?>"><?= sanitizeInput(substr($log['new_value'] ?? '', 0, 50)) ?></span>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td class="small"><?= sanitizeInput($log['ip_address'] ?? '-') ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pagi['totalPages'] > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page - 1 ?>&action=<?= urlencode($action) ?>&table=<?= urlencode($table) ?>&user_id=<?= $userId ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $pagi['totalPages']; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&action=<?= urlencode($action) ?>&table=<?= urlencode($table) ?>&user_id=<?= $userId ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $pagi['totalPages'] ? 'disabled' : '' ?>">
            <a class="page-link" href="?page=<?= $page + 1 ?>&action=<?= urlencode($action) ?>&table=<?= urlencode($table) ?>&user_id=<?= $userId ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
