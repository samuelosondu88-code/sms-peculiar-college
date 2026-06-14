-- Result Management Module Schema
-- Extends existing SMS with term-based result computation, approval workflow,
-- psychomotor/affective assessment, promotion engine, PIN access

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;

-- Result Settings (CA weight, exam weight, grade boundaries, component max scores)
CREATE TABLE IF NOT EXISTS result_settings (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    ca_weight DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    exam_weight DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    max_assign1 DECIMAL(5,2) DEFAULT 10.00,
    max_assign2 DECIMAL(5,2) DEFAULT 10.00,
    max_test1 DECIMAL(5,2) DEFAULT 10.00,
    max_test2 DECIMAL(5,2) DEFAULT 10.00,
    max_exam DECIMAL(5,2) DEFAULT 60.00,
    ca_max DECIMAL(5,2) DEFAULT 40.00,
    pass_mark DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    grade_a_min DECIMAL(5,2) NOT NULL DEFAULT 75.00,
    grade_b_min DECIMAL(5,2) NOT NULL DEFAULT 60.00,
    grade_c_min DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    grade_d_min DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    grade_e_min DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_term (session_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Result Scores (per student per subject per term)
CREATE TABLE IF NOT EXISTS result_scores (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    -- CA Components
    assignment_score DECIMAL(5,2) DEFAULT 0.00,
    assignment2_score DECIMAL(5,2) DEFAULT 0.00,
    test_score DECIMAL(5,2) DEFAULT 0.00,
    test2_score DECIMAL(5,2) DEFAULT 0.00,
    ca_total DECIMAL(5,2) DEFAULT 0.00,
    -- Project / Practical
    project_score DECIMAL(5,2) DEFAULT 0.00,
    -- Exam
    exam_score DECIMAL(5,2) DEFAULT 0.00,
    -- Computed
    total_score DECIMAL(5,2) DEFAULT 0.00,
    grade VARCHAR(2) DEFAULT NULL,
    subject_position INT(11) DEFAULT NULL,
    -- Status
    status ENUM('draft','submitted','approved','published') NOT NULL DEFAULT 'draft',
    entered_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject_term (student_id, subject_id, session_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Psychomotor Assessment
CREATE TABLE IF NOT EXISTS psychomotor_assessments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    creativity ENUM('A','B','C','D','E') DEFAULT 'B',
    sports ENUM('A','B','C','D','E') DEFAULT 'B',
    practical_skills ENUM('A','B','C','D','E') DEFAULT 'B',
    neatness ENUM('A','B','C','D','E') DEFAULT 'B',
    leadership ENUM('A','B','C','D','E') DEFAULT 'B',
    entered_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_term_psycho (student_id, session_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Affective Domain Assessment
CREATE TABLE IF NOT EXISTS affective_assessments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    honesty ENUM('A','B','C','D','E') DEFAULT 'B',
    punctuality ENUM('A','B','C','D','E') DEFAULT 'B',
    respect ENUM('A','B','C','D','E') DEFAULT 'B',
    cooperation ENUM('A','B','C','D','E') DEFAULT 'B',
    responsibility ENUM('A','B','C','D','E') DEFAULT 'B',
    entered_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_term_affect (student_id, session_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Result Comments (Teacher and Principal remarks)
CREATE TABLE IF NOT EXISTS result_comments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    class_teacher_remark TEXT DEFAULT NULL,
    class_teacher_id INT(11) DEFAULT NULL,
    principal_remark TEXT DEFAULT NULL,
    principal_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (principal_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_term_comment (student_id, session_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Result Approval Workflow
CREATE TABLE IF NOT EXISTS result_approvals (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    subject_id INT(11) DEFAULT NULL,
    approval_stage ENUM('subject_teacher','class_teacher','principal','published') NOT NULL DEFAULT 'subject_teacher',
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by INT(11) DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_approval (class_id, session_id, term_id, subject_id, approval_stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Promotion Configuration
CREATE TABLE IF NOT EXISTS promotion_config (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    pass_mark DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    min_subjects_pass INT(11) NOT NULL DEFAULT 5,
    conditional_pass_mark DECIMAL(5,2) NOT NULL DEFAULT 35.00,
    max_fail_subjects INT(11) NOT NULL DEFAULT 2,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_class (session_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Promotion Results
CREATE TABLE IF NOT EXISTS promotion_results (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    from_class_id INT(11) NOT NULL,
    to_class_id INT(11) DEFAULT NULL,
    session_id INT(11) NOT NULL,
    annual_average DECIMAL(5,2) DEFAULT NULL,
    promotion_status ENUM('promoted','conditional','repeated','graduated') NOT NULL,
    remark TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (from_class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (to_class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_session (student_id, session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Result Access PINs
CREATE TABLE IF NOT EXISTS result_pins (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pin VARCHAR(20) NOT NULL UNIQUE,
    student_id INT(11) DEFAULT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) DEFAULT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATE DEFAULT NULL,
    used_at DATETIME DEFAULT NULL,
    generated_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pin (pin),
    INDEX idx_student_active (student_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Academic Insights
CREATE TABLE IF NOT EXISTS academic_insights (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    strengths TEXT DEFAULT NULL,
    weaknesses TEXT DEFAULT NULL,
    recommendations TEXT DEFAULT NULL,
    subject_suggestions TEXT DEFAULT NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_term_insight (student_id, session_id, term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Broadcast / Notification Log
CREATE TABLE IF NOT EXISTS broadcast_log (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT(11) NOT NULL,
    recipient_type ENUM('student','parent','teacher','all') NOT NULL DEFAULT 'student',
    channel ENUM('sms','email','both') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent','failed','pending') NOT NULL DEFAULT 'sent',
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id, recipient_type),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
