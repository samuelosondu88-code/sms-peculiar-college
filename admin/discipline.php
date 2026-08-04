<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Discipline Records';
$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action_taken = sanitizeInput($_POST['action_taken'] ?? '');
    $status = $_POST['status'] ?? 'open';

    if ($id) {
        $db->prepare("UPDATE behavior_records SET action_taken = ?, status = ? WHERE id = ?")->execute([$action_taken, $status, $id]);
        $msg = 'Record updated.';
        logActivity($_SESSION['user_id'], 'update_discipline', 'behavior_records', $id);
    }
}

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $db->prepare("DELETE FROM behavior_records WHERE id = ?")->execute([$did]);
    logActivity($_SESSION['user_id'], 'delete_discipline', 'behavior_records', $did);
    redirect('/admin/discipline.php');
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$sql = "SELECT b.*, u.first_name AS sfn, u.last_name AS sln, s.admission_no, u2.first_name AS rfn, u2.last_name AS rln, c.name AS class_name
        FROM behavior_records b
        JOIN students s ON b.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN users u2 ON b.reported_by = u2.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.admission_no LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($statusFilter) {
    $sql .= " AND b.status = ?"; $params[] = $statusFilter;
}
if ($typeFilter) {
    $sql .= " AND b.incident_type = ?"; $params[] = $typeFilter;
}
$sql .= " ORDER BY b.incident_date DESC LIMIT 100";

$records = $db->prepare($sql);
$records->execute($params);
$records = $records->fetchAll();

$types = $db->query("SELECT DISTINCT incident_type FROM behavior_records WHERE incident_type IS NOT NULL AND incident_type != '' ORDER BY incident_type")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-gavel me-2"></i>Discipline Records</h4>
    <a href="?export=csv" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i>Export CSV</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search student name or admission no..." value="<?= sanitizeInput($search) ?>">
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
    </div>
    <div class="col-md-3">
        <select name="type" class="form-select form-select-sm">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-dark">
            <tr><th>Date</th><th>Student</th><th>Class</th><th>Admission No</th><th>Type</th><th>Description</th><th>Action Taken</th><th>Reported By</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td class="small"><?= date('d M Y', strtotime($r['incident_date'])) ?></td>
                <td><?= sanitizeInput($r['sfn'] . ' ' . $r['sln']) ?></td>
                <td class="small"><?= sanitizeInput($r['class_name'] ?? '-') ?></td>
                <td class="small"><?= sanitizeInput($r['admission_no']) ?></td>
                <td><span class="badge bg-warning text-dark"><?= sanitizeInput($r['incident_type']) ?></span></td>
                <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitizeInput($r['description']) ?>"><?= sanitizeInput($r['description']) ?></td>
                <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= sanitizeInput($r['action_taken'] ?? '-') ?></td>
                <td class="small"><?= sanitizeInput($r['rfn'] . ' ' . $r['rln']) ?></td>
                <td>
                    <span class="badge bg-<?= $r['status'] === 'open' ? 'danger' : ($r['status'] === 'resolved' ? 'warning' : 'secondary') ?>"><?= $r['status'] ?></span>
                </td>
                <td class="text-nowrap">
                    <button class="btn btn-sm btn-outline-primary" onclick="editRecord(<?= $r['id'] ?>, '<?= sanitizeInput($r['action_taken'] ?? '') ?>', '<?= $r['status'] ?>')"><i class="fas fa-edit"></i></button>
                    <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this record?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!count($records)): ?><tr><td colspan="10" class="text-center text-muted py-3">No records found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="disciplineModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Discipline Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="recordId" value="0">
                <div class="mb-3">
                    <label class="form-label">Action Taken</label>
                    <textarea name="action_taken" id="rAction" class="form-control" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" id="rStatus" class="form-select">
                        <option value="open">Open</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </div>
        </form>
    </div>
</div>

<script>
function editRecord(id, action, status) {
    document.getElementById('recordId').value = id;
    document.getElementById('rAction').value = action;
    document.getElementById('rStatus').value = status;
    new bootstrap.Modal(document.getElementById('disciplineModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
