<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect('/index.php');
}

$error = '';
$loginMode = $_GET['mode'] ?? 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_email'])) {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? '1' : '0';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (isLoginThrottled($email, $ip)) {
        $error = 'Too many login attempts. Please try again in 5 minutes.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, password_hash, role, first_name, last_name, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && verifyPassword($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            if ($remember === '1') { $_SESSION['_remember'] = true; }
            regenerateSession();
            setSessionFingerprint();
            recordLoginAttempt($email, $ip, true);
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            logActivity($user['id'], 'login');
            session_write_close();
            redirect('/index.php');
        }
        recordLoginAttempt($email, $ip, false);
        $error = 'Invalid email or password, or account is inactive.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pin'])) {
    $admissionNo = sanitizeInput($_POST['admission_no'] ?? '');
    $pin = sanitizeInput($_POST['pin'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (empty($admissionNo) || empty($pin)) {
        $error = 'Please enter both admission number and PIN.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT s.id, s.user_id, u.first_name, u.last_name, u.role, u.status
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.admission_no = ? AND u.role = 'student' LIMIT 1");
        $stmt->execute([$admissionNo]);
        $student = $stmt->fetch();

        if (!$student) {
            $error = 'Invalid admission number.';
        } elseif ($student['status'] !== 'active') {
            $error = 'Account is inactive. Contact the administrator.';
        } else {
            $pinStmt = $db->prepare("SELECT * FROM student_pins WHERE student_id = ? AND pin = ? AND status = 'active' AND (expires_at IS NULL OR expires_at >= CURDATE()) LIMIT 1");
            $pinStmt->execute([$student['id'], $pin]);
            $pinRecord = $pinStmt->fetch();

            if ($pinRecord) {
                $db->prepare("UPDATE student_pins SET status = 'used', used_at = NOW(), attempts = attempts + 1 WHERE id = ?")->execute([$pinRecord['id']]);
                $db->prepare("INSERT INTO pin_login_log (student_id, pin_id, ip_address, success) VALUES (?, ?, ?, 1)")->execute([$student['id'], $pinRecord['id'], $ip]);

                $newPin = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $db->prepare("INSERT INTO student_pins (student_id, pin, generated_by) VALUES (?, ?, ?)")->execute([$student['id'], $newPin, $student['user_id']]);

                $_SESSION['user_id'] = (int)$student['user_id'];
                $_SESSION['role'] = 'student';
                $_SESSION['user_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['first_name'] = $student['first_name'];
                $_SESSION['last_name'] = $student['last_name'];
                regenerateSession();
                setSessionFingerprint();
                $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$student['user_id']]);
                session_write_close();
                redirect('/student/index.php');
            } else {
                if ($pinRecord === false) {
                    $stmt = $db->prepare("UPDATE student_pins SET attempts = attempts + 1 WHERE student_id = ? AND status = 'active'");
                    $stmt->execute([$student['id']]);
                    $failed = $db->prepare("SELECT attempts, max_attempts FROM student_pins WHERE student_id = ? AND status = 'active' LIMIT 1");
                    $failed->execute([$student['id']]);
                    $fData = $failed->fetch();
                    if ($fData && (int)$fData['attempts'] >= (int)$fData['max_attempts']) {
                        $db->prepare("UPDATE student_pins SET status = 'revoked' WHERE student_id = ? AND status = 'active'")->execute([$student['id']]);
                        $error = 'PIN has been revoked due to too many failed attempts. Contact the administrator.';
                    } else {
                        $remaining = (int)($fData['max_attempts'] ?? 5) - (int)($fData['attempts'] ?? 0);
                        $error = "Invalid PIN. $remaining attempt(s) remaining.";
                    }
                } else {
                    $error = 'Invalid or expired PIN.';
                }
                $db->prepare("INSERT INTO pin_login_log (student_id, ip_address, success) VALUES (?, ?, 0)")->execute([$student['id'], $ip]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SCHOOL_NAME ?></title>
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
            <p class="text-muted small mt-1">Sign in to your account</p>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning"><i class="fas fa-clock me-2"></i>Session expired due to inactivity.</div>
        <?php endif; ?>
        <?php if (isset($_GET['hijack'])): ?>
        <div class="alert alert-danger"><i class="fas fa-shield-alt me-2"></i>Session terminated for security reasons.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($loginMode === 'email'): ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text" style="background: var(--bg-light); border: 2px solid var(--border-color); border-right: none;"><i class="fas fa-envelope" style="color: var(--gold);"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus style="border-left: none;">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text" style="background: var(--bg-light); border: 2px solid var(--border-color); border-right: none;"><i class="fas fa-lock" style="color: var(--gold);"></i></span>
                    <input type="password" name="password" id="password-field" class="form-control" placeholder="Enter your password" required style="border-left: none;">
                    <button class="btn btn-outline-secondary toggle-password" type="button" onclick="togglePassword()" style="border: 2px solid var(--border-color); border-left: none;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label small" for="remember">Remember me</label>
            </div>
            <button type="submit" name="login_email" class="btn btn-gold w-100 py-2 fw-bold">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="?mode=pin" class="small" style="color: var(--primary); font-weight: 500;"><i class="fas fa-key me-1"></i>Student PIN Login</a>
        </div>
        <div class="text-center mt-2">
            <a href="forgot-password.php" class="small" style="color: var(--text-muted);">Forgot password?</a>
        </div>

        <?php else: ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Admission Number / Student ID</label>
                <div class="input-group">
                    <span class="input-group-text" style="background: var(--bg-light); border: 2px solid var(--border-color); border-right: none;"><i class="fas fa-id-card" style="color: var(--gold);"></i></span>
                    <input type="text" name="admission_no" class="form-control" placeholder="e.g. PIC-2025-001" required autofocus style="border-left: none;">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Secure PIN</label>
                <div class="input-group">
                    <span class="input-group-text" style="background: var(--bg-light); border: 2px solid var(--border-color); border-right: none;"><i class="fas fa-key" style="color: var(--gold);"></i></span>
                    <input type="password" name="pin" class="form-control" placeholder="Enter your PIN" required style="border-left: none;" maxlength="20">
                </div>
                <small class="text-muted">Use the PIN provided by the school administration.</small>
            </div>
            <button type="submit" name="login_pin" class="btn btn-gold w-100 py-2 fw-bold">
                <i class="fas fa-key me-2"></i>Login with PIN
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="?mode=email" class="small" style="color: var(--primary); font-weight: 500;"><i class="fas fa-envelope me-1"></i>Staff & Teacher Login</a>
        </div>
        <?php endif; ?>

        <div class="text-center mt-2">
            <a href="<?= BASE_URL ?>/public/index.php" class="small" style="color: var(--text-muted);">&larr; Back to Home</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script>
        function togglePassword() {
            const field = document.getElementById('password-field');
            const icon = event.currentTarget.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
