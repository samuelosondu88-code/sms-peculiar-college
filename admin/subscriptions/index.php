<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Subscription Management';
$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $planId = (int)$_POST['plan_id'];
    $billing = sanitizeInput($_POST['billing_cycle'] ?? 'monthly');
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if ($plan) {
        $amount = $billing === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly'];
        $start = date('Y-m-d');
        $end = $billing === 'yearly' ? date('Y-m-d', strtotime('+1 year')) : date('Y-m-d', strtotime('+1 month'));
        $stmt = $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE status = 'active'");
        $stmt->execute();
        $stmt = $db->prepare("INSERT INTO subscriptions (school_name, school_email, plan_id, billing_cycle, amount, start_date, end_date, status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'online')");
        $stmt->execute([SCHOOL_NAME, SCHOOL_EMAIL, $planId, $billing, $amount, $start, $end]);
        $subId = $db->lastInsertId();
        $invNo = generateReceiptNo();
        $db->prepare("INSERT INTO payments (subscription_id, invoice_no, amount, payment_method, payment_status, paid_at) VALUES (?, ?, ?, 'online', 'completed', NOW())")->execute([$subId, $invNo, $amount]);
        $msg = "Subscribed to {$plan['name']} plan successfully.";
        logAudit('subscription_create', 'subscriptions', $subId, null, "Plan: {$plan['name']}, Billing: $billing");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $subId = (int)$_POST['sub_id'];
    $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?")->execute([$subId]);
    $msg = 'Subscription cancelled.';
    logAudit('subscription_cancel', 'subscriptions', $subId);
}

$plans = $db->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly")->fetchAll();
$activeSub = $db->query("SELECT s.*, sp.name as plan_name, sp.features FROM subscriptions s JOIN subscription_plans sp ON s.plan_id = sp.id WHERE s.status IN ('active','trial') ORDER BY s.created_at DESC LIMIT 1")->fetch();
$allSubs = $db->query("SELECT s.*, sp.name as plan_name FROM subscriptions s JOIN subscription_plans sp ON s.plan_id = sp.id ORDER BY s.created_at DESC LIMIT 10")->fetchAll();
$payments = $db->query("SELECT p.*, sp.name as plan_name FROM payments p JOIN subscriptions s ON p.subscription_id = s.id JOIN subscription_plans sp ON s.plan_id = sp.id ORDER BY p.created_at DESC LIMIT 20")->fetchAll();

$features = [];
if ($activeSub && $activeSub['features']) {
    $features = json_decode($activeSub['features'], true) ?: [];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-credit-card me-2"></i>Subscription Management</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<?php if ($activeSub): ?>
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white"><i class="fas fa-check-circle me-2"></i>Current Subscription</div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Plan:</strong> <?= sanitizeInput($activeSub['plan_name']) ?></div>
            <div class="col-md-3"><strong>Status:</strong> <?= getStatusBadge($activeSub['status']) ?></div>
            <div class="col-md-3"><strong>Start:</strong> <?= formatDate($activeSub['start_date']) ?></div>
            <div class="col-md-3"><strong>Expires:</strong> <?= formatDate($activeSub['end_date']) ?>
                <?php if (strtotime($activeSub['end_date']) < strtotime('+30 days')): ?>
                <span class="badge bg-warning text-dark ms-1">Expiring soon</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($features)): ?>
        <div class="mt-3">
            <strong>Features:</strong>
            <?php foreach ($features as $f): ?>
            <span class="badge bg-info me-1"><?= sanitizeInput($f) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($plans as $plan): ?>
    <div class="col-md-4">
        <div class="card h-100 <?= ($activeSub['plan_id'] ?? 0) === $plan['id'] ? 'border-warning' : '' ?>">
            <div class="card-body text-center">
                <h5 class="fw-bold"><?= sanitizeInput($plan['name']) ?></h5>
                <h2 class="text-primary fw-bold">
                    ₦<?= number_format($plan['price_monthly'], 2) ?>
                    <small class="text-muted fs-6">/mo</small>
                </h2>
                <p class="text-muted small mb-3">₦<?= number_format($plan['price_yearly'], 2) ?>/year</p>
                <p class="small"><?= sanitizeInput($plan['description']) ?></p>
                <ul class="list-unstyled small text-start">
                    <?php $planFeatures = json_decode($plan['features'], true) ?: []; ?>
                    <?php foreach ($planFeatures as $f): ?>
                    <li><i class="fas fa-check text-success me-1"></i><?= sanitizeInput($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (($activeSub['plan_id'] ?? 0) === $plan['id']): ?>
                <span class="badge bg-warning text-dark fs-6">Current Plan</span>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <div class="btn-group w-100 mb-2">
                        <button type="submit" name="billing_cycle" value="monthly" class="btn btn-outline-primary">Monthly</button>
                        <button type="submit" name="billing_cycle" value="yearly" class="btn btn-primary">Yearly</button>
                    </div>
                    <button type="submit" name="subscribe" class="btn btn-primary w-100">Subscribe</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Subscription History</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Plan</th><th>Status</th><th>Start</th><th>End</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($allSubs as $sub): ?>
                            <tr>
                                <td><?= sanitizeInput($sub['plan_name']) ?></td>
                                <td><?= getStatusBadge($sub['status']) ?></td>
                                <td><?= formatDate($sub['start_date']) ?></td>
                                <td><?= formatDate($sub['end_date']) ?></td>
                                <td>
                                    <?php if ($sub['status'] === 'active'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Cancel subscription?')">
                                        <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                                        <button type="submit" name="cancel" class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-receipt me-2"></i>Payment History</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Invoice</th><th>Plan</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><code><?= sanitizeInput($p['invoice_no']) ?></code></td>
                                <td><?= sanitizeInput($p['plan_name'] ?? '-') ?></td>
                                <td><?= formatCurrency($p['amount']) ?></td>
                                <td><?= getStatusBadge($p['payment_status']) ?></td>
                                <td><?= formatDate($p['paid_at'] ?? $p['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payments)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No payments recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
