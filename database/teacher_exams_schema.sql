CREATE TABLE IF NOT EXISTS exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    term_id INT,
    question_type ENUM('mcq','true_false','fill_blank','short_answer','essay') NOT NULL DEFAULT 'mcq',
    question_text TEXT NOT NULL,
    option_a TEXT,
    option_b TEXT,
    option_c TEXT,
    option_d TEXT,
    correct_answer TEXT,
    marks DECIMAL(5,2) DEFAULT 1.00,
    image_path VARCHAR(255),
    explanation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    term_id INT,
    title VARCHAR(255) NOT NULL,
    exam_type ENUM('CA','Test','Mid-Term','Examination','Mock Exam','CBT') NOT NULL DEFAULT 'Test',
    total_marks DECIMAL(10,2) DEFAULT 0.00,
    duration_minutes INT DEFAULT 60,
    exam_date DATE,
    start_time TIME,
    end_time TIME,
    instructions TEXT,
    shuffle_questions TINYINT(1) DEFAULT 0,
    show_result TINYINT(1) DEFAULT 1,
    is_published TINYINT(1) DEFAULT 0,
    status ENUM('draft','published','in_progress','completed','graded') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teacher_exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT DEFAULT 0,
    FOREIGN KEY (exam_id) REFERENCES teacher_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE,
    UNIQUE KEY (exam_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    started_at DATETIME,
    submitted_at DATETIME,
    status ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
    auto_score DECIMAL(10,2) DEFAULT 0.00,
    manual_score DECIMAL(10,2) DEFAULT 0.00,
    total_score DECIMAL(10,2) DEFAULT 0.00,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    grade VARCHAR(2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES teacher_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    response TEXT,
    is_correct TINYINT(1),
    auto_score DECIMAL(10,2) DEFAULT 0.00,
    manual_score DECIMAL(10,2) DEFAULT 0.00,
    total_score DECIMAL(10,2) DEFAULT 0.00,
    graded_by INT,
    graded_at DATETIME,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
