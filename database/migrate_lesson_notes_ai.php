<?php
/**
 * Idempotent migration runner for the Lesson Notes + AI feature.
 *
 * Safe to re-run. Checks the live schema before applying each change,
 * so it works on both MariaDB and MySQL.
 *
 *   php database/migrate_lesson_notes_ai.php
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

function indexExists(PDO $db, string $t, string $i): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
    $stmt->execute([$t, $i]);
    return (bool)$stmt->fetchColumn();
}

$changed = 0;

if (!tableExists($db, 'lesson_notes')) {
    fwrite(STDERR, "FATAL: lesson_notes table does not exist. Run schema.sql first.\n");
    exit(1);
}

// NOTE: NO "AFTER" clauses here. The lesson_notes table has two historical
// variants (schema.sql uses file_path/week_no/date_taught; the deployed DB
// uses week/term_id/lesson_plan_id). Column order does not matter, so we add
// columns positionally-safe by omitting AFTER entirely.
$add = [
    'file_path'           => "ALTER TABLE lesson_notes ADD COLUMN file_path VARCHAR(255) NULL",
    'date_taught'         => "ALTER TABLE lesson_notes ADD COLUMN date_taught DATE NULL",
    'academic_session_id' => "ALTER TABLE lesson_notes ADD COLUMN academic_session_id INT NULL",
    'term_id'             => "ALTER TABLE lesson_notes ADD COLUMN term_id INT NULL",
    'lesson_plan_id'      => "ALTER TABLE lesson_notes ADD COLUMN lesson_plan_id INT NULL",
    'status'              => "ALTER TABLE lesson_notes ADD COLUMN status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft'",
    'is_ai_generated'     => "ALTER TABLE lesson_notes ADD COLUMN is_ai_generated TINYINT(1) NOT NULL DEFAULT 0",
    'summary'             => "ALTER TABLE lesson_notes ADD COLUMN summary TEXT NULL",
    'updated_at'          => "ALTER TABLE lesson_notes ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
];
foreach ($add as $col => $sql) {
    if (!columnExists($db, 'lesson_notes', $col)) {
        $db->exec($sql);
        echo "ADDED lesson_notes.$col\n";
        $changed++;
    }
}

$indexes = [
    'idx_lesson_notes_status'  => "ALTER TABLE lesson_notes ADD KEY idx_lesson_notes_status (status)",
    'idx_lesson_notes_class'   => "ALTER TABLE lesson_notes ADD KEY idx_lesson_notes_class (class_id, status)",
    'idx_lesson_notes_session' => "ALTER TABLE lesson_notes ADD KEY idx_lesson_notes_session (academic_session_id, term_id)",
    'idx_lesson_notes_plan'    => "ALTER TABLE lesson_notes ADD KEY idx_lesson_notes_plan (lesson_plan_id)",
];
foreach ($indexes as $idx => $sql) {
    if (!indexExists($db, 'lesson_notes', $idx)) {
        $db->exec($sql);
        echo "ADDED index $idx\n";
        $changed++;
    }
}

if (!tableExists($db, 'ai_generation_log')) {
    $db->exec("CREATE TABLE IF NOT EXISTS ai_generation_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        action VARCHAR(60) NOT NULL,
        provider VARCHAR(40) NOT NULL DEFAULT 'template',
        model VARCHAR(100) NULL,
        prompt TEXT NULL,
        status ENUM('success','error') NOT NULL DEFAULT 'success',
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ai_gen_teacher (teacher_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "CREATED ai_generation_log\n";
    $changed++;
}

echo $changed ? "Migration complete ($changed change(s) applied).\n" : "Migration up to date (0 changes).\n";
