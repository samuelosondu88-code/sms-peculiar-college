-- ============================================================
-- Migration: Missing app-expected columns
-- SMS Peculiar College
-- ------------------------------------------------------------
-- The app queries columns that the live database lacked:
--   * submissions.status   (declared in database/schema.sql but the
--     live table was created from an older definition)
--   * books.description    (referenced by student/library.php but never
--     present in database/schema.sql)
-- Idempotent: each column is only added if it does not exist.
-- ============================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS add_column_if_not_exists;

DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(300))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col
    ) THEN
        SET @q = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE s FROM @q;
        EXECUTE s;
        DEALLOCATE PREPARE s;
    END IF;
END//
DELIMITER ;

CALL add_column_if_not_exists('submissions', 'status', '`status` ENUM(''submitted'',''graded'',''late'') DEFAULT ''submitted'' AFTER `feedback`');
CALL add_column_if_not_exists('books', 'description', '`description` TEXT DEFAULT NULL AFTER `category`');

DROP PROCEDURE IF EXISTS add_column_if_not_exists;