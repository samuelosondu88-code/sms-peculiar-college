<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'My Leave';
$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = $_POST['leave_type'] ?? 'annual';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = sanitizeInput($_POST['reason'] ?? '');

    if (!$start_date || !$end_date || !$reason) {
        $msg = 'All fields are required.'; $msgType = 'danger';
    } elseif ($start_date > $end_date) {
        $msg = 'End date must be after start date.'; $msgType = 'danger';
    } else {
        $db->prepare("INSERT INTO staff_leave (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)")
            ->execute([$_SESSION['user_id'], $leave_type, $start_date, $end_date, $reason]);
        $msg = 'Leave application submitted.';
        logActivity($_SESSION['user_id'], 'apply_leave', 'staff_leave', (int)$db->lastInsertId());
    }
}

if (isset($_GET['cancel'])) {
    $cid = (int)$_GET['cancel'];
    $db->prepare("UPDATE staff_leave SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'")->execute([$cid, $_SESSION['user_id']]);
    $msg = 'Leave request cancelled.';
}

$myLeaves = $db->prepare("SELECT * FROM staff_leave WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$myLeaves->execute([$_SESSION['user_id']]);
$myLeaves = $myLeaves->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-umbrella-beach me-2"></i>My Leave</h4>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal"><i class="fas fa-plus me-1"></i>Apply Leave</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-dark"><tr><th>Type</th><th>Start</th><th>End</th><th>Days</th><th>Reason</th><th>Status</th><th>Applied</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($myLeaves as $l):
                $start = new DateTime($l['start_date']);
                $end = new DateTime($l['end_date']);
                $days = $start->diff($end)->days + 1;
            ?>
            <tr>
                <td><?= ucfirst($l['leave_type']) ?></td>
                <td class="small"><?= date('d M Y', strtotime($l['start_date'])) ?></td>
                <td class="small"><?= date('d M Y', strtotime($l['end_date'])) ?></td>
                <td><?= $days ?></td>
                <td class="small"><?= sanitizeInput($l['reason']) ?></td>
                <td>
                    <span class="badge bg-<?= $l['status'] === 'pending' ? 'warning text-dark' : ($l['status'] === 'approved' ? 'success' : ($l['status'] === 'rejected' ? 'danger' : 'secondary')) ?>">
                        <?= $l['status'] ?>
                    </span>
                </td>
                <td class="small"><?= date('d M', strtotime($l['created_at'])) ?></td>
                <td>
                    <?php if ($l['status'] === 'pending'): ?>
                    <a href="?cancel=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Cancel this leave request?')"><i class="fas fa-ban"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!count($myLeaves)): ?><tr><td colspan="8" class="text-center text-muted py-3">No leave applications yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Apply for Leave</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Leave Type</label>
                    <select name="leave_type" class="form-select">
                        <option value="annual">Annual</option>
                        <option value="sick">Sick</option>
                        <option value="personal">Personal</option>
                        <option value="maternity">Maternity</option>
                        <option value="paternity">Paternity</option>
                        <option value="study">Study</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Date *</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason *</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="apply_leave" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
