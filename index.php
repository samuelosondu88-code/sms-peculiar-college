<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': redirect('/admin/index.php'); break;
        case 'teacher': redirect('/teacher/index.php'); break;
        case 'student': redirect('/student/index.php'); break;
        case 'parent': redirect('/parent/index.php'); break;
        case 'accountant': redirect('/accountant/index.php'); break;
        default: redirect('/public/index.php');
    }
}
redirect('/public/index.php');
