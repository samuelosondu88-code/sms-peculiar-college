-- =========================================================================
-- Migration: Lesson Notes enhancement + AI generation log
-- -------------------------------------------------------------------------
-- 1) Extends `lesson_notes` to support:
--      - academic session + term linking
--      - optional lesson plan linking
--      - draft / published / archived workflow
--      - AI-generated flag + summary + updated_at
-- 2) Creates `ai_generation_log` to record every AI assistant generation
--    (provider, model, action, status) for audit/tracing.
--
-- NOTE: These ALTERs are NOT idempotent in MySQL. Use
--       `php database/migrate_lesson_notes_ai.php` for safe re-runs, or
--       apply once on a fresh database.
-- =========================================================================

ALTER TABLE lesson_notes
    ADD COLUMN academic_session_id INT NULL AFTER class_id,
    ADD COLUMN term_id INT NULL AFTER academic_session_id,
    ADD COLUMN lesson_plan_id INT NULL AFTER term_id,
    ADD COLUMN date_taught DATE NULL AFTER lesson_plan_id,
    ADD COLUMN status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft' AFTER file_path,
    ADD COLUMN is_ai_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN summary TEXT NULL AFTER content,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE lesson_notes
    ADD KEY idx_lesson_notes_status (status),
    ADD KEY idx_lesson_notes_class (class_id, status),
    ADD KEY idx_lesson_notes_session (academic_session_id, term_id),
    ADD KEY idx_lesson_notes_plan (lesson_plan_id);

CREATE TABLE IF NOT EXISTS ai_generation_log (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
