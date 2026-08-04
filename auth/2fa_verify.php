<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Must have a pending 2FA session
if (!isset($_SESSION['_2fa_pending']) || !$_SESSION['_2fa_pending'] || !isset($_SESSION['_2fa_user_id'])) {
    redirect('/auth/login.php');
}

$db = getDB();
$error = '';
$uid = (int)$_SESSION['_2fa_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^\d{6}$/', $otp)) {
        $error = 'Please enter a valid 6-digit code.';
    } else {
        $stmt = $db->prepare("SELECT id FROM otp_codes WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([$uid, $otp]);
        $match = $stmt->fetch();

        if ($match) {
            $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?")->execute([$match['id']]);

            // Complete login
            $userStmt = $db->prepare("SELECT id, role, first_name, last_name FROM users WHERE id = ?");
            $userStmt->execute([$uid]);
            $user = $userStmt->fetch();

            $_SESSION['user_id'] = $uid;
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            unset($_SESSION['_2fa_pending'], $_SESSION['_2fa_user_id'], $_SESSION['_2fa_email']);

            regenerateSession();
            setSessionFingerprint();
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$uid]);
            logActivity($uid, 'login_2fa');
            logger('auth')->info('2FA verified, login complete', ['user_id' => $uid, 'role' => $user['role'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
            session_write_close();
            redirect('/index.php');
        } else {
            $error = 'Invalid or expired code. Request a new one.';
            logger('auth')->warning('2FA verification failed', ['user_id' => $uid, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
        }
    }
}

// Resend
if (isset($_GET['resend'])) {
    $stmt = $db->prepare("SELECT email, first_name FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();

    if ($u) {
        // Invalidate old codes
        $db->prepare("UPDATE otp_codes SET used = 1 WHERE user_id = ? AND used = 0")->execute([$uid]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $db->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)")->execute([$uid, $otp, $expires]);

        $body = "<h3>Your Verification Code</h3><p>Use the following code to complete your login:</p>
                <h2 style='background:#f0f0f0;padding:15px;text-align:center;letter-spacing:8px;font-size:32px;'>$otp</h2>
                <p>This code expires in 10 minutes.</p><p>If you did not request this, ignore this email.</p>";
        sendEmail($u['email'], 'Your 2FA Verification Code', $body);
    }
    redirect('/auth/2fa_verify.php');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="icon" type="image/jpeg" href="<?= BASE_URL ?>/assets/images/logo.jpg">
</head>
<body class="login-page">
    <div class="login-card animate-fade-up">
        <div class="school-logo">
            <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="<?= SCHOOL_NAME ?>" style="max-width: 100px; max-height: 100px; border-radius: 16px;">
            <div class="school-name mt-3"><?= SCHOOL_NAME ?></div>
            <p class="text-muted small mt-1">Two-Factor Authentication</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
        <?php endif; ?>

        <div class="alert alert-info">
            <i class="fas fa-envelope me-2"></i>A verification code has been sent to your email.
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Verification Code</label>
                <input type="text" name="otp" class="form-control form-control-lg text-center" placeholder="000000" required maxlength="6" pattern="\d{6}" inputmode="numeric" autofocus style="font-size:1.8rem;letter-spacing:8px;">
            </div>
            <button type="submit" name="verify_otp" class="btn btn-gold w-100 py-2 fw-bold">
                <i class="fas fa-check me-2"></i>Verify & Sign In
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="?resend=1" class="small">Didn't receive the code? <strong>Resend</strong></a>
        </div>
        <div class="text-center mt-2">
            <a href="auth/logout.php" class="small text-danger">Cancel & Sign Out</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
