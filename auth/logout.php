<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout');
    logger('auth')->info('Logout', ['user_id' => (int)$_SESSION['user_id'], 'role' => $_SESSION['role'] ?? null, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
}

$_SESSION = [];
session_destroy();

$params = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);

redirect('/auth/login.php?logged_out=1');
