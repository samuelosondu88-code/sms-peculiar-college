<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/functions.php';

// Simulate a logged-in student
session_id('test');
session_start();
$_SESSION['user_id'] = 4;
$_SESSION['role'] = 'student';
$_SESSION['user_name'] = 'Chidi Okonkwo';

$_SERVER['PHP_SELF'] = '/sms-peculiar-college/student/index.php';

// Manually render the sidebar to check for errors
$role = $_SESSION['role'];
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

echo "Role: $role\n";
echo "Current Dir: $currentDir\n";
echo "Current Page: $currentPage\n\n";

// Check the student section of sidebar
ob_start();
include __DIR__ . '/includes/sidebar.php';
$output = ob_get_clean();

if (strpos($output, 'CBT Exams') !== false) {
    echo "SUCCESS: 'CBT Exams' link found in rendered sidebar\n";
    // Extract the link
    preg_match('/<a[^>]*href="[^"]*student\/cbt[^"]*"[^>]*>.*?CBT Exams.*?<\/a>/s', $output, $m);
    if (!empty($m[0])) {
        echo "Link HTML: " . htmlspecialchars($m[0]) . "\n";
    }
} else {
    echo "ERROR: 'CBT Exams' NOT found in rendered sidebar!\n";
    echo "Sidebar output (first 2000 chars):\n" . substr($output, 0, 2000) . "\n";
}
