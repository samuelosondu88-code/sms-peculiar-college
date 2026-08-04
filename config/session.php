<?php
// Bootstrap the modular app (env, autoloader, helpers, logger) and register the
// central error/exception handler before any session work runs.
require_once __DIR__ . '/../app/Config/bootstrap.php';
App\Core\ErrorHandler::register();

require_once __DIR__ . '/../includes/security.php';

initSecureSession();
sendSecurityHeaders();

// ── Maintenance mode ────────────────────────────────────────────────────────
// When enabled, non-admin traffic is served the maintenance page. An
// authenticated admin (or a valid `?down_for_maintenance=` bypass token) is
// allowed through so the site can be taken down and restored remotely.
if (is_maintenance_mode()) {
    $bypassOk = (isset($_GET['down_for_maintenance']) && is_string($_GET['down_for_maintenance'])
        && hash_equals(maintenance_bypass_token(), $_GET['down_for_maintenance']))
        || (($_SESSION['role'] ?? '') === 'admin');
    if (!$bypassOk) {
        if (!headers_sent()) {
            http_response_code(503);
        }
        require __DIR__ . '/../maintenance.php';
        exit;
    }
}

// Auto-verify CSRF for all POST requests (except login/logout)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $skipPaths = ['auth/login.php', 'auth/logout.php'];
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $skip = false;
    foreach ($skipPaths as $p) {
        if (strpos($scriptPath, $p) !== false) { $skip = true; break; }
    }
    if (!$skip) {
        requireCsrfToken();
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $base_url = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base_url . '/auth/login.php');
        exit;
    }
}

function hasRole(string ...$roles): bool {
    return isLoggedIn() && in_array($_SESSION['role'], $roles);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        http_response_code(403);
        require __DIR__ . '/../error-403.php';
        exit;
    }
}

function regenerateSession(): void {
    $oldData = $_SESSION;
    session_destroy();
    initSecureSession();
    session_regenerate_id(true);
    $_SESSION = $oldData;
    $_SESSION['_last_activity'] = time();
}
