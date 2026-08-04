-- ============================================================
-- Migration: admission_forms settings columns
-- SMS Peculiar College
-- ------------------------------------------------------------
-- The admin "Admission Form Settings" page (admin/admission-forms.php)
-- manages purchasable admission-form definitions (form_name, price,
-- academic_session_id, is_active). Populate the missing columns so the
-- existing page works, regardless of any pre-existing rows.
--
-- Idempotent via MariaDB ADD COLUMN IF NOT EXISTS. MySQL 8 does not support
-- IF NOT EXISTS here — run each statement once, or through the runner which
-- tolerates a duplicate "duplicate column name" error.
-- ============================================================

ALTER TABLE admission_forms ADD COLUMN IF NOT EXISTS form_name VARCHAR(100) NULL AFTER status;
ALTER TABLE admission_forms ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE admission_forms ADD COLUMN IF NOT EXISTS academic_session_id INT NULL;
ALTER TABLE admission_forms ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;