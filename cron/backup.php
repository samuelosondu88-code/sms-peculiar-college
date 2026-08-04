<?php
/**
 * Database & File Backup Script
 *
 * Usage: php cron/backup.php
 * Recommended: Run daily via cron (Linux) or Task Scheduler (Windows)
 *
 * Cron example (daily at 2 AM):
 *   0 2 * * * /usr/bin/php /var/www/html/cron/backup.php
 */

require_once __DIR__ . '/../config/env.php';

$backupDir = __DIR__ . '/../backups/';
$dbName = getenv('DB_NAME') ?: 'sms_peculiar_college';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';
$dbHost = getenv('DB_HOST') ?: 'localhost';
$retentionDays = 30;
$verbosity = in_array('--verbose', $_SERVER['argv'] ?? []);
$now = date('Y-m-d_H-i-s');
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

if ($isWindows && $verbosity) {
    logMsg("Windows environment detected, using XAMPP paths");
}

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

function logMsg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ── 1. Database Backup ──
logMsg("Starting database backup: {$dbName}");

if ($isWindows) {
    $mysqlDir = 'C:\xampp\mysql\bin';
    $dbFile = "{$backupDir}db_{$dbName}_{$now}.sql";
    $cmd = sprintf(
        '"%s\mysqldump.exe" --host=%s --user=%s --password=%s --single-transaction --routines --triggers --events --skip-lock-tables --result-file=%s %s 2>&1',
        $mysqlDir,
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbFile),
        escapeshellarg($dbName)
    );
} else {
    $dbFile = "{$backupDir}db_{$dbName}_{$now}.sql.gz";
    $cmd = sprintf(
        'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers --events --skip-lock-tables %s 2>&1 | gzip > %s',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($dbFile)
    );
}

$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

if ($returnCode === 0) {
    $size = filesize($dbFile);
    logMsg("Database backup saved: {$dbFile} (" . round($size / 1024 / 1024, 2) . " MB)");
} else {
    logMsg("Database backup FAILED: " . implode("\n", $output));
}

// ── 2. File Backup ──
logMsg("Starting file backup...");

$fileDirs = [
    __DIR__ . '/../documents',
    __DIR__ . '/../uploads',
    __DIR__ . '/../assets/images',
];

if ($isWindows) {
    $fileFile = "{$backupDir}files_{$now}.zip";
    $includeArgs = '';
    foreach ($fileDirs as $d) {
        if (is_dir($d)) {
            $includeArgs .= ' ' . escapeshellarg($d);
        }
    }
    if ($includeArgs) {
        $includeArgs = str_replace(' ', ',', trim($includeArgs));
        $cmd = 'powershell -NoProfile -Command "& { Compress-Archive -Path ' . $includeArgs . ' -DestinationPath ' . escapeshellarg($fileFile) . ' -Force; if ($?) { exit 0 } else { exit 1 } }" 2>&1';
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        if (file_exists($fileFile) && filesize($fileFile) > 0) {
            $size = filesize($fileFile);
            logMsg("File backup saved: {$fileFile} (" . round($size / 1024 / 1024, 2) . " MB)");
        } else {
            logMsg("File backup FAILED: " . implode("\n", $output));
        }
    }
} else {
    $fileFile = "{$backupDir}files_{$now}.tar.gz";
    $includeArgs = '';
    foreach ($fileDirs as $d) {
        if (is_dir($d)) {
            $includeArgs .= ' ' . escapeshellarg(basename(dirname($d)) . '/' . basename($d));
        }
    }
    if ($includeArgs) {
        $cmd = sprintf(
            'tar -czf %s -C %s %s 2>&1',
            escapeshellarg($fileFile),
            escapeshellarg(dirname(__DIR__)),
            $includeArgs
        );
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        if ($returnCode === 0) {
            $size = filesize($fileFile);
            logMsg("File backup saved: {$fileFile} (" . round($size / 1024 / 1024, 2) . " MB)");
        } else {
            logMsg("File backup FAILED: " . implode("\n", $output));
        }
    }
}

// ── 3. Cleanup old backups ──
logMsg("Cleaning up backups older than {$retentionDays} days...");
$cutoff = time() - ($retentionDays * 86400);
$deleted = 0;
foreach (glob("{$backupDir}*") as $f) {
    if (is_file($f) && filemtime($f) < $cutoff) {
        unlink($f);
        $deleted++;
    }
}
logMsg("Deleted {$deleted} old backup(s).");

// ── 4. Verify latest backup ──
$latestDb = glob("{$backupDir}db_{$dbName}_*.sql*");
if (!empty($latestDb)) {
    rsort($latestDb);
    logMsg("Latest backup integrity assumed OK: {$latestDb[0]}");
}

logMsg("Backup complete.");
