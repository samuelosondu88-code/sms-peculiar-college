<?php
require_once __DIR__ . '/../config/session.php';
requireRole('accountant');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Accountant Dashboard';
$db = getDB();

$stmt = $db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$pendingPayments = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE status = 'approved' AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
$monthlyRevenue = (float)$stmt->fetchColumn();

$stmt = $db->query("SELECT COALESCE(SUM(balance), 0) FROM fees WHERE status = 'unpaid' OR status = 'partial'");
$outstandingFees = (float)$stmt->fetchColumn();

$recentPayments = $db->query("
    SELECT p.*, u.first_name, u.last_name FROM payments p
    JOIN fees f ON p.fee_id = f.id
    JOIN students s ON f.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC LIMIT 10
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Accountant Dashboard</h4>
        <p class="text-muted small">Welcome, <?= sanitizeInput($_SESSION['user_name']) ?>!</p>
    </div>
    <a href="<?= BASE_URL ?>/accountant/payments.php" class="btn btn-warning fw-bold">
        <i class="fas fa-clock me-1"></i><?= $pendingPayments ?> Pending
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <i class="fas fa-money-bill-wave stat-icon"></i>
            <div class="stat-value"><?= formatCurrency($monthlyRevenue) ?></div>
            <div class="stat-label">Monthly Revenue</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-danger">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= formatCurrency($outstandingFees) ?></div>
            <div class="stat-label">Outstanding Fees</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-warning">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value"><?= $pendingPayments ?></div>
            <div class="stat-label">Pending Verifications</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fas fa-clock me-2"></i>Pending Payment Verifications</span>
        <a href="<?= BASE_URL ?>/accountant/payments.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Student</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><?= sanitizeInput($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><strong><?= formatCurrency($p['amount_paid']) ?></strong></td>
                        <td><?= ucfirst($p['payment_method']) ?></td>
                        <td><small><?= sanitizeInput($p['transaction_ref'] ?? '-') ?></small></td>
                        <td><small><?= formatDate($p['payment_date']) ?></small></td>
                        <td>
                            <a href="<?= BASE_URL ?>/accountant/payments.php?verify=<?= $p['id'] ?>" class="btn btn-sm btn-success"><i class="fas fa-check"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No pending payments</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
