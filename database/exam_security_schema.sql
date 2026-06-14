SET SQL_MODE = 'ALLOW_INVALID_DATES';

-- Exam Security Settings (per exam, merged into teacher_exams logic)
CREATE TABLE IF NOT EXISTS exam_security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL UNIQUE,
    require_fullscreen TINYINT(1) DEFAULT 1,
    require_camera TINYINT(1) DEFAULT 0,
    max_tab_switches INT DEFAULT 3,
    max_fullscreen_exits INT DEFAULT 3,
    max_camera_errors INT DEFAULT 5,
    max_face_violations INT DEFAULT 5,
    inactivity_timeout_minutes INT DEFAULT 5,
    auto_submit_on_violation TINYINT(1) DEFAULT 1,
    restrict_device TINYINT(1) DEFAULT 0,
    allowed_ips TEXT DEFAULT NULL,
    shuffle_questions TINYINT(1) DEFAULT 1,
    shuffle_options TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES teacher_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comprehensive Activity Log
CREATE TABLE IF NOT EXISTS exam_activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt_event (attempt_id, event_type),
    INDEX idx_created (created_at),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Proctoring Frames (evidence)
CREATE TABLE IF NOT EXISTS exam_proctoring_evidence (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    violation_type VARCHAR(50) NOT NULL,
    face_count INT DEFAULT 0,
    confidence_score DECIMAL(5,2) DEFAULT NULL,
    risk_score INT DEFAULT 0,
    metadata JSON DEFAULT NULL,
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_attempt_violation (attempt_id, violation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Device Fingerprints
CREATE TABLE IF NOT EXISTS exam_device_fingerprints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL UNIQUE,
    fingerprint_hash VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    screen_resolution VARCHAR(20) DEFAULT NULL,
    timezone_offset INT DEFAULT NULL,
    platform VARCHAR(50) DEFAULT NULL,
    language VARCHAR(20) DEFAULT NULL,
    hardware_concurrency INT DEFAULT NULL,
    device_memory DECIMAL(5,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    INDEX idx_fingerprint (fingerprint_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Integrity Scores (one per attempt)
CREATE TABLE IF NOT EXISTS exam_integrity_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL UNIQUE,
    camera_compliance DECIMAL(5,2) DEFAULT 100.00,
    tab_switch_count INT DEFAULT 0,
    fullscreen_exit_count INT DEFAULT 0,
    camera_error_count INT DEFAULT 0,
    face_violation_count INT DEFAULT 0,
    identity_verified TINYINT(1) DEFAULT 0,
    overall_score DECIMAL(5,2) DEFAULT 100.00,
    risk_level ENUM('low','medium','high','critical') DEFAULT 'low',
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns to exam_attempts for security tracking
ALTER TABLE exam_attempts
    ADD COLUMN IF NOT EXISTS violation_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS auto_submitted TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS submit_reason VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS device_fingerprint VARCHAR(64) DEFAULT NULL;
