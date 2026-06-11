<?php
require_once __DIR__ . '/../config/session.php';
requireRole('accountant');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Payment Management';
$db = getDB();
$msg = '';

if (isset($_GET['verify'])) {
    $paymentId = (int)$_GET['verify'];
    $action = sanitizeInput($_GET['action'] ?? 'approved');
    $stmt = $db->prepare("UPDATE payments SET status = ?, verified_by = ? WHERE id = ?");
    $stmt->execute([$action, $_SESSION['user_id'], $paymentId]);

    $stmt = $db->prepare("SELECT fee_id, amount_paid FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    $pay = $stmt->fetch();

    if ($pay && $action === 'approved') {
        $stmt = $db->prepare("UPDATE fees SET paid_amount = paid_amount + ?, balance = total_amount - paid_amount, status = CASE WHEN balance <= 0 THEN 'paid' ELSE 'partial' END WHERE id = ?");
        $stmt->execute([$pay['amount_paid'], $pay['fee_id']]);
    }
    $msg = "Payment {$action} successfully.";
    header("Location: /accountant/payments.php?msg=" . urlencode($msg));
    exit;
}

$payments = $db->query("SELECT p.*, u.first_name, u.last_name, fs.fee_name FROM payments p JOIN fees f ON p.fee_id = f.id JOIN fee_structure fs ON f.fee_structure_id = fs.id JOIN students s ON f.student_id = s.id JOIN users u ON s.user_id = u.id ORDER BY p.created_at DESC LIMIT 50")->fetchAll();
$msg = sanitizeInput($_GET['msg'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-credit-card me-2"></i>Payment Management</h4>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 datatable">
                <thead><tr><th>Student</th><th>Fee</th><th>Amount</th><th>Method</th><th>Reference</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= sanitizeInput($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= sanitizeInput($p['fee_name']) ?></td>
                        <td><strong><?= formatCurrency($p['amount_paid']) ?></strong></td>
                        <td><span class="badge bg-info text-dark"><?= ucfirst($p['payment_method']) ?></span></td>
                        <td><small><?= sanitizeInput($p['transaction_ref'] ?? '-') ?></small></td>
                        <td><?= formatDate($p['payment_date']) ?></td>
                        <td><?= getStatusBadge($p['status']) ?></td>
                        <td>
                            <?php if ($p['status'] === 'pending'): ?>
                            <a href="?verify=<?= $p['id'] ?>&action=approved" class="btn btn-sm btn-success" onclick="return confirm('Approve this payment?')"><i class="fas fa-check"></i></a>
                            <a href="?verify=<?= $p['id'] ?>&action=rejected" class="btn btn-sm btn-danger" onclick="return confirm('Reject this payment?')"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                            <?php if ($p['proof_document']): ?>
                            <a href="/<?= $p['proof_document'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-file"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?><tr><td colspan="8" class="text-center text-muted py-3">No payments.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
