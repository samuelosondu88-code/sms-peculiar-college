<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$message = '';
$token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = sanitizeInput($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($token)) {
        $error = 'Invalid reset token.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (class_exists(\App\Services\AuthService::class) && !\App\Services\AuthService::meetsPolicy($password)['ok']) {
        $error = 'Password must include upper/lowercase letters, a number and a special character.';
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

            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 1");
            $stmt->execute([$reset['user_id']]);

            $message = 'Password reset successfully. <a href="login.php">Login now</a>.';
            logger('auth')->info('Password reset via token', ['user_id' => (int)$reset['user_id'], 'email' => $reset['email'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        } else {
            $error = 'Invalid or expired reset link. Please request a new one.';
        }
    }
} else {
    $token = sanitizeInput($_GET['token'] ?? '');
    if (empty($token)) {
        $error = 'No reset token provided.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        if (!$stmt->fetch()) {
            $error = 'Reset link is invalid or has expired. Please request a new one.';
            $token = '';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
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

        <?php if (!$message && !empty($token)): ?>
        <form method="POST">
            <?= getCsrfField() ?>
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
            <a href="login.php" class="small">&larr; Back to Login</a>
        </div>
        <?php elseif (!$message): ?>
        <div class="text-center mt-3">
            <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
        </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
