<?php
require_once __DIR__ . '/../config/session.php';
requireRole('accountant');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Payroll Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $staffId = (int)($_POST['staff_id'] ?? 0);
    $grossSalary = (float)($_POST['gross_salary'] ?? 0);
    $deductions = (float)($_POST['deductions'] ?? 0);
    $netSalary = $grossSalary - $deductions;
    $month = sanitizeInput($_POST['month'] ?? date('Y-m'));

    if ($staffId && $grossSalary > 0) {
        $stmt = $db->prepare("INSERT INTO payroll (user_id, gross_salary, deductions, net_salary, payment_date, month, processed_by) VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
        $stmt->execute([$staffId, $grossSalary, $deductions, $netSalary, $month, $_SESSION['user_id']]);
        $msg = 'Payroll processed.';
    }
}

$payroll = $db->query("SELECT p.*, u.first_name, u.last_name, u.role FROM payroll p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 50")->fetchAll();
$staff = $db->query("SELECT u.id, u.first_name, u.last_name, u.role FROM users u WHERE u.role IN ('teacher', 'admin', 'accountant') AND u.status = 'active'")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-wallet me-2"></i>Payroll</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#payrollModal"><i class="fas fa-plus me-1"></i>Process Payroll</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Staff</th><th>Role</th><th>Month</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($payroll as $p): ?>
                    <tr>
                        <td><?= sanitizeInput($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= getRoleBadge($p['role']) ?></td>
                        <td><?= $p['month'] ?></td>
                        <td><?= formatCurrency($p['gross_salary']) ?></td>
                        <td class="text-danger"><?= formatCurrency($p['deductions']) ?></td>
                        <td><strong><?= formatCurrency($p['net_salary']) ?></strong></td>
                        <td><?= getStatusBadge($p['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payroll)): ?><tr><td colspan="7" class="text-center text-muted py-3">No payroll records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="payrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Process Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Staff</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($staff as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['first_name'] . ' ' . $s['last_name']) ?> (<?= ucfirst($s['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= date('Y-m') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Gross Salary (₦)</label><input type="number" name="gross_salary" class="form-control" step="0.01" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Deductions (₦)</label><input type="number" name="deductions" class="form-control" step="0.01" value="0"></div>
                </div>
                <div class="modal-footer"><input type="hidden" name="process_payroll" value="1"><button type="submit" class="btn btn-primary">Process</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
