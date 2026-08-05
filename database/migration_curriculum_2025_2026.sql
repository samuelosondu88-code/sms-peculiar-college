-- 2025/2026 Nigerian Secondary School Curriculum migration.
-- Adds discipline category + level to subjects and a student subject-selection
-- table, so report cards can be grouped by discipline (core/science/humanities/
-- business/trade/vocational) for JSS (Junior) and SS (Senior) secondary levels.

-- 1. Add subjects.category (discipline) and subjects.level (JSS/SS).
ALTER TABLE `subjects`
    ADD COLUMN `category` VARCHAR(50) NOT NULL DEFAULT 'core',
    ADD COLUMN `level` VARCHAR(10) NOT NULL DEFAULT 'JSS';

-- 2. Backfill level based on class name (SS* => SS, otherwise JSS).
UPDATE `subjects` s
JOIN `classes` c ON c.id = s.class_id
SET s.level = CASE WHEN UPPER(c.name) LIKE 'SS%' THEN 'SS' ELSE 'JSS' END;

-- 3. Backfill category from subject name keywords (most modest, general-purpose
--    inference; an admin can fine-tune via the Subjects screen afterwards).
UPDATE `subjects`
SET category = 'trade'
WHERE category = 'core' AND (
    name LIKE '%technology%' OR name LIKE '%agriculture%' OR name LIKE '%animal%'
    OR name LIKE '%catering%' OR name LIKE '%computer%' OR name LIKE '%business%'
    OR name LIKE '%commerce%' OR name LIKE '%marketing%' OR name LIKE '%financial%'
    OR name LIKE '%accounting%' OR name LIKE '%typing%' OR name LIKE '%shorthand%'
    OR name LIKE '%office%' OR name LIKE '%welding%' OR name LIKE '%electric%'
    OR name LIKE '%carpentry%' OR name LIKE '%garment%' OR name LIKE '%fashion%'
    OR name LIKE '%hair%' OR name LIKE '%beauty%' OR name LIKE '%art%'
    OR name LIKE '%music%' OR name LIKE '%drawing%' OR name LIKE '%wood%'
    OR name LIKE '%metal%' OR name LIKE '%mechanical%'
);

ALTER TABLE `subjects` ADD INDEX `idx_subjects_category` (`category`);
ALTER TABLE `subjects` ADD INDEX `idx_subjects_level` (`level`);

-- 4. Student subject selections (drives the curriculum structure per student).
CREATE TABLE IF NOT EXISTS `student_subject_selections` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `student_id` int(11) NOT NULL,
    `subject_id` int(11) NOT NULL,
    `academic_session_id` int(11) NOT NULL,
    `is_core` tinyint(1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_student_subject` (`student_id`,`subject_id`,`academic_session_id`),
    KEY `subject_id` (`subject_id`),
    KEY `idx_sel_session_student` (`academic_session_id`,`student_id`),
    CONSTRAINT `student_subject_selections_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `student_subject_selections_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `student_subject_selections_ibfk_3` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;