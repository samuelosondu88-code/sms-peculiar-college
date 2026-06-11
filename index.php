<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    switch ($role) {
        case 'admin': header('Location: admin/index.php'); break;
        case 'teacher': header('Location: teacher/index.php'); break;
        case 'student': header('Location: student/index.php'); break;
        case 'parent': header('Location: parent/index.php'); break;
        case 'accountant': header('Location: accountant/index.php'); break;
        default: header('Location: public/index.php');
    }
    exit;
}
header('Location: public/index.php');
exit;
