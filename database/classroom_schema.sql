-- Virtual Classroom Module Schema
-- Integrates with existing students, teachers, subjects, sessions, terms

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;

-- Virtual Classes
CREATE TABLE IF NOT EXISTS virtual_classes (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    session_id INT(11) NOT NULL,
    term_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    INDEX idx_teacher (teacher_id),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Enrollments
CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    status ENUM('active','dropped') NOT NULL DEFAULT 'active',
    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (virtual_class_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Materials (lesson notes, PDFs, videos, etc.)
CREATE TABLE IF NOT EXISTS class_materials (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    material_type ENUM('lesson_note','video','document','presentation','reference','other') NOT NULL DEFAULT 'lesson_note',
    uploaded_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (virtual_class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Announcements
CREATE TABLE IF NOT EXISTS class_announcements (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Assignments
CREATE TABLE IF NOT EXISTS class_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    max_score DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    due_date DATETIME DEFAULT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (virtual_class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assignment Submissions
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    submission_text TEXT DEFAULT NULL,
    score DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    graded_by INT(11) DEFAULT NULL,
    status ENUM('submitted','graded','returned') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    graded_at DATETIME DEFAULT NULL,
    FOREIGN KEY (assignment_id) REFERENCES class_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_submission (assignment_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Attendance
CREATE TABLE IF NOT EXISTS class_attendance (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late') NOT NULL DEFAULT 'present',
    marked_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (virtual_class_id, student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Discussions
CREATE TABLE IF NOT EXISTS class_discussions (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    parent_id INT(11) DEFAULT NULL,
    user_id INT(11) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES class_discussions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (virtual_class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teacher Class Schedule (for scheduled live lessons)
CREATE TABLE IF NOT EXISTS class_schedule (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    virtual_class_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    scheduled_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    meeting_link VARCHAR(500) DEFAULT NULL,
    is_live TINYINT(1) NOT NULL DEFAULT 0,
    recording_path VARCHAR(500) DEFAULT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_class_id) REFERENCES virtual_classes(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class_date (virtual_class_id, scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
