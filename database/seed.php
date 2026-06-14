<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

echo "Seeding database sms_peculiar_college...\n\n";

try {
    $db = getDB();

    // Helper
    function hashPwd($pwd) {
        return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    $pwd = hashPwd('Password@123');

    // 1. Academic Session
    $db->exec("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES
        ('2025/2026', '2025-09-01', '2026-08-31', 1, 'active')");

    // 2. Terms
    $db->exec("INSERT INTO terms (session_id, term_name, start_date, end_date, is_current) VALUES
        (1, 'First Term', '2025-09-15', '2025-12-19', 1),
        (1, 'Second Term', '2026-01-06', '2026-04-10', 0),
        (1, 'Third Term', '2026-04-27', '2026-08-14', 0)");

    // 3. Departments
    $db->exec("INSERT INTO departments (name, code, description) VALUES
        ('Science', 'SCI', 'Science Department'),
        ('Arts', 'ART', 'Arts and Humanities Department'),
        ('Commercial', 'COM', 'Commercial Studies Department')");

    // 4. Users (all roles)
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES
        (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['admin', 'admin@peculiarcollege.edu.ng', $pwd, 'Admin', 'User', '08012345670', 'admin']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES
        (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['teacher1', 'teacher@peculiarcollege.edu.ng', $pwd, 'John', 'Okafor', '08012345671', 'teacher']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES
        (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['teacher2', 'teacher2@peculiarcollege.edu.ng', $pwd, 'Sandra', 'Eze', '08012345672', 'teacher']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES
        (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['student1', 'student@peculiarcollege.edu.ng', $pwd, 'Chidi', 'Okonkwo', '08012345673', 'student']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES
        (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['parent1', 'parent@peculiarcollege.edu.ng', $pwd, 'Emeka', 'Okonkwo', '08012345674', 'parent']);
    $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES
        (?, ?, ?, ?, ?, ?, ?, 'active')")->execute(['accountant1', 'accountant@peculiarcollege.edu.ng', $pwd, 'Chioma', 'Nwosu', '08012345675', 'accountant']);

    echo "  Users created\n";

    // 5. Classes
    $db->exec("INSERT INTO classes (name, section, capacity, class_teacher_id, department_id, academic_session_id) VALUES
        ('JSS1', 'A', 40, 2, 1, 1),
        ('JSS1', 'B', 40, 3, 1, 1),
        ('JSS2', 'A', 40, NULL, 1, 1),
        ('JSS3', 'A', 35, NULL, 1, 1),
        ('SS1', 'Science', 35, NULL, 1, 1),
        ('SS1', 'Arts', 35, NULL, 2, 1),
        ('SS1', 'Commercial', 35, NULL, 3, 1),
        ('SS2', 'Science', 35, NULL, 1, 1),
        ('SS2', 'Arts', 35, NULL, 2, 1),
        ('SS3', 'Science', 30, NULL, 1, 1)");

    echo "  Classes created\n";

    // 6. Subjects (unique codes per row)
    $subjects = [
        [1, 'English Language', 'ENG_1', 1], [1, 'Mathematics', 'MTH_1', 1], [1, 'Basic Science', 'BSC_1', 1],
        [1, 'Social Studies', 'SST_1', 1], [1, 'Computer Studies', 'ICT_1', 1], [1, 'Civic Education', 'CIV_1', 1],
        [1, 'Agricultural Science', 'AGR_1', 1], [1, 'Home Economics', 'HME_1', 1], [1, 'Physical Education', 'PHE_1', 1],
        [2, 'English Language', 'ENG_2', 1], [2, 'Mathematics', 'MTH_2', 1], [2, 'Basic Science', 'BSC_2', 1],
        [2, 'Social Studies', 'SST_2', 1], [2, 'Computer Studies', 'ICT_2', 1], [2, 'Civic Education', 'CIV_2', 1],
        [2, 'Agricultural Science', 'AGR_2', 1], [2, 'Home Economics', 'HME_2', 1], [2, 'Physical Education', 'PHE_2', 1],
        [3, 'English Language', 'ENG_3', 1], [3, 'Mathematics', 'MTH_3', 1], [3, 'Basic Science', 'BSC_3', 1],
        [3, 'Computer Studies', 'ICT_3', 1], [3, 'Civic Education', 'CIV_3', 1], [3, 'Agricultural Science', 'AGR_3', 1],
        [3, 'Home Economics', 'HME_3', 1], [3, 'Social Studies', 'SST_3', 1],
        [4, 'English Language', 'ENG_4', 1], [4, 'Mathematics', 'MTH_4', 1], [4, 'Basic Science', 'BSC_4', 1],
        [4, 'Computer Studies', 'ICT_4', 1], [4, 'Civic Education', 'CIV_4', 1], [4, 'Agricultural Science', 'AGR_4', 1],
        [4, 'Social Studies', 'SST_4', 1],
        [5, 'English Language', 'ENG_5', 1], [5, 'Mathematics', 'MTH_5', 1], [5, 'Physics', 'PHY_5', 1],
        [5, 'Chemistry', 'CHM_5', 1], [5, 'Biology', 'BIO_5', 1], [5, 'Civic Education', 'CIV_5', 1],
        [5, 'Computer Studies', 'ICT_5', 1],
        [6, 'English Language', 'ENG_6', 1], [6, 'Mathematics', 'MTH_6', 1], [6, 'Literature in English', 'LIT_6', 1],
        [6, 'Government', 'GOV_6', 1], [6, 'Christian Religious Studies', 'CRS_6', 1], [6, 'Civic Education', 'CIV_6', 1],
        [6, 'Computer Studies', 'ICT_6', 1],
        [7, 'English Language', 'ENG_7', 1], [7, 'Mathematics', 'MTH_7', 1], [7, 'Economics', 'ECO_7', 1],
        [7, 'Commerce', 'COM_7', 1], [7, 'Financial Accounting', 'ACC_7', 1], [7, 'Civic Education', 'CIV_7', 1],
        [7, 'Computer Studies', 'ICT_7', 1],
        [8, 'English Language', 'ENG_8', 1], [8, 'Mathematics', 'MTH_8', 1], [8, 'Physics', 'PHY_8', 1],
        [8, 'Chemistry', 'CHM_8', 1], [8, 'Biology', 'BIO_8', 1], [8, 'Further Mathematics', 'FUR_8', 1],
        [8, 'Civic Education', 'CIV_8', 1], [8, 'Computer Studies', 'ICT_8', 1],
        [9, 'English Language', 'ENG_9', 1], [9, 'Mathematics', 'MTH_9', 1], [9, 'Literature in English', 'LIT_9', 1],
        [9, 'Government', 'GOV_9', 1], [9, 'Christian Religious Studies', 'CRS_9', 1], [9, 'Civic Education', 'CIV_9', 1],
        [9, 'Computer Studies', 'ICT_9', 1],
        [10, 'English Language', 'ENG_10', 1], [10, 'Mathematics', 'MTH_10', 1], [10, 'Physics', 'PHY_10', 1],
        [10, 'Chemistry', 'CHM_10', 1], [10, 'Biology', 'BIO_10', 1], [10, 'Further Mathematics', 'FUR_10', 1],
        [10, 'Civic Education', 'CIV_10', 1], [10, 'Computer Studies', 'ICT_10', 1],
    ];
    $subjStmt = $db->prepare("INSERT INTO subjects (class_id, name, code, teacher_id) VALUES (?, ?, ?, ?)");
    foreach ($subjects as $s) { $subjStmt->execute($s); }
    echo "  Subjects created\n";

    // 7. Teachers
    $db->exec("INSERT INTO teachers (user_id, employee_id, qualification, department_id, date_hired) VALUES
        (2, 'TCH001', 'B.Sc. Mathematics Education', 1, '2023-09-01'),
        (3, 'TCH002', 'B.A. English Studies', 2, '2023-09-01')");
    echo "  Teachers created\n";

    // 8. Subject allocations (teacher-class-subject) - look up by code
    $allocStmt = $db->prepare("INSERT INTO subject_allocations (teacher_id, class_id, subject_id, academic_session_id) VALUES (?, ?, ?, ?)");
    $getSubj = $db->prepare("SELECT id FROM subjects WHERE class_id = ? AND code = ?");
    // Teacher 2 (John Okafor) - Math for JSS1A and SS1 Science
    $getSubj->execute([1, 'MTH_1']); $math1 = $getSubj->fetchColumn();
    $getSubj->execute([5, 'MTH_5']); $math5 = $getSubj->fetchColumn();
    if ($math1) $allocStmt->execute([2, 1, $math1, 1]);
    if ($math5) $allocStmt->execute([2, 5, $math5, 1]);
    // Teacher 3 (Sandra Eze) - English for JSS1A and SS1 Arts
    $getSubj->execute([1, 'ENG_1']); $eng1 = $getSubj->fetchColumn();
    $getSubj->execute([6, 'ENG_6']); $eng6 = $getSubj->fetchColumn();
    if ($eng1) $allocStmt->execute([3, 1, $eng1, 1]);
    if ($eng6) $allocStmt->execute([3, 6, $eng6, 1]);
    echo "  Subject allocations created\n";

    // 9. Student
    $db->exec("INSERT INTO students (user_id, admission_no, class_id, date_of_birth, gender, enrollment_date, status) VALUES
        (4, 'PEC2025001', 1, '2012-04-15', 'male', '2025-09-15', 'active')");
    echo "  Student created\n";

    // 10. Parent
    $db->exec("INSERT INTO parents (user_id, occupation, relationship) VALUES
        (5, 'Business Owner', 'Father')");
    $db->exec("INSERT INTO student_parents (student_id, parent_id, is_guardian) VALUES (1, 1, 1)");
    echo "  Parent linked\n";

    // 11. Fee Structure
    $db->exec("INSERT INTO fee_structure (fee_name, amount, class_id, term_id, is_mandatory) VALUES
        ('Tuition Fee', 150000.00, 1, 1, 1),
        ('Development Levy', 25000.00, 1, 1, 1),
        ('Sports Fee', 15000.00, 1, 1, 0),
        ('Library Fee', 10000.00, 1, 1, 1)");
    echo "  Fee structure created\n";

    // 12. Fees assigned to student
    $db->exec("INSERT INTO fees (student_id, fee_structure_id, total_amount, balance, due_date, status) VALUES
        (1, 1, 150000.00, 150000.00, '2025-10-15', 'unpaid'),
        (1, 2, 25000.00, 25000.00, '2025-10-15', 'unpaid'),
        (1, 4, 10000.00, 10000.00, '2025-10-15', 'unpaid')");
    echo "  Student fees created\n";

    // 13. Books
    $bookStmt = $db->prepare("INSERT INTO books (title, author, isbn, publisher, category, quantity, available) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $bookStmt->execute(['New General Mathematics JSS1', 'J.B. Channon', '978-0-19-573821-1', 'Oxford University Press', 'Textbook', 20, 20]);
    $bookStmt->execute(['New Concept English JSS1', 'F. Ademola', '978-0-19-573821-2', 'Longman Nigeria', 'Textbook', 15, 15]);
    $bookStmt->execute(['Basic Science for JSS', 'S. Olaitan', '978-0-19-573821-3', 'Macmillan Nigeria', 'Textbook', 10, 10]);
    $bookStmt->execute(['Physics for SS1', 'P.N. Okeke', '978-0-19-573821-4', 'Africana Press', 'Textbook', 8, 8]);
    $bookStmt->execute(['The Great Gatsby', 'F. Scott Fitzgerald', '978-0-19-573821-5', 'Scribner', 'Fiction', 3, 3]);
    $bookStmt->execute(['Things Fall Apart', 'Chinua Achebe', '978-0-19-573821-6', 'Heinemann', 'Fiction', 5, 5]);
    echo "  Books created\n";

    // 14. Transport route
    $db->exec("INSERT INTO transport_routes (route_name, description, fee, driver_name, driver_phone, vehicle_no, status) VALUES
        ('Main Campus Route', 'Downtown to Main Campus via City Center', 35000.00, 'Musa Ibrahim', '08098765432', 'ABC-123XY', 'active')");
    echo "  Transport route created\n";

    // 15. Hostel
    $db->exec("INSERT INTO hostels (name, type, capacity, occupied, fee) VALUES
        ('Unity Hall', 'boys', 100, 1, 80000.00)");
    $db->exec("INSERT INTO hostel_rooms (hostel_id, room_no, capacity, occupied) VALUES
        (1, '101', 4, 1)");
    $db->exec("INSERT INTO hostel_allocations (student_id, room_id, start_date, status) VALUES
        (1, 1, '2025-09-15', 'active')");
    echo "  Hostel created\n";

    // 16. Notices
    $db->exec("INSERT INTO notices (title, content, target_role, priority, created_by) VALUES
        ('Welcome to 2025/2026 Session', 'We are pleased to welcome all students and parents to the new academic session. Classes commence on September 15th.', 'all', 'normal', 1),
        ('First Term Examination Schedule', 'First term examinations will begin on December 1st, 2025. All students must be present.', 'student', 'important', 1),
        ('Staff Meeting Notice', 'All staff are required to attend the general staff meeting on Friday at 2pm in the staff room.', 'teacher', 'normal', 1)");
    echo "  Notices created\n";

    // Seed result settings
    $stmt = $db->prepare("INSERT IGNORE INTO result_settings (session_id, term_id, ca_weight, exam_weight, pass_mark, grade_a_min, grade_b_min, grade_c_min, grade_d_min, grade_e_min) VALUES (?, ?, 40, 60, 40, 75, 60, 50, 40, 30)");
    $terms = $db->query("SELECT id FROM terms WHERE session_id = 1")->fetchAll();
    foreach ($terms as $t) {
        $stmt->execute([1, $t['id']]);
    }

    // Seed promotion config for JSS1
    $classIds = $db->query("SELECT id FROM classes WHERE academic_session_id = 1")->fetchAll(PDO::FETCH_COLUMN);
    $pStmt = $db->prepare("INSERT IGNORE INTO promotion_config (session_id, class_id, pass_mark, min_subjects_pass) VALUES (?, ?, 40, 5)");
    foreach ($classIds as $cid) {
        $pStmt->execute([1, $cid]);
    }

    echo "\nSeeding complete! Login credentials:\n";
    echo "  Admin:       admin@peculiarcollege.edu.ng / Password@123\n";
    echo "  Teacher:     teacher@peculiarcollege.edu.ng / Password@123\n";
    echo "  Student:     student@peculiarcollege.edu.ng / Password@123\n";
    echo "  Parent:      parent@peculiarcollege.edu.ng / Password@123\n";
    echo "  Accountant:  accountant@peculiarcollege.edu.ng / Password@123\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
