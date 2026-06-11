<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$message = '';

$token = sanitizeInput($_GET['token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = sanitizeInput($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $error = 'Invalid reset token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT pr.user_id, u.email FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset) {
            $hash = generatePasswordHash($password);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $reset['user_id']]);

            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            $message = 'Password reset successfully. <a href="/auth/login.php">Login now</a>.';
        } else {
            $error = 'Invalid or expired reset token.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="school-logo">
            <i class="fas fa-key"></i>
            <div class="school-name mt-2">Reset Password</div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?= sanitizeInput($token) ?>">
            <div class="mb-3">
                <label class="form-label fw500">New Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required minlength="8">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw500">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-check"></i></span>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required minlength="8">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                <i class="fas fa-save me-2"></i>Reset Password
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="/auth/login.php" class="small">&larr; Back to Login</a>
        </div>
        <?php endif; ?>
    </div>
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
