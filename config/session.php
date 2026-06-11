<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
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
        die('Access denied. You do not have permission to view this page.');
    }
}

function regenerateSession(): void {
    session_regenerate_id(true);
}
