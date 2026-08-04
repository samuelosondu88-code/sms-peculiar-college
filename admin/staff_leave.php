<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Staff Leave';
$db = getDB();
$msg = '';
$msgType = 'success';

if (isset($_GET['approve'])) {
    $lid = (int)$_GET['approve'];
    $db->prepare("UPDATE staff_leave SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $lid]);
    logActivity($_SESSION['user_id'], 'approve_leave', 'staff_leave', $lid);
    $msg = 'Leave approved.';
}

if (isset($_GET['reject'])) {
    $lid = (int)$_GET['reject'];
    $db->prepare("UPDATE staff_leave SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?")->execute([$_SESSION['user_id'], $lid]);
    logActivity($_SESSION['user_id'], 'reject_leave', 'staff_leave', $lid);
    $msg = 'Leave rejected.';
}

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT sl.*, u.first_name, u.last_name, u.role, u.email
        FROM staff_leave sl
        JOIN users u ON sl.user_id = u.id
        WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= " AND sl.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY sl.created_at DESC LIMIT 100";

$leaves = $db->prepare($sql);
$leaves->execute($params);
$leaves = $leaves->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-umbrella-beach me-2"></i>Staff Leave Management</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Status</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-dark">
            <tr><th>Staff</th><th>Role</th><th>Leave Type</th><th>Start</th><th>End</th><th>Days</th><th>Reason</th><th>Status</th><th>Applied</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($leaves as $l):
                $start = new DateTime($l['start_date']);
                $end = new DateTime($l['end_date']);
                $days = $start->diff($end)->days + 1;
            ?>
            <tr>
                <td><?= sanitizeInput($l['first_name'] . ' ' . $l['last_name']) ?></td>
                <td><span class="badge bg-info"><?= $l['role'] ?></span></td>
                <td><?= ucfirst($l['leave_type']) ?></td>
                <td class="small"><?= date('d M Y', strtotime($l['start_date'])) ?></td>
                <td class="small"><?= date('d M Y', strtotime($l['end_date'])) ?></td>
                <td><?= $days ?></td>
                <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitizeInput($l['reason']) ?>"><?= sanitizeInput($l['reason']) ?></td>
                <td>
                    <span class="badge bg-<?= $l['status'] === 'pending' ? 'warning text-dark' : ($l['status'] === 'approved' ? 'success' : ($l['status'] === 'rejected' ? 'danger' : 'secondary')) ?>">
                        <?= $l['status'] ?>
                    </span>
                </td>
                <td class="small"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                <td class="text-nowrap">
                    <?php if ($l['status'] === 'pending'): ?>
                    <a href="?approve=<?= $l['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this leave?')"><i class="fas fa-check"></i></a>
                    <a href="?reject=<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Reject this leave?')"><i class="fas fa-times"></i></a>
                    <?php else: ?>
                    <span class="text-muted small"><?= $l['status'] === 'approved' ? ('by ' . sanitizeInput($_SESSION['first_name'] ?? '')) : '' ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!count($leaves)): ?><tr><td colspan="10" class="text-center text-muted py-3">No leave records found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
