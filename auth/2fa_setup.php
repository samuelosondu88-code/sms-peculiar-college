<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Two-Factor Authentication';
$db = getDB();
$uid = (int)$_SESSION['user_id'];
$msg = '';
$msgType = 'success';

// Ensure 2FA record exists
$chk = $db->prepare("SELECT id FROM user_2fa WHERE user_id = ?");
$chk->execute([$uid]);
if (!$chk->fetch()) {
    $db->prepare("INSERT INTO user_2fa (user_id, is_enabled, method) VALUES (?, 0, 'email')")->execute([$uid]);
}

$row = $db->prepare("SELECT * FROM user_2fa WHERE user_id = ?");
$row->execute([$uid]);
$settings = $row->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'enable') {
        $db->prepare("UPDATE user_2fa SET is_enabled = 1, method = 'email' WHERE user_id = ?")->execute([$uid]);
        logActivity($uid, 'enable_2fa', 'user_2fa', $uid);
        $msg = 'Two-factor authentication enabled. An OTP will now be sent to your email at each login.';
    } elseif ($action === 'disable') {
        $db->prepare("UPDATE user_2fa SET is_enabled = 0 WHERE user_id = ?")->execute([$uid]);
        logActivity($uid, 'disable_2fa', 'user_2fa', $uid);
        $msg = 'Two-factor authentication disabled.';
    }

    $row->execute();
    $settings = $row->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card">
    <div class="card-body text-center py-5">
        <div class="mb-4">
            <i class="fas fa-<?= $settings['is_enabled'] ? 'check-circle text-success' : 'shield-alt text-muted' ?>" style="font-size:4rem;"></i>
        </div>
        <h5><?= $settings['is_enabled'] ? '2FA is Enabled' : '2FA is Disabled' ?></h5>
        <p class="text-muted">
            <?php if ($settings['is_enabled']): ?>
            You will receive a one-time code via email each time you sign in.
            <?php else: ?>
            Add an extra layer of security to your account. When enabled, you'll need a one-time code sent to your email in addition to your password.
            <?php endif; ?>
        </p>

        <?php if ($settings['is_enabled']): ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="disable">
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Disable two-factor authentication?')">
                <i class="fas fa-times me-1"></i>Disable 2FA
            </button>
        </form>
        <?php else: ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="action" value="enable">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check me-1"></i>Enable 2FA
            </button>
        </form>
        <?php endif; ?>

        <hr class="my-4">
        <div class="text-start small">
            <h6>How it works:</h6>
            <ol class="text-muted">
                <li>Enable 2FA using the button above.</li>
                <li>On your next login, after entering your password correctly, you'll be asked for a one-time code.</li>
                <li>Check your email — a 6-digit code will be sent to <?= sanitizeInput($_SESSION['user_name'] ?? 'your email') ?>.</li>
                <li>Enter the code to complete login.</li>
            </ol>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
