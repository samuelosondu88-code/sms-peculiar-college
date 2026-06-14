<?php
require_once __DIR__ . '/../includes/security.php';

initSecureSession();

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
