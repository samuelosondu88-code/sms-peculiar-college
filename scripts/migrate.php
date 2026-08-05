<?php

declare(strict_types=1);

/**
 * Database index migration runner (CLI).
 *
 * Applies database/migration_indexes.sql against the configured database.
 * The migration file is DELIMITER-aware (it uses a stored procedure so the
 * same SQL is portable across MySQL 5.7+/8 and MariaDB); this runner parses
 * the DELIMITER directives and executes each statement via PDO.
 *
 * Idempotent: re-running is safe — indexes are only created if missing.
 *
 * Usage:
 *   php scripts\migrate.php            # apply the migration
 *   php scripts\migrate.php --check    # dry-run: report which indexes exist
 *
 * Intended to be invoked from the command line (web access is denied by
 * .htaccess like the other scripts/ tools).
 */

require __DIR__ . '/../config/env.php';

$host = env('DB_HOST') ?: 'localhost';
$name = env('DB_NAME') ?: 'sms_peculiar_college';
$user = env('DB_USER') ?: '';
$pass = env('DB_PASS') ?: '';

if ($user === '' || $pass === '') {
    fwrite(STDERR, "DB_USER / DB_PASS not configured.\n");
    exit(2);
}

$migrationFile = __DIR__ . '/../database/migration_indexes.sql';
foreach ($argv as $a) {
    if (preg_match('/^--file=(.+)$/', $a, $m)) {
        $candidate = $m[1];
        $candidate = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (is_file($candidate)) {
            $migrationFile = $candidate;
        } elseif (is_file(__DIR__ . '/../database/' . $candidate)) {
            $migrationFile = __DIR__ . '/../database/' . $candidate;
        }
    }
}
if (!is_file($migrationFile)) {
    fwrite(STDERR, "Migration file not found: $migrationFile\n");
    exit(2);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(2);
}

/**
 * Parse a SQL script honoring DELIMITER directives into statements.
 *
 * @return string[]
 */
function splitStatements(string $sql): array
{
    $lines = preg_split('/\r\n|\r|\n/', $sql);
    $statements = [];
    $delimiter = ';';
    $buffer = '';

    foreach ($lines as $line) {
        $trim = trim($line);

        if (preg_match('/^DELIMITER\s+(\S+)/i', $trim, $m)) {
            if (trim($buffer) !== '') {
                $statements[] = trim($buffer);
                $buffer = '';
            }
            $delimiter = $m[1];
            continue;
        }

        // Skip full-line comments (never semantically required).
        if (str_starts_with($trim, '--') || str_starts_with($trim, '#')) {
            continue;
        }

        $buffer .= $line . "\n";

        while (($pos = strpos($buffer, $delimiter)) !== false) {
            $stmt = trim(substr($buffer, 0, $pos));
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = substr($buffer, $pos + strlen($delimiter));
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function shorten(string $stmt, int $max = 72): string
{
    $one = preg_replace('/\s+/', ' ', $stmt);
    if (strlen($one) <= $max) {
        return $one;
    }
    return substr($one, 0, $max) . '…';
}

$sql = (string) file_get_contents($migrationFile);
$statements = splitStatements($sql);

$dryRun = in_array('--check', $argv, true);

// For --check, list indexes already present on the target tables.
if ($dryRun) {
    $tables = [];
    foreach ($statements as $stmt) {
        // Each CALL passes (table, index_name, column_list) to the helper.
        if (preg_match_all("/CALL add_index_if_not_exists\s*\(\s*'([a-z_]+)'\s*,\s*'([a-z_]+)'/i", $stmt, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $tables[$m[1]] = true;
            }
        }
    }
    $tables = array_keys($tables);
    $in = implode(',', array_fill(0, count($tables), '?'));
    $stmtCheck = $pdo->prepare(
        "SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($in)
         GROUP BY TABLE_NAME, INDEX_NAME ORDER BY TABLE_NAME, INDEX_NAME"
    );
    $stmtCheck->execute($tables);
    $rows = $stmtCheck->fetchAll();

    if ($rows) {
        echo "Existing indexes on target tables:\n";
        foreach ($rows as $r) {
            echo "  {$r['TABLE_NAME']}.{$r['INDEX_NAME']} ({$r['cols']})\n";
        }
    } else {
        echo "No target indexes found.\n";
    }
    echo "(dry-run — run without --check to apply)\n";
    exit(0);
}

echo "Applying migration: " . basename($migrationFile) . "\n";
echo "Statements to run: " . count($statements) . "\n";

$failures = 0;
foreach ($statements as $i => $stmt) {
    try {
        $pdo->exec($stmt);
        echo sprintf("[%2d] OK   %s\n", $i + 1, shorten($stmt));
    } catch (PDOException $e) {
        $failures++;
        echo sprintf("[%2d] FAIL %s\n      -> %s\n", $i + 1, shorten($stmt), $e->getMessage());
    }
}

echo "--- done: " . (count($statements) - $failures) . " succeeded, $failures failed ---\n";
exit($failures > 0 ? 1 : 0);
