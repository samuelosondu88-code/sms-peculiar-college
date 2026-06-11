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
    $db->exec("INSERT INTO classes (name, section, code, capacity, class_teacher_id) VALUES
        ('JSS1', 'A', 'JSS1A', 40, 2),
        ('JSS1', 'B', 'JSS1B', 40, 3),
        ('JSS2', 'A', 'JSS2A', 40, NULL),
        ('JSS3', 'A', 'JSS3A', 35, NULL),
        ('SS1', 'Science', 'SS1SCI', 35, NULL),
        ('SS1', 'Arts', 'SS1ART', 35, NULL),
        ('SS1', 'Commercial', 'SS1COM', 35, NULL),
        ('SS2', 'Science', 'SS2SCI', 35, NULL),
        ('SS2', 'Arts', 'SS2ART', 35, NULL),
        ('SS3', 'Science', 'SS3SCI', 30, NULL)");

    echo "  Classes created\n";

    // 6. Subjects
    $subjects = [
        [1, 'Mathematics', 'MATH', 1], [1, 'English Language', 'ENG', 1], [1, 'Basic Science', 'BASCI', 1],
        [1, 'Social Studies', 'SOC', 1], [1, 'Computer Studies', 'COMPS', 1],
        [4, 'Mathematics', 'MATH', 1], [4, 'English Language', 'ENG', 1], [4, 'Basic Science', 'BASCI', 1],
        [5, 'Mathematics', 'MATH', 1], [5, 'English Language', 'ENG', 1], [5, 'Physics', 'PHY', 1],
        [5, 'Chemistry', 'CHM', 1], [5, 'Biology', 'BIO', 1],
        [6, 'English Language', 'ENG', 1], [6, 'Literature', 'LIT', 1], [6, 'Government', 'GOV', 1],
        [6, 'History', 'HIST', 1], [6, 'French', 'FREN', 1],
        [7, 'English Language', 'ENG', 1], [7, 'Economics', 'ECO', 1], [7, 'Accounting', 'ACCT', 1],
        [7, 'Commerce', 'COMM', 1], [7, 'Mathematics', 'MATH', 1],
    ];
    $subjStmt = $db->prepare("INSERT INTO subjects (class_id, name, code, teacher_id) VALUES (?, ?, ?, ?)");
    foreach ($subjects as $s) { $subjStmt->execute($s); }
    echo "  Subjects created\n";

    // 7. Teachers
    $db->exec("INSERT INTO teachers (user_id, employee_id, qualification, department_id, date_hired) VALUES
        (2, 'TCH001', 'B.Sc. Mathematics Education', 1, '2023-09-01'),
        (3, 'TCH002', 'B.A. English Studies', 2, '2023-09-01')");
    echo "  Teachers created\n";

    // 8. Subject allocations (teacher-class-subject)
    $allocStmt = $db->prepare("INSERT INTO subject_allocations (teacher_id, class_id, subject_id, academic_session_id) VALUES (?, ?, ?, 1)");
    // Teacher 2 (John Okafor) - Math for JSS1A and SS1 Science
    $allocStmt->execute([2, 1, 1, 1]); // Math JSS1A
    $allocStmt->execute([2, 5, 9, 1]);  // Math SS1 Science
    // Teacher 3 (Sandra Eze) - English for JSS1A and SS1 Arts
    $allocStmt->execute([3, 1, 2, 1]);  // English JSS1A
    $allocStmt->execute([3, 6, 14, 1]); // English SS1 Arts
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
