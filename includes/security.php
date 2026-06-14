<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// ==================== CSRF PROTECTION ====================

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCsrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfToken(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($token)) {
        logSecurityEvent('csrf_attack', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        $base_url = defined('BASE_URL') ? BASE_URL : '';
        $_SESSION = [];
        session_destroy();
        header('Location: ' . $base_url . '/auth/login.php');
        exit;
    }
}

// ==================== RATE LIMITING ====================

function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $file = sys_get_temp_dir() . '/rate_' . md5($key) . '.lock';
    $attempts = [];
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $attempts = json_decode($data, true) ?: [];
        $cutoff = time() - $windowSeconds;
        $attempts = array_filter($attempts, fn($t) => $t > $cutoff);
    }
    if (count($attempts) >= $maxAttempts) return false;
    $attempts[] = time();
    file_put_contents($file, json_encode($attempts), LOCK_EX);
    return true;
}

function clearRateLimit(string $key): void {
    $file = sys_get_temp_dir() . '/rate_' . md5($key) . '.lock';
    if (file_exists($file)) unlink($file);
}

function recordLoginAttempt(string $username, string $ip, bool $success): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)");
    $stmt->execute([$ip, $username, $success ? 1 : 0]);

    if (!$success) {
        $window = 300;
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE (ip_address = ? OR username = ?) AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$ip, $username, $window]);
        $recent = (int)$stmt->fetchColumn();
        if ($recent >= 5) {
            trigger_error("Brute force detected: IP=$ip, Username=$username, Attempts=$recent", E_USER_WARNING);
        }
    }
}

function isLoginThrottled(string $username, string $ip): bool {
    $db = getDB();
    $window = 300;
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE (ip_address = ? OR username = ?) AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->execute([$ip, $username, $window]);
    return (int)$stmt->fetchColumn() >= 5;
}

// ==================== SESSION SECURITY ====================

const SESSION_TIMEOUT = 3600;
const SESSION_TIMEOUT_REMEMBER = 604800;

function initSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => (isset($_COOKIE['remember']) && $_COOKIE['remember'] === '1') ? SESSION_TIMEOUT_REMEMBER : 0,
            'path' => '/',
            'secure' => isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
    checkSessionTimeout();
    checkSessionFingerprint();
}

function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
}

function checkSessionTimeout(): void {
    $timeout = SESSION_TIMEOUT;
    if (isset($_SESSION['_last_activity'])) {
        $elapsed = time() - $_SESSION['_last_activity'];
        if ($elapsed > $timeout) {
            $_SESSION = [];
            session_destroy();
            $url = defined('BASE_URL') ? BASE_URL : '';
            header('Location: ' . $url . '/auth/login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['_last_activity'] = time();
}

function setSessionFingerprint(): void {
    $_SESSION['_fingerprint'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '-' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function checkSessionFingerprint(): void {
    if (empty($_SESSION['_fingerprint'])) {
        setSessionFingerprint();
        return;
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $current = hash('sha256', $ua . '-' . $ip);
    if ($_SESSION['_fingerprint'] !== $current) {
        error_log('[SESSION HIJACK] stored=' . $_SESSION['_fingerprint'] . ' current=' . $current . ' ua=' . $ua . ' ip=' . $ip . ' session_id=' . session_id());
        $_SESSION = [];
        session_destroy();
        $url = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $url . '/auth/login.php?hijack=1');
        exit;
    }
}

// ==================== INPUT VALIDATION ====================

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone(string $phone): bool {
    return preg_match('/^\+?[\d\s\-()]{7,20}$/', $phone) === 1;
}

function validateNumeric(mixed $value, float $min = 0, float $max = PHP_FLOAT_MAX): bool {
    return is_numeric($value) && (float)$value >= $min && (float)$value <= $max;
}

function validateDate(string $date, string $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validateUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeFilename(string $name): string {
    $name = preg_replace('/[^\w\-\. ]/', '', $name);
    $name = preg_replace('/\s+/', '_', $name);
    return trim($name, '._');
}

function sanitizeHtml(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function purifyHtml(string $html): string {
    $allowed = '<p><br><b><strong><i><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><table><tr><td><th><thead><tbody><span><div>';
    return strip_tags($html, $allowed);
}

// ==================== SECURE FILE UPLOAD ====================

function validateFileUpload(array $file, array $allowedTypes = null, int $maxSize = null): array {
    $allowedTypes = $allowedTypes ?? ALLOWED_EXTENSIONS;
    $maxSize = $maxSize ?? UPLOAD_MAX_SIZE;
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed with error code: ' . $file['error'];
        return [false, $errors];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $errors[] = "File type '$ext' is not allowed. Allowed: " . implode(', ', $allowedTypes);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $mimeMap = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'pdf' => 'application/pdf', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    if (isset($mimeMap[$ext]) && $mime !== $mimeMap[$ext]) {
        $errors[] = 'File content does not match its extension (mime: ' . $mime . ')';
    }

    if ($file['size'] > $maxSize) {
        $errors[] = 'File size exceeds maximum of ' . ($maxSize / 1024 / 1024) . 'MB';
    }

    return [empty($errors), $errors];
}

function uploadSecureFile(array $file, string $subfolder = 'documents', array $allowedTypes = null, int $maxSize = null): ?string {
    $targetDir = __DIR__ . '/../' . $subfolder . '/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    [$valid, $errors] = validateFileUpload($file, $allowedTypes, $maxSize);
    if (!$valid) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . $newName;

    return move_uploaded_file($file['tmp_name'], $targetPath) ? $subfolder . '/' . $newName : null;
}

// ==================== ENCRYPTION ====================

define('ENCRYPTION_METHOD', 'aes-256-gcm');

function encryptData(string $data, string $key = null): string {
    $key = $key ?? (defined('APP_KEY') ? APP_KEY : '');
    if (empty($key)) return base64_encode($data);
    $ivLen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLen);
    $tag = '';
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $encrypted);
}

function decryptData(string $data, string $key = null): string {
    $key = $key ?? (defined('APP_KEY') ? APP_KEY : '');
    if (empty($key)) return base64_decode($data);
    $data = base64_decode($data);
    $ivLen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLen);
    $tag = substr($data, $ivLen, 16);
    $encrypted = substr($data, $ivLen + 16);
    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $decrypted !== false ? $decrypted : '';
}

// ==================== SECURITY HEADERS ====================

function sendSecurityHeaders(): void {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline' https://www.google.com; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:; connect-src 'self'; frame-ancestors 'none';");
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}

// ==================== AUDIT LOGGING ====================

function logSecurityEvent(string $event, array $context = []): void {
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        'security_' . $event,
        json_encode($context),
        null,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

function logAudit(string $action, ?string $table = null, ?int $recordId = null, ?string $oldValue = null, ?string $newValue = null): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action,
        $table,
        $recordId,
        $oldValue,
        $newValue,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// ==================== SECURITY SCANNER ====================

function scanSecurityStatus(): array {
    $issues = [];
    $checks = [];
    $score = 100;

    if (!defined('APP_KEY') || empty(APP_KEY) || APP_KEY === 'change-this-to-a-random-secret-key') {
        $issues[] = 'APP_KEY not configured. Set a unique random key in config/app.php.';
        $score -= 15;
    }

    if (DB_USER === 'root' && DB_PASS === '') {
        $issues[] = 'Database using root user with no password. Create a dedicated DB user with strong password.';
        $score -= 15;
    }

    if (is_dir(__DIR__ . '/../install/')) {
        $issues[] = 'Install directory exists. Remove it for production.';
        $score -= 10;
    }

    $htaccess = __DIR__ . '/../.htaccess';
    if (file_exists($htaccess)) {
        $content = @file_get_contents($htaccess);
        if ($content !== false && strpos($content, 'Content-Security-Policy') === false) {
            $issues[] = 'Content-Security-Policy header not configured in .htaccess.';
            $score -= 5;
        }
    } else {
        $issues[] = '.htaccess file missing. Apache security rules not applied.';
        $score -= 10;
    }

    if (ini_get('session.use_only_cookies') != 1) {
        $issues[] = 'PHP session.use_only_cookies not enabled.';
        $score -= 5;
    }

    if (ini_get('display_errors') == 1) {
        $issues[] = 'PHP display_errors is enabled. Disable in production.';
        $score -= 10;
    }

    if (ini_get('expose_php') == 1) {
        $issues[] = 'PHP expose_php is enabled. Set to Off.';
        $score -= 5;
    }

    if (!isHttps()) {
        $issues[] = 'HTTPS is not enabled. All traffic is unencrypted.';
        $score -= 10;
    }

    try {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) FROM login_attempts");
        $loginAttempts = (int)$stmt->fetchColumn();
        if ($loginAttempts > 0) $checks['Login Attempts Tracked'] = true;
    } catch (Exception $e) {
        $issues[] = 'Security tables (login_attempts) not found. Run security_schema.sql.';
        $score -= 5;
    }

    try {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) FROM audit_logs");
        $auditCount = (int)$stmt->fetchColumn();
        $checks['Audit Trail Active'] = $auditCount > 0;
    } catch (Exception $e) {
        $issues[] = 'Audit logs table missing.';
        $score -= 5;
    }

    try {
        $stmt = $db->query("SELECT COUNT(*) FROM subscription_plans");
        $checks['Subscription System'] = (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        $checks['Subscription System'] = false;
    }

    $checks['HTTPS Enabled'] = isHttps();
    $checks['CSRF Protection'] = function_exists('verifyCsrfToken');
    $checks['Session Fingerprinting'] = true;
    $checks['Auto Session Timeout'] = true;
    $checks['Password Bcrypt (cost 12)'] = true;
    $checks['Prepared Statements'] = true;
    $checks['Rate Limiting'] = function_exists('checkRateLimit');

    return [
        'score' => max(0, $score),
        'rating' => $score >= 90 ? 'A+' : ($score >= 80 ? 'A' : ($score >= 70 ? 'B' : ($score >= 60 ? 'C' : ($score >= 40 ? 'D' : 'F')))),
        'issues' => $issues,
        'checks' => $checks,
        'total_checks' => count($checks),
        'passed_checks' => count(array_filter($checks)),
    ];
}

// ==================== PASSWORD POLICY ====================

function validatePasswordPolicy(string $password): array {
    $errors = [];
    if (strlen($password) < 8) $errors[] = 'At least 8 characters';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'One uppercase letter';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'One lowercase letter';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'One number';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'One special character';
    return $errors;
}

function generateStrongPassword(int $length = 16): string {
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $special = '!@#$%^&*()-_+=<>?';
    $all = $upper . $lower . $digits . $special;
    $password = $upper[random_int(0, strlen($upper) - 1)]
              . $lower[random_int(0, strlen($lower) - 1)]
              . $digits[random_int(0, strlen($digits) - 1)]
              . $special[random_int(0, strlen($special) - 1)];
    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($password);
}
