<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Fee Management';
$db = getDB();

$summary = [
    'total_collected' => $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE status = 'approved'")->fetchColumn(),
    'outstanding' => $db->query("SELECT COALESCE(SUM(balance), 0) FROM fees WHERE status IN ('unpaid','partial')")->fetchColumn(),
    'pending_verification' => $db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn(),
    'total_students_with_fees' => $db->query("SELECT COUNT(DISTINCT student_id) FROM fees")->fetchColumn(),
];

$recentTransactions = $db->query("SELECT p.*, u.first_name, u.last_name, fs.fee_name FROM payments p JOIN fees f ON p.fee_id = f.id JOIN fee_structure fs ON f.fee_structure_id = fs.id JOIN students s ON f.student_id = s.id JOIN users u ON s.user_id = u.id ORDER BY p.created_at DESC LIMIT 10")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-money-bill me-2"></i>Fee Management</h4>
    <a href="<?= BASE_URL ?>/accountant/fees.php" class="btn btn-primary"><i class="fas fa-cog me-1"></i>Manage Fee Structure</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card stat-success"><i class="fas fa-money-bill-wave stat-icon"></i><div class="stat-value"><?= formatCurrency($summary['total_collected']) ?></div><div class="stat-label">Total Collected</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-danger"><i class="fas fa-exclamation-triangle stat-icon"></i><div class="stat-value"><?= formatCurrency($summary['outstanding']) ?></div><div class="stat-label">Outstanding</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-warning"><i class="fas fa-clock stat-icon"></i><div class="stat-value"><?= $summary['pending_verification'] ?></div><div class="stat-label">Pending Verifications</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-info"><i class="fas fa-users stat-icon"></i><div class="stat-value"><?= $summary['total_students_with_fees'] ?></div><div class="stat-label">Students with Fees</div></div></div>
</div>

<div class="card">
    <div class="card-header">Recent Transactions</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Student</th><th>Fee</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recentTransactions as $t): ?>
                <tr>
                    <td><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></td>
                    <td><?= sanitizeInput($t['fee_name']) ?></td>
                    <td><strong><?= formatCurrency($t['amount_paid']) ?></strong></td>
                    <td><?= ucfirst($t['payment_method']) ?></td>
                    <td><?= formatDate($t['payment_date']) ?></td>
                    <td><?= getStatusBadge($t['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
