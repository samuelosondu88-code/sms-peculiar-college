<?php
/**
 * One-time setup script: creates missing database tables.
 * DELETE THIS FILE after running!
 * CLI-only: it performs DDL and must never be reachable over HTTP.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("Forbidden: setup.php may only be run from the command line.\n");
}
require __DIR__ . '/config/env.php';

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'sms_peculiar_college';
$dbUser = getenv('DB_USER') ?: 'peculiar_user';
$dbPass = getenv('DB_PASS') ?: '';

if ($dbPass === '') {
    die("Database password not configured. Set DB_PASS in .env before running setup.\n");
}

header('Content-Type: text/plain');

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    echo "Database connected.\n\n";
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage() . "\n\nMake sure MySQL is running via XAMPP Control Panel.\n");
}

// Detect available storage engine
$engine = 'InnoDB';
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS _engine_test (id INT) ENGINE=InnoDB");
    $pdo->query("DROP TABLE _engine_test");
    echo "Using ENGINE=InnoDB\n";
} catch (Exception $e) {
    $engine = 'MyISAM';
    echo "InnoDB unavailable, using ENGINE=MyISAM\n";
}

$tables = [
    'user_2fa' => "CREATE TABLE IF NOT EXISTS user_2fa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        is_enabled TINYINT(1) DEFAULT 0,
        method ENUM('email','app') DEFAULT 'email',
        secret VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=$engine",
    
    'otp_codes' => "CREATE TABLE IF NOT EXISTS otp_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=$engine",
    
    'staff_leave' => "CREATE TABLE IF NOT EXISTS staff_leave (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        leave_type ENUM('annual','sick','personal','maternity','paternity','study','other') NOT NULL DEFAULT 'annual',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
        approved_by INT,
        approved_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=$engine",
    
    'staff_documents' => "CREATE TABLE IF NOT EXISTS staff_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        doc_name VARCHAR(200) NOT NULL,
        doc_type VARCHAR(50) DEFAULT 'other',
        file_path VARCHAR(255) NOT NULL,
        notes TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=$engine",
];

$allOk = true;
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "  [OK] $name\n";
    } catch (Exception $e) {
        echo "  [FAIL] $name: " . $e->getMessage() . "\n";
        $allOk = false;
    }
}

echo "\n";
if ($allOk) {
    echo "All tables created successfully! You can now log in.\n";
    echo "Delete this file (setup.php) after verification.\n";
} else {
    echo "Some tables failed. Check errors above.\n";
}
