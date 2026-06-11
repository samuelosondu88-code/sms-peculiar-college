-- School Management System - Peculiar International College
-- Database Schema v1.0

CREATE DATABASE IF NOT EXISTS sms_peculiar_college;
USE sms_peculiar_college;

-- Users table (unified login for all roles)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student','parent','accountant') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    avatar VARCHAR(255),
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Password reset tokens
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- User sessions log
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Audit log
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Academic years / sessions
CREATE TABLE academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(50) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) DEFAULT 0,
    status ENUM('active','closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Terms within a session
CREATE TABLE terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    term_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) DEFAULT 0,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Departments
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    head_teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (head_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Classes / Grades
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    section VARCHAR(20),
    capacity INT DEFAULT 40,
    class_teacher_id INT,
    department_id INT,
    academic_session_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Subjects
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    class_id INT NOT NULL,
    teacher_id INT,
    credit_unit INT DEFAULT 1,
    is_compulsory TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Subject allocations (teacher-class-subject mapping)
CREATE TABLE subject_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_session_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL,
    UNIQUE KEY (teacher_id, class_id, subject_id)
) ENGINE=InnoDB;

-- Teachers additional info
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    employee_id VARCHAR(30) UNIQUE NOT NULL,
    qualification TEXT,
    department_id INT,
    specialization VARCHAR(200),
    date_hired DATE,
    employment_type ENUM('full-time','part-time','contract') DEFAULT 'full-time',
    salary DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Students additional info
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    admission_no VARCHAR(30) UNIQUE NOT NULL,
    class_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    blood_group VARCHAR(5),
    religion VARCHAR(50),
    nationality VARCHAR(50),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    enrollment_date DATE,
    status ENUM('active','graduated','expelled','withdrawn') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Parents additional info
CREATE TABLE parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    occupation VARCHAR(100),
    relationship VARCHAR(50),
    emergency_contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Student-Parent relationship (many-to-many for multiple children)
CREATE TABLE student_parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    is_guardian TINYINT(1) DEFAULT 1,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, parent_id)
) ENGINE=InnoDB;

-- Timetable
CREATE TABLE timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    day ENUM('monday','tuesday','wednesday','thursday','friday','saturday') NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    room VARCHAR(50),
    term_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Attendance
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT,
    date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    remark TEXT,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, class_id, date)
) ENGINE=InnoDB;

-- Exams
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    term_id INT NOT NULL,
    class_id INT,
    start_date DATE,
    end_date DATE,
    max_score DECIMAL(5,2) DEFAULT 100.00,
    is_published TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Results / Grades
CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    score DECIMAL(5,2),
    grade VARCHAR(2),
    remark TEXT,
    entered_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (student_id, exam_id, subject_id)
) ENGINE=InnoDB;

-- Fee structure
CREATE TABLE fee_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    fee_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    term_id INT NOT NULL,
    due_date DATE,
    is_mandatory TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Student fee records
CREATE TABLE fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    balance DECIMAL(10,2) DEFAULT 0.00,
    due_date DATE,
    status ENUM('unpaid','partial','paid','overpaid','waived') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structure(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Payments
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','card','transfer','cheque','pos') NOT NULL,
    transaction_ref VARCHAR(100) UNIQUE,
    receipt_no VARCHAR(50) UNIQUE,
    payment_date DATE NOT NULL,
    verified_by INT,
    proof_document VARCHAR(255),
    notes TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Expenses
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    receipt_no VARCHAR(50),
    approved_by INT,
    entered_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Payroll
CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gross_salary DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0.00,
    net_salary DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    month VARCHAR(7) NOT NULL,
    status ENUM('pending','paid','cancelled') DEFAULT 'pending',
    transaction_ref VARCHAR(100),
    processed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Library books
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(200),
    isbn VARCHAR(20) UNIQUE,
    publisher VARCHAR(100),
    category VARCHAR(100),
    quantity INT DEFAULT 1,
    available INT DEFAULT 1,
    shelf_location VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Book borrowings
CREATE TABLE borrowings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    status ENUM('borrowed','returned','overdue','lost') DEFAULT 'borrowed',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    fine_paid TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Transport routes
CREATE TABLE transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(100) NOT NULL,
    description TEXT,
    fee DECIMAL(10,2) NOT NULL,
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    vehicle_no VARCHAR(50),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Student transport assignments
CREATE TABLE student_transport (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    route_id INT NOT NULL,
    pickup_point VARCHAR(200),
    dropoff_point VARCHAR(200),
    status ENUM('active','inactive') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Hostel / Dormitory
CREATE TABLE hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('boys','girls','mixed') NOT NULL,
    capacity INT NOT NULL,
    occupied INT DEFAULT 0,
    fee DECIMAL(10,2) NOT NULL,
    warden_id INT,
    status ENUM('active','inactive') DEFAULT 'active',
    FOREIGN KEY (warden_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Hostel rooms
CREATE TABLE hostel_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_no VARCHAR(20) NOT NULL,
    capacity INT DEFAULT 4,
    occupied INT DEFAULT 0,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Hostel allocations
CREATE TABLE hostel_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    bed_no VARCHAR(10),
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active','completed','cancelled') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES hostel_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Assignments
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    file_path VARCHAR(255),
    due_date DATETIME NOT NULL,
    max_score DECIMAL(5,2) DEFAULT 100.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Assignment submissions
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(5,2),
    feedback TEXT,
    graded_by INT,
    status ENUM('submitted','graded','late') DEFAULT 'submitted',
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lesson notes / Lesson plans
CREATE TABLE lesson_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    topic VARCHAR(200) NOT NULL,
    content LONGTEXT,
    file_path VARCHAR(255),
    week_no INT,
    date_taught DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Internal messaging
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    attachment VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    is_starred TINYINT(1) DEFAULT 0,
    parent_message_id INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_message_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Notices / Announcements
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    target_role ENUM('all','admin','teacher','student','parent'),
    priority ENUM('normal','important','urgent') DEFAULT 'normal',
    file_path VARCHAR(255),
    created_by INT NOT NULL,
    expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Events calendar
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(200),
    type ENUM('academic','sports','cultural','meeting','holiday','other') DEFAULT 'other',
    target_role ENUM('all','admin','teacher','student','parent'),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Complaints / Suggestions
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50),
    priority ENUM('low','medium','high') DEFAULT 'medium',
    status ENUM('pending','reviewing','resolved','rejected') DEFAULT 'pending',
    response TEXT,
    resolved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Contact form messages (public)
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    replied TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Behavior / Discipline records
CREATE TABLE behavior_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    incident_date DATE NOT NULL,
    incident_type VARCHAR(100),
    description TEXT NOT NULL,
    action_taken TEXT,
    reported_by INT NOT NULL,
    status ENUM('open','resolved','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admission forms configuration (for public purchase)
CREATE TABLE admission_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 4000.00,
    academic_session_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Public applications (prospective students)
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    address TEXT,
    class_applying VARCHAR(50),
    previous_school VARCHAR(200),
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    parent_email VARCHAR(100),
    parent_occupation VARCHAR(100),
    documents TEXT,
    payment_status ENUM('pending','paid','failed') DEFAULT 'pending',
    application_ref VARCHAR(30) UNIQUE NOT NULL,
    status ENUM('draft','submitted','reviewing','accepted','rejected','waitlisted') DEFAULT 'draft',
    admin_notes TEXT,
    reviewed_by INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES admission_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Application payments (₦4,000 form fee)
CREATE TABLE application_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('card','transfer','cash') NOT NULL,
    transaction_ref VARCHAR(100) UNIQUE,
    receipt_no VARCHAR(50) UNIQUE,
    proof_document VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    verified_by INT,
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Indexes for performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_students_class ON students(class_id);
CREATE INDEX idx_attendance_date ON attendance(date);
CREATE INDEX idx_results_student ON results(student_id);
CREATE INDEX idx_fees_student ON fees(student_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_messages_receiver ON messages(receiver_id, is_read);
CREATE INDEX idx_notices_target ON notices(target_role);
CREATE INDEX idx_applications_status ON applications(status);
