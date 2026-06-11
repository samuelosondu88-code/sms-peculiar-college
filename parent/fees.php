<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Pay Fees';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';

$stmt = $db->prepare("SELECT p.id FROM parents p WHERE p.user_id = ?");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

$children = [];
if ($parent) {
    $children = $db->prepare("SELECT s.id, u.first_name, u.last_name FROM student_parents sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE sp.parent_id = ?");
    $children->execute([$parent['id']]);
    $children = $children->fetchAll();
}

$studentId = (int)($_GET['student_id'] ?? ($children[0]['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $feeId = (int)($_POST['fee_id'] ?? 0);
    $amountPaid = (float)($_POST['amount_paid'] ?? 0);
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? 'card');
    $transactionRef = sanitizeInput($_POST['transaction_ref'] ?? generateReference('TXN'));
    $receiptNo = generateReceiptNo();

    $proofPath = null;
    if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $proofPath = uploadFile($_FILES['proof'], 'documents/receipts');
    }

    if ($feeId && $amountPaid > 0) {
        $stmt = $db->prepare("INSERT INTO payments (fee_id, amount_paid, payment_method, transaction_ref, receipt_no, payment_date, status, proof_document) VALUES (?, ?, ?, ?, ?, CURDATE(), 'pending', ?)");
        $stmt->execute([$feeId, $amountPaid, $paymentMethod, $transactionRef, $receiptNo, $proofPath]);
        $msg = 'Payment submitted for verification. Your reference: ' . $transactionRef;
    }
}

$fees = [];
if ($studentId) {
    $fees = $db->prepare("SELECT f.id, f.total_amount, f.paid_amount, f.balance, f.status, fs.fee_name, t.term_name FROM fees f JOIN fee_structure fs ON f.fee_structure_id = fs.id LEFT JOIN terms t ON fs.term_id = t.id WHERE f.student_id = ? AND f.status IN ('unpaid', 'partial') ORDER BY f.due_date");
    $fees->execute([$studentId]);
    $fees = $fees->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-money-bill me-2"></i>Pay Fees</h4>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<form method="GET" class="row g-3 mb-4">
    <?php if (!empty($children)): ?>
    <div class="col-md-4">
        <label class="form-label">Child</label>
        <select name="student_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $studentId === $c['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($c['first_name'] . ' ' . $c['last_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</form>

<?php if ($studentId): ?>
<div class="card mb-4">
    <div class="card-header">Payment Methods</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                        <h6 class="fw-bold">Pay Online with Card</h6>
                        <p class="small text-muted">Secure payment via Paystack/Flutterwave</p>
                        <button class="btn btn-primary" onclick="alert('Payment gateway integration will be connected here.')">Pay Now</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <i class="fas fa-university fa-2x text-primary mb-2"></i>
                        <h6 class="fw-bold">Bank Transfer / Deposit</h6>
                        <p class="small text-muted">Submit proof of payment for verification</p>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bankTransferModal">Submit Proof</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Outstanding Fees</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Fee</th><th>Term</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($fees as $f): ?>
                    <tr>
                        <td><?= sanitizeInput($f['fee_name']) ?></td>
                        <td><?= sanitizeInput($f['term_name'] ?? '-') ?></td>
                        <td><?= formatCurrency($f['total_amount']) ?></td>
                        <td><?= formatCurrency($f['paid_amount']) ?></td>
                        <td><strong><?= formatCurrency($f['balance']) ?></strong></td>
                        <td><?= getStatusBadge($f['status']) ?></td>
                        <td><button class="btn btn-sm btn-primary" onclick="alert('Pay for this fee via card. Amount: <?= $f['balance'] ?>')">Pay</button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fees)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">All fees are paid.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="bankTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Bank Transfer Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6 class="fw-bold">School Bank Details</h6>
                        <p class="mb-1">Bank: First Bank of Nigeria</p>
                        <p class="mb-1">Account Name: Peculiar International College</p>
                        <p class="mb-1">Account No: <strong>2034567890</strong></p>
                        <p class="mb-0">Sort Code: 011</p>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">Select Fee to Pay</label>
                        <select name="fee_id" class="form-select" required>
                            <?php foreach ($fees as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= sanitizeInput($f['fee_name']) ?> - ₦<?= number_format($f['balance'], 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount Paid</label>
                        <input type="number" name="amount_paid" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" name="transaction_ref" class="form-control" placeholder="e.g., Teller No or Ref" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Payment Proof (Receipt/Screenshot)</label>
                        <input type="file" name="proof" class="form-control" accept="image/*,.pdf">
                    </div>
                    <input type="hidden" name="payment_method" value="transfer">
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="submit_payment" value="1">
                    <button type="submit" class="btn btn-primary">Submit for Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
