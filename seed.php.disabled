<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();

    function h($p) { return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]); }
    $pwd = h('Password@123');

    // Drop existing data
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
        $db->exec("TRUNCATE TABLE `{$table}`");
    }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 1. Session + Terms
    $db->exec("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES ('2025/2026', '2025-09-01', '2026-08-31', 1, 'active')");
    $db->exec("INSERT INTO terms (session_id, term_name, start_date, end_date, is_current) VALUES (1, 'First Term', '2025-09-15', '2025-12-19', 1), (1, 'Second Term', '2026-01-06', '2026-04-10', 0), (1, 'Third Term', '2026-04-27', '2026-08-14', 0)");
    $db->exec("INSERT INTO departments (name, code, description) VALUES ('Science', 'SCI', 'Science Department'), ('Arts', 'ART', 'Arts and Humanities Department'), ('Commercial', 'COM', 'Commercial Studies Department')");

    // 2. Users
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['admin', 'admin@peculiarcollege.edu.ng', $pwd, 'Admin', 'User', '08012345670', 'admin']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['teacher1', 'teacher@peculiarcollege.edu.ng', $pwd, 'John', 'Okafor', '08012345671', 'teacher']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['teacher2', 'teacher2@peculiarcollege.edu.ng', $pwd, 'Sandra', 'Eze', '08012345672', 'teacher']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['student1', 'student@peculiarcollege.edu.ng', $pwd, 'Chidi', 'Okonkwo', '08012345673', 'student']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['parent1', 'parent@peculiarcollege.edu.ng', $pwd, 'Emeka', 'Okonkwo', '08012345674', 'parent']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['accountant1', 'accountant@peculiarcollege.edu.ng', $pwd, 'Chioma', 'Nwosu', '08012345675', 'accountant']);

    // 3. Classes
    $db->exec("INSERT INTO classes (name, section, capacity, class_teacher_id, academic_session_id) VALUES ('JSS1', 'A', 40, 2, 1), ('JSS1', 'B', 40, 3, 1), ('JSS2', 'A', 40, NULL, 1), ('JSS3', 'A', 35, NULL, 1), ('SS1', 'Science', 35, NULL, 1), ('SS1', 'Arts', 35, NULL, 1), ('SS1', 'Commercial', 35, NULL, 1), ('SS2', 'Science', 35, NULL, 1), ('SS2', 'Arts', 35, NULL, 1), ('SS3', 'Science', 30, NULL, 1)");

    // 4. Subjects
    $subj = $db->prepare("INSERT INTO subjects (class_id, name, code, teacher_id) VALUES (?, ?, ?, ?)");
    $subj->execute([1, 'Mathematics', 'MTH01', 2]); $subj->execute([1, 'English Language', 'ENG01', 3]); $subj->execute([1, 'Basic Science', 'BSC01', NULL]); $subj->execute([1, 'Computer Studies', 'CMP01', NULL]); $subj->execute([5, 'Mathematics', 'MTH05', 2]); $subj->execute([5, 'Physics', 'PHY01', NULL]); $subj->execute([5, 'Chemistry', 'CHM01', NULL]); $subj->execute([6, 'English Language', 'ENG06', 3]); $subj->execute([6, 'Literature', 'LIT01', NULL]);

    // 5. Teachers
    $db->exec("INSERT INTO teachers (user_id, employee_id, qualification, department_id, date_hired) VALUES (2, 'TCH001', 'B.Sc. Mathematics Education', 1, '2023-09-01'), (3, 'TCH002', 'B.A. English Studies', 2, '2023-09-01')");

    // 6. Subject Allocations
    $alloc = $db->prepare("INSERT INTO subject_allocations (teacher_id, class_id, subject_id, academic_session_id) VALUES (?, ?, ?, 1)");
    $alloc->execute([2, 1, 1]); $alloc->execute([2, 5, 5]); $alloc->execute([3, 1, 2]); $alloc->execute([3, 6, 8]);

    // 7. Student + Parent
    $db->exec("INSERT INTO students (user_id, admission_no, class_id, date_of_birth, gender, enrollment_date, status) VALUES (4, 'PEC2025001', 1, '2012-04-15', 'male', '2025-09-15', 'active')");
    $db->exec("INSERT INTO parents (user_id, occupation, relationship) VALUES (5, 'Business Owner', 'Father')");
    $db->exec("INSERT INTO student_parents (student_id, parent_id, is_guardian) VALUES (1, 1, 1)");

    // 8. Fee Structure + Student Fees
    $db->exec("INSERT INTO fee_structure (fee_name, amount, class_id, term_id, is_mandatory) VALUES ('Tuition Fee', 150000.00, 1, 1, 1), ('Development Levy', 25000.00, 1, 1, 1), ('Library Fee', 10000.00, 1, 1, 1)");
    $db->exec("INSERT INTO fees (student_id, fee_structure_id, total_amount, balance, due_date, status) VALUES (1, 1, 150000.00, 150000.00, '2025-10-15', 'unpaid'), (1, 2, 25000.00, 25000.00, '2025-10-15', 'unpaid'), (1, 3, 10000.00, 10000.00, '2025-10-15', 'unpaid')");

    // 9. Books
    $bk = $db->prepare("INSERT INTO books (title, author, isbn, publisher, category, quantity, available) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $bk->execute(['New General Mathematics JSS1', 'J.B. Channon', '9780195738211', 'Oxford', 'Textbook', 20, 20]);
    $bk->execute(['New Concept English JSS1', 'F. Ademola', '9780195738212', 'Longman', 'Textbook', 15, 15]);
    $bk->execute(['Physics for SS1', 'P.N. Okeke', '9780195738214', 'Africana Press', 'Textbook', 8, 8]);
    $bk->execute(['Things Fall Apart', 'Chinua Achebe', '9780195738216', 'Heinemann', 'Fiction', 5, 5]);

    // 10. Transport + Hostel
    $db->exec("INSERT INTO transport_routes (route_name, description, fee, driver_name, driver_phone, vehicle_no, status) VALUES ('Main Campus Route', 'Downtown to Main Campus', 35000.00, 'Musa Ibrahim', '08098765432', 'ABC-123XY', 'active')");
    $db->exec("INSERT INTO hostels (name, type, capacity, occupied, fee) VALUES ('Unity Hall', 'boys', 100, 1, 80000.00)");
    $db->exec("INSERT INTO hostel_rooms (hostel_id, room_no, capacity, occupied) VALUES (1, '101', 4, 1)");
    $db->exec("INSERT INTO hostel_allocations (student_id, room_id, start_date, status) VALUES (1, 1, '2025-09-15', 'active')");

    // 11. Notices
    $db->exec("INSERT INTO notices (title, content, target_role, priority, created_by) VALUES ('Welcome to 2025/2026 Session', 'Classes commence on September 15th.', 'all', 'normal', 1), ('First Term Exam Schedule', 'Exams begin December 1st.', 'student', 'important', 1), ('Staff Meeting', 'All staff meet Friday 2pm.', 'teacher', 'normal', 1)");

    echo "Seeding complete!<br>";
    echo "Login at <a href='/sms-peculiar-college/index.php'>/sms-peculiar-college</a><br><br>";
    echo "admin@peculiarcollege.edu.ng / Password@123<br>";
    echo "teacher@peculiarcollege.edu.ng / Password@123<br>";
    echo "student@peculiarcollege.edu.ng / Password@123<br>";
    echo "parent@peculiarcollege.edu.ng / Password@123<br>";
    echo "accountant@peculiarcollege.edu.ng / Password@123<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
