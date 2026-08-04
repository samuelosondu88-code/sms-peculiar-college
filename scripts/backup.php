<?php

declare(strict_types=1);

/**
 * Database backup / restore utility (CLI).
 *
 * Pure PHP MySQL dump — no mysqldump dependency — so it runs on free shared
 * hosts. Backups are written to storage/backups/ and gzip'd.
 *
 * Usage:
 *   php scripts\backup.php                        # create a backup
 *   php scripts\backup.php --list                 # list existing backups
 *   php scripts\backup.php --restore=FILE.sql.gz  # restore a backup
 *   php scripts\backup.php --rotate=10            # delete backups beyond the N most recent
 *
 * Secure for web use, but a .htaccess denies direct web access to the script;
 * it is intended to be invoked from the command line / cron.
 */

// Load environment (legacy loader, no Composer required).
require __DIR__ . '/../config/env.php';

$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'sms_peculiar_college';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

if ($user === '' || $pass === '') {
    fwrite(STDERR, "DB_USER / DB_PASS not configured.\n");
    exit(2);
}

$backupDir = __DIR__ . '/../storage/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
    fwrite(STDERR, "Cannot create backup directory: $backupDir\n");
    exit(2);
}

define('SQL_GO', "\n/* SMS-PEC---GO */\n");

function connect(string $host, string $name, string $user, string $pass): PDO
{
    return new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function sqlValue($v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return "'" . str_replace(["\\", "'", "\r", "\n", "\x00"], ["\\\\", "\\'", "\\r", "\\n", "\\0"], (string) $v) . "'";
}

function dumpDatabase(PDO $db, string $name): string
{
    $out  = "-- SMS Peculiar College backup\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s T') . "\n";
    $out .= "-- Database: $name\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $out .= SQL_GO;

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $out .= "-- Table: $table\n";
        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        if (false === $create) {
            continue;
        }
        $out .= $create[1] . ";\n" . SQL_GO;

        $row = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if (empty($row)) {
            continue;
        }

        $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_map(fn($c) => '`' . $c['Field'] . '`', $cols);
        $colsQ = implode(', ', $colNames);

        $buffer = '';
        $i = 0;
        foreach ($row as $r) {
            $vals = array_map('sqlValue', $r);
            $buffer .= "INSERT INTO `$table` ($colsQ) VALUES (" . implode(', ', $vals) . ");\n";
            if (++$i % 500 === 0) {
                $out .= $buffer . SQL_GO;
                $buffer = '';
            }
        }
        if ($buffer !== '') {
            $out .= $buffer . SQL_GO;
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n" . SQL_GO;
    return $out;
}

function restore(PDO $db, string $file): void
{
    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException("Cannot read backup: $file");
    }
    // gzip sniff
    if (substr($raw, 0, 2) === "\x1f\x8b") {
        $raw = gzdecode($raw);
        if ($raw === false) {
            throw new RuntimeException("Corrupt gzip backup: $file");
        }
    }

    $statements = array_filter(array_map('trim', explode(SQL_GO, $raw)));
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($statements as $stmt) {
        $sql = implode("\n", array_values(array_filter(
            array_map('trim', explode("\n", $stmt)),
            static fn (string $line): bool => $line !== '' && !str_starts_with($line, '--')
        )));
        if ($sql === '') {
            continue;
        }
        $db->exec($sql);
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
}

$args = array_slice($argv, 1);
$action = 'dump';
$target = null;
$rotate = 0;

foreach ($args as $a) {
    if ($a === '--list') {
        $action = 'list';
    } elseif (str_starts_with($a, '--restore=')) {
        $action = 'restore';
        $target = substr($a, strlen('--restore='));
    } elseif (str_starts_with($a, '--rotate=')) {
        $rotate = (int) substr($a, strlen('--rotate='));
    } elseif ($a === 'dump') {
        $action = 'dump';
    }
}

// ── List ──
if ($action === 'list') {
    $files = glob($backupDir . '/*.sql.gz');
    if (empty($files)) {
        echo "No backups found.\n";
        exit(0);
    }
    rsort($files);
    foreach ($files as $f) {
        printf("%s  %8.1f KB\n", basename($f), filesize($f) / 1024);
    }
    exit(0);
}

// ── Rotate ──
if ($rotate > 0) {
    $files = glob($backupDir . '/*.sql.gz');
    rsort($files);
    foreach (array_slice($files, $rotate) as $f) {
        @unlink($f);
        echo "Removed old backup: " . basename($f) . "\n";
    }
    exit(0);
}

$db = null;
try {
    $db = connect($host, $name, $user, $pass);
} catch (\PDOException $e) {
    fwrite(STDERR, "Database connection failed. Is MySQL running? (" . $e->getMessage() . ")\n");
    exit(2);
}

// ── Restore ──
if ($action === 'restore') {
    $path = str_starts_with($target, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $target)
        ? $target
        : $backupDir . '/' . basename($target);
    if (!is_file($path)) {
        fwrite(STDERR, "Backup file not found: $path\n");
        exit(2);
    }
    try {
        restore($db, $path);
        echo "Restore complete from " . basename($path) . "\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "Restore failed: " . $e->getMessage() . "\n");
        exit(2);
    }
    exit(0);
}

// ── Dump (default) ──
try {
    $tableCount = count($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
    $sql = dumpDatabase($db, $name);
    $file = $backupDir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql.gz';
    $gz = gzencode($sql);
    if (file_put_contents($file, $gz) === false) {
        throw new RuntimeException("Failed to write $file");
    }
    printf("Backup written: %s (%.1f KB, %d tables)\n", basename($file), strlen($gz) / 1024, $tableCount);
} catch (\Throwable $e) {
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . "\n");
    exit(2);
}
exit(0);