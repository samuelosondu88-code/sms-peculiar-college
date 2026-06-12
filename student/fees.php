<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Fees';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT s.id FROM students s WHERE s.user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

$fees = [];
if ($student) {
    $fees = $db->prepare("
        SELECT f.*, fs.fee_name, fs.amount as original_amount, t.term_name
        FROM fees f
        JOIN fee_structure fs ON f.fee_structure_id = fs.id
        LEFT JOIN terms t ON fs.term_id = t.id
        WHERE f.student_id = ?
        ORDER BY f.due_date DESC
    ");
    $fees->execute([$student['id']]);
    $fees = $fees->fetchAll();
}

$totalDue = 0;
$totalPaid = 0;
$totalBalance = 0;
foreach ($fees as $f) {
    $totalDue += $f['total_amount'];
    $totalPaid += $f['paid_amount'];
    $totalBalance += $f['balance'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-money-bill me-2"></i>My Fees</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-primary">
            <i class="fas fa-calculator stat-icon"></i>
            <div class="stat-value"><?= formatCurrency($totalDue) ?></div>
            <div class="stat-label">Total Due</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <i class="fas fa-check stat-icon"></i>
            <div class="stat-value"><?= formatCurrency($totalPaid) ?></div>
            <div class="stat-label">Total Paid</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card <?= $totalBalance > 0 ? 'stat-danger' : 'stat-success' ?>">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= formatCurrency($totalBalance) ?></div>
            <div class="stat-label">Outstanding Balance</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Fee Breakdown</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fee Name</th>
                        <th>Term</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fees as $f): ?>
                    <tr>
                        <td><?= sanitizeInput($f['fee_name']) ?></td>
                        <td><?= sanitizeInput($f['term_name'] ?? '-') ?></td>
                        <td><?= formatCurrency($f['total_amount']) ?></td>
                        <td><?= formatCurrency($f['paid_amount']) ?></td>
                        <td><strong><?= formatCurrency($f['balance']) ?></strong></td>
                        <td><?= getStatusBadge($f['status']) ?></td>
                        <td><?= $f['due_date'] ? formatDate($f['due_date']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fees)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No fee records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
