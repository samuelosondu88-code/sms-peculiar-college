-- ============================================================
-- Migration: Query Optimisation Indexes
-- SMS Peculiar College
-- ------------------------------------------------------------
-- Adds composite indexes for the app's hottest lookup patterns
-- (results, approvals, attendance, fees, payments, audit logs).
--
-- Idempotent: each index is only created if it does not exist.
-- Run once:
--   mysql -u USER -p DATABASE < database/migration_indexes.sql
--   (or via phpMyAdmin "Import")
-- ============================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS add_index_if_not_exists;

DELIMITER //
CREATE PROCEDURE add_index_if_not_exists(IN tbl_name VARCHAR(64), IN idx_name VARCHAR(64), IN col_list VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE()
          AND table_name = tbl_name
          AND index_name = idx_name
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl_name, '` ADD INDEX `', idx_name, '` (', col_list, ')');
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- Result scores: per-student and per-class term lookups (report cards, summaries)
CALL add_index_if_not_exists('result_scores', 'idx_scores_student_term', 'student_id, session_id, term_id');
CALL add_index_if_not_exists('result_scores', 'idx_scores_class_term',   'class_id, session_id, term_id');
CALL add_index_if_not_exists('result_scores', 'idx_scores_subject_term', 'subject_id, session_id, term_id');

-- Approval workflow lookups
CALL add_index_if_not_exists('result_approvals', 'idx_approvals_class_term', 'class_id, session_id, term_id, approval_stage');
CALL add_index_if_not_exists('result_approvals', 'idx_approvals_status',     'status, approval_stage');

-- Attendance stats (report cards) and daily registers
CALL add_index_if_not_exists('attendance', 'idx_att_student_term', 'student_id, class_id, date');
CALL add_index_if_not_exists('attendance', 'idx_att_class_date',   'class_id, date');

-- Fees: outstanding balances per student, and overdue-aging reports
CALL add_index_if_not_exists('fees', 'idx_fees_student_status', 'student_id, status');
CALL add_index_if_not_exists('fees', 'idx_fees_status_due',     'status, due_date');

-- Payments: per-fee history, reference dedup, date range reports
CALL add_index_if_not_exists('payments', 'idx_payments_fee',          'fee_id');
CALL add_index_if_not_exists('payments', 'idx_payments_ref',          'transaction_ref');
CALL add_index_if_not_exists('payments', 'idx_payments_date',         'payment_date');

-- Audit trail queries
CALL add_index_if_not_exists('audit_logs', 'idx_audit_user_action', 'user_id, action');
CALL add_index_if_not_exists('audit_logs', 'idx_audit_action_table', 'action, table_name');

-- Brute-force throttle lookups
CALL add_index_if_not_exists('login_attempts', 'idx_login_user_ip', 'username, ip_address');

DROP PROCEDURE IF EXISTS add_index_if_not_exists;
