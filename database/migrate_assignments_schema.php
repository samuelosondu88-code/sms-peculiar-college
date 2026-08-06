<?php
/**
 * Idempotent migration runner to sync the assignments/submissions schema
 * with database/schema.sql.
 *
 * Deployed databases predate schema.sql and are missing:
 *   - assignments.file_path (code inserts it)
 *   - assignments.due_date DATETIME (deployed DB uses DATE; the code sends a
 *     datetime-local value "Y-m-dTH:i")
 *   - submissions.graded_by (grading writes it)
 *
 * Safe to re-run; checks the live schema before applying each change.
 *
 *   php database/migrate_assignments_schema.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../config/database.php';

$db = getDB();

function tableExists(PDO $db, string $t): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$t]);
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $db, string $t, string $c): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$t, $c]);
    return (bool)$stmt->fetchColumn();
}

$changed = 0;

if (!tableExists($db, 'assignments')) {
    fwrite(STDERR, "FATAL: assignments table does not exist. Run schema.sql first.\n");
    exit(1);
}

/* assignments.file_path */
if (!columnExists($db, 'assignments', 'file_path')) {
    $db->exec("ALTER TABLE assignments ADD COLUMN file_path VARCHAR(255) NULL");
    echo "ADDED assignments.file_path\n";
    $changed++;
}

/* assignments.due_date should accept datetime-local values from the form. */
$dd = $db->query("SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'assignments' AND column_name = 'due_date'")->fetchColumn();
if ($dd && strtolower($dd) !== 'datetime') {
    $db->exec("ALTER TABLE assignments MODIFY due_date DATETIME NOT NULL");
    echo "CHANGED assignments.due_date to DATETIME (was $dd)\n";
    $changed++;
}

if (!tableExists($db, 'submissions')) {
    fwrite(STDERR, "FATAL: submissions table does not exist. Run schema.sql first.\n");
    exit(1);
}

/* submissions.graded_by */
if (!columnExists($db, 'submissions', 'graded_by')) {
    $db->exec("ALTER TABLE submissions ADD COLUMN graded_by INT NULL");
    echo "ADDED submissions.graded_by\n";
    $changed++;
}

echo $changed ? "Migration complete ($changed change(s) applied).\n" : "Migration up to date (0 changes).\n";
