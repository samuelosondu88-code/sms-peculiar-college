<?php
/**
 * Test Data Seeder - Generates 1,000 students, 50 teachers, results, attendance, fees
 * 
 * Usage: php database/test_data_seeder.php [--reset] [--students=N] [--teachers=N]
 *   --reset      Reset all generated data (keeps base seed data)
 *   --students   Number of students to generate (default: 1000)
 *   --teachers   Number of teachers to generate (default: 50)
 *   --no-parents Skip parent account generation
 */

// Config
$OPT_STUDENTS  = 1000;
$OPT_TEACHERS  = 50;
$OPT_PARENTS   = 200;
$OPT_RESET     = false;
$OPT_NO_PARENTS = false;

// Parse CLI args
foreach ($argv ?? [] as $arg) {
    if ($arg === '--reset')             { $OPT_RESET = true; }
    if ($arg === '--no-parents')        { $OPT_NO_PARENTS = true; }
    if (preg_match('/^--students=(\d+)$/', $arg, $m)) { $OPT_STUDENTS = (int)$m[1]; }
    if (preg_match('/^--teachers=(\d+)$/', $arg, $m)) { $OPT_TEACHERS = (int)$m[1]; }
}

require_once __DIR__ . '/../config/database.php';

$db = getDB();

$START = microtime(true);

echo "=== SMS Peculiar College Test Data Seeder ===\n\n";

// ──────────────────────────────────────────────────────────────
// RESET SECTION
// ──────────────────────────────────────────────────────────────
if ($OPT_RESET) {
    echo "Resetting generated data...\n";
    // Delete in FK-safe order (children first)
    // Get existing tables safely
    $existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tables = [
        'result_scores', 'promotion_results', 'promotion_config',
        'class_attendance', 'class_discussions', 'assignment_submissions',
        'class_assignments', 'class_materials', 'class_enrollments',
        'virtual_classes', 'class_schedule',
        'lesson_notes', 'lesson_plans',
        'teacher_exam_scores', 'teacher_exam_answers', 'teacher_exam_submissions', 'teacher_exam_questions', 'teacher_exams',
        'cbt_results', 'cbt_answers', 'cbt_submissions', 'cbt_questions', 'cbt_exams',
        'payments', 'fees',
        'hostel_allocations', 'hostel_rooms',
        'book_borrowings',
        'attendance',
        'student_parents', 'parents',
        'subject_allocations',
        'students',
        'teachers',
        'users',
    ];
    foreach ($tables as $t) {
        if (!in_array($t, $existingTables)) {
            echo "  Skipped (not exists): {$t}\n";
            continue;
        }
        $db->exec("DELETE FROM `{$t}`");
        echo "  Cleared: {$t}\n";
    }
    // Reset auto-increment
    foreach ($tables as $t) {
        if (!in_array($t, $existingTables)) continue;
        try {
            $db->exec("ALTER TABLE `{$t}` AUTO_INCREMENT = 1");
        } catch (Exception $e) {
            // Some tables might not support this or may be empty after DELETE
        }
    }
    echo "Reset complete.\n\n";
}

// ──────────────────────────────────────────────────────────────
// DATA HELPER
// ──────────────────────────────────────────────────────────────
function hashPwd($pwd) {
    return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 10]);
}

function randScore($min, $max, $meanFactor = 0.7) {
    // Bell-ish distribution centered around meanFactor of range
    $range = $max - $min;
    $mean  = $min + $range * $meanFactor;
    $std   = $range * 0.18;
    $val   = round(gaussRandom($mean, $std));
    return max($min, min($max, $val));
}

function gaussRandom($mean, $std) {
    static $cache = null;
    if ($cache !== null) {
        $val = $cache;
        $cache = null;
        return $mean + $std * $val;
    }
    $u1 = mt_rand() / mt_getrandmax();
    $u2 = mt_rand() / mt_getrandmax();
    $r  = sqrt(-2 * log($u1));
    $t  = 2 * M_PI * $u2;
    $cache = $r * sin($t);
    return $mean + $std * ($r * cos($t));
}

function gradeFromScore($score) {
    if ($score >= 75) return 'A';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 40) return 'D';
    return 'F';
}

function dbBatchInsert($db, $table, $columns, $rows, $chunkSize = 200, $ignore = false) {
    if (empty($rows)) return 0;
    $ignoreStr = $ignore ? ' IGNORE' : '';
    $count = 0;
    foreach (array_chunk($rows, $chunkSize) as $chunk) {
        $placeholders = [];
        $values = [];
        foreach ($chunk as $row) {
            $ph = [];
            foreach ($row as $val) {
                $values[] = $val;
                $ph[] = '?';
            }
            $placeholders[] = '(' . implode(',', $ph) . ')';
        }
        $sql = "INSERT{$ignoreStr} INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES " . implode(',', $placeholders);
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        $count += $stmt->rowCount();
    }
    return $count;
}

// ──────────────────────────────────────────────────────────────
// NAMES DATA
// ──────────────────────────────────────────────────────────────
$NIGERIAN_FIRST_NAMES_MALE = [
    'Chidi', 'Emeka', 'Chuka', 'Obinna', 'Nnamdi', 'Chibueze', 'Ifeanyi', 'Chima', 'Ugochukwu', 'Kelechi',
    'Chinedu', 'Chibuzor', 'Chidiebere', 'Chidubem', 'Chigozie', 'Chimobi', 'Chinonso', 'Chinyere', 'Chukwudi', 'Chukwuma',
    'Chukwuemeka', 'Dumebi', 'Ebuka', 'Ekeoma', 'Ekene', 'Emeka', 'Enyinnaya', 'Ezinne', 'Gozie', 'Ikenna',
    'Ikechukwu', 'Ikemba', 'Izu', 'Izuchukwu', 'Kachi', 'Kosisochukwu', 'Kosy', 'Mazi', 'Nduka', 'Nnenna',
    'Nwabueze', 'Nwachinemere', 'Nwadiuto', 'Nwafor', 'Nwankwo', 'Nwosu', 'Obiora', 'Obinna', 'Ochie', 'Okechukwu',
    'Okeke', 'Okey', 'Okoro', 'Olamilekan', 'Olawale', 'Oluwadamilare', 'Oluwafemi', 'Oluwapelumi', 'Onyekachi', 'Onyinye',
    'Onyinyechi', 'Osita', 'Ozo', 'Ozoemena', 'Somtochukwu', 'Somto', 'Tochukwu', 'Tobe', 'Tochi', 'Uchenna',
    'Uche', 'Ugo', 'Ugochinyere', 'Ugochukwu', 'Ujunwa', 'Ukpai', 'Uloma', 'Uzochi', 'Uzochukwu', 'Uzodinma',
    'Uzoma', 'Chiamaka', 'Chidimma', 'Chika', 'Chikodi', 'Chimamanda', 'Chinwe', 'Chinweuba', 'Chinyelu', 'Chinyere',
    'Samuel', 'Daniel', 'David', 'Michael', 'Emmanuel', 'Joseph', 'Joshua', 'James', 'John', 'Peter',
    'Stephen', 'Andrew', 'Philip', 'Anthony', 'Mark', 'Paul', 'George', 'Victor', 'Francis', 'Benedict',
    'Patrick', 'Vincent', 'Martin', 'Raymond', 'Dominic', 'Christopher', 'Matthew', 'Luke', 'Timothy', 'Simon',
    'Amos', 'Isaac', 'Abraham', 'Solomon', 'Jeremiah', 'Isaiah', 'Jonah', 'Nathaniel', 'Ezekiel', 'Gabriel',
    'Raphael', 'Festus', 'Innocent', 'Prosper', 'Miracle', 'Precious', 'Destiny', 'Favour', 'Wisdom', 'Justice',
];

$NIGERIAN_FIRST_NAMES_FEMALE = [
    'Chioma', 'Nkechi', 'Amara', 'Chiamaka', 'Chidinma', 'Chika', 'Chikodi', 'Chimamanda', 'Chinaza', 'Chinwe',
    'Chinweuba', 'Chinyelu', 'Chinyere', 'Ebere', 'Ezinne', 'Ifeoma', 'Ijeoma', 'Ijezie', 'Kelechi', 'Mmerichukwu',
    'Nchedochukwu', 'Ngozi', 'Njideka', 'Nkechinyere', 'Nnenna', 'Nneoma', 'Nwadiuto', 'Nwanneka', 'Ogechi', 'Ogochukwu',
    'Olabisi', 'Olamide', 'Olawumi', 'Oluwadamilola', 'Oluwafunmilayo', 'Oluwaseun', 'Oluwaseyi', 'Oluwatobiloba', 'Oluwayemisi', 'Onyeka',
    'Onyinye', 'Onyinyechi', 'Sandra', 'Somtochukwu', 'Tochukwu', 'Uchenna', 'Uchechi', 'Ugochinyere', 'Ujunwa', 'Uloma',
    'Uzochi', 'Uzoma', 'Zinachidi', 'Chisom', 'Chizoba', 'Chizara', 'Adanna', 'Adaobi', 'Adaeze', 'Adaku',
    'Chiamaka', 'Chibueze', 'Chidimma', 'Chidinma', 'Chika', 'Chikodi', 'Chimamanda', 'Chinaza', 'Chinwe', 'Chinweuba',
    'Blessing', 'Grace', 'Peace', 'Faith', 'Mercy', 'Charity', 'Prudence', 'Amanda', 'Pamela', 'Esther',
    'Ruth', 'Sarah', 'Mary', 'Martha', 'Deborah', 'Rebecca', 'Hannah', 'Naomi', 'Judith', 'Elizabeth',
    'Catherine', 'Margaret', 'Helen', 'Ann', 'Alice', 'Rose', 'Lucy', 'Agnes', 'Monica', 'Dorothy',
    'Joy', 'Glory', 'Queen', 'Victoria', 'Beatrice', 'Florence', 'Evelyn', 'Doris', 'Lillian', 'Gloria',
];

$SURNAMES = [
    'Okonkwo', 'Eze', 'Okafor', 'Nwosu', 'Obi', 'Igwe', 'Nwachukwu', 'Okeke', 'Onyema', 'Nwankwo',
    'Okechukwu', 'Ezeiruaku', 'Chukwuma', 'Onyekachi', 'Chibueze', 'Nnamdi', 'Uzodinma', 'Ikechukwu', 'Ogbonna', 'Ezeh',
    'Nwafor', 'Okoro', 'Okafor', 'Okpara', 'Nwankwo', 'Okezie', 'Nwadike', 'Ozoemena', 'Chinedu', 'Nneji',
    'Okoli', 'Ezeugwu', 'Nwodo', 'Oha', 'Ogbodo', 'Ohemu', 'Okereke', 'Oko', 'Okonkwo', 'Okorie',
    'Onyishi', 'Otu', 'Ozougwu', 'Ubah', 'Uche', 'Ude', 'Udeh', 'Udenze', 'Udo', 'Udu',
    'Ugwueze', 'Ugwuegede', 'Ugwu', 'Ugwuanyi', 'Uka', 'Ukanwa', 'Ukandu', 'Ukonu', 'Ukpabi', 'Ukwu',
    'Umenweke', 'Umeh', 'Umerah', 'Umezuruike', 'Umunna', 'Umuokoro', 'Unachukwu', 'Unaka', 'Unegbu', 'Urama',
    'Uroko', 'Usman', 'Uzo', 'Uzodinma', 'Uzoukwu', 'Yusuf', 'Zagha', 'Zideon', 'Bello', 'Yahaya',
    'Musa', 'Mohammed', 'Abubakar', 'Adamu', 'Aliyu', 'Sani', 'Ibrahim', 'Abdullahi', 'Usman', 'Danjuma',
    'Adeyemi', 'Adeyinka', 'Adebayo', 'Adebisi', 'Adegoke', 'Adekunle', 'Adeniyi', 'Adeola', 'Adepoju', 'Adesina',
    'Adigun', 'Adisa', 'Ajayi', 'Akinlade', 'Akinsanya', 'Akintola', 'Akinyemi', 'Alabi', 'Alamu', 'Alonge',
    'Anjorin', 'Aremu', 'Ariyo', 'Ashaolu', 'Awolowo', 'Ayeni', 'Ayodele', 'Balogun', 'Bankole', 'Bassey',
    'Ephraim', 'Etim', 'Etuk', 'Eyong', 'Johnson', 'Kalada', 'Kalu', 'Benson', 'Ekong', 'Inyang',
];

// ──────────────────────────────────────────────────────────────
// CLASS CONFIG
// ──────────────────────────────────────────────────────────────
$CLASSES = [];
$classStmt = $db->query("SELECT id, name, section FROM classes ORDER BY id");
while ($row = $classStmt->fetch()) {
    $CLASSES[$row['id']] = $row;
}

if (empty($CLASSES)) {
    die("ERROR: No classes found. Run the base seed script first (database/seed.php).\n");
}

// Subject IDs per class
$SUBJECTS_BY_CLASS = [];
$subjStmt = $db->query("SELECT id, class_id, name FROM subjects ORDER BY id");
while ($row = $subjStmt->fetch()) {
    $SUBJECTS_BY_CLASS[$row['class_id']][] = $row;
}

echo "Found " . count($CLASSES) . " classes, subjects loaded.\n\n";

// Student distribution across classes
$STUDENT_DIST = [
    1 => 120, 2 => 120, 3 => 110, 4 => 100,
    5 => 100, 6 => 80,  7 => 80,
    8 => 100, 9 => 80,  10 => 110,
];
// Adjust based on OPT_STUDENTS
$totalDist = array_sum($STUDENT_DIST);
$scaleFactor = $OPT_STUDENTS / $totalDist;
foreach ($STUDENT_DIST as $k => $v) {
    $STUDENT_DIST[$k] = (int)round($v * $scaleFactor);
}
// Ensure exact total
$diff = $OPT_STUDENTS - array_sum($STUDENT_DIST);
if ($diff > 0) $STUDENT_DIST[1] += $diff;
elseif ($diff < 0) $STUDENT_DIST[10] += $diff;

echo "Student distribution:\n";
foreach ($STUDENT_DIST as $cid => $count) {
    echo "  {$CLASSES[$cid]['name']} {$CLASSES[$cid]['section']}: {$count}\n";
}
echo "\n";

try {

// ═══════════════════════════════════════════════════════════════
// 1. CREATE TEACHERS
// ═══════════════════════════════════════════════════════════════
echo "--- Creating {$OPT_TEACHERS} teachers...\n";
$db->beginTransaction();

$teacherUserIds = [];
$teacherRecords = [];
$teacherSubjects = []; // teacher_id => [class_id => [subject_id, ...]]

    // Get next available user ID
    $nextUserId = (int)$db->query("SELECT MAX(id) + 1 FROM users")->fetchColumn();
    if ($nextUserId < 7) $nextUserId = 7;

// We already have 2 teachers (IDs 1,2 from seed) and teacher3 (ID 4 from seed)
// Let's count existing teachers
$existingTeachers = (int)$db->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
echo "  Existing teachers: {$existingTeachers}\n";

$firstNamePool = array_merge($NIGERIAN_FIRST_NAMES_MALE, $NIGERIAN_FIRST_NAMES_FEMALE);
shuffle($firstNamePool);

$teacherDataRows = [];
$userTeacherRows = []; // [username, email, password_hash, first_name, last_name, phone, role, status]

$departments = [1, 2, 3]; // Science, Arts, Commercial
$qualifications = [
    'B.Sc. in Education', 'B.A. in Education', 'B.Ed.',
    'M.Sc. in Education', 'M.A. in Education', 'M.Ed.',
    'B.Sc. with PGDE', 'B.A. with PGDE', 'B.Ed. (Hons)',
    'B.Sc. in Mathematics Education', 'B.A. in English Education',
    'B.Sc. in Science Education', 'B.A. in Arts Education',
    'M.Sc. in Mathematics Education', 'M.A. in English Education',
    'B.Sc. + M.Sc. in Education',
];

$emailDomains = ['gmail.com', 'yahoo.com', 'outlook.com', 'peculiarcollege.edu.ng'];

for ($i = 1; $i <= $OPT_TEACHERS - $existingTeachers; $i++) {
    $uid = $nextUserId++;
    $fname = $firstNamePool[($i * 3) % count($firstNamePool)];
    $lname = $SURNAMES[$i % count($SURNAMES)];
    $username = 'teacher' . $uid;
    $email   = strtolower($fname . '.' . $lname . $uid) . '@' . $emailDomains[$i % count($emailDomains)];
    $phone   = '080' . str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

    $userTeacherRows[] = [$username, $email, hashPwd('Password@123'), $fname, $lname, $phone, 'teacher', 'active'];
    $empId = 'TCH' . str_pad($uid, 4, '0', STR_PAD_LEFT);
    $dept  = $departments[($i - 1) % count($departments)];
    $qual  = $qualifications[($i - 1) % count($qualifications)];
    $hire  = sprintf('202%d-%02d-%02d', mt_rand(3, 5), mt_rand(1, 12), mt_rand(1, 28));
    $teacherDataRows[] = [$uid, $empId, $qual, $dept, $hire];
    $teacherUserIds[] = $uid;
}

// Batch INSERT users
$userCols = ['username', 'email', 'password_hash', 'first_name', 'last_name', 'phone', 'role', 'status'];
$inserted = dbBatchInsert($db, 'users', $userCols, $userTeacherRows);
echo "  Inserted {$inserted} teacher user accounts\n";

// Map the new user IDs back - we need to get teacher IDs
// Since auto-increment is sequential, we can calculate them
// But safer: get them directly
$db->commit();

// Get the actual teacher user IDs (newly inserted ones)
$db->beginTransaction(); // new tx for next batch
$allTeacherUsers = $db->query("SELECT id FROM users WHERE role = 'teacher' AND id NOT IN (SELECT user_id FROM teachers WHERE user_id IS NOT NULL) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$actualTeacherUserIds = $allTeacherUsers;

// Batch INSERT teachers (user_id, employee_id, qualification, department_id, date_hired)
$teacherInsertRows = [];
foreach ($actualTeacherUserIds as $idx => $uid) {
    $td = $teacherDataRows[$idx] ?? null;
    if (!$td) continue;
    $teacherInsertRows[] = [$uid, $td[1], $td[2], $td[3], $td[4]];
}
$insertedT = dbBatchInsert($db, 'teachers', ['user_id', 'employee_id', 'qualification', 'department_id', 'date_hired'], $teacherInsertRows);
echo "  Inserted {$insertedT} teacher records\n";
$db->commit();

// Get all teacher IDs (including existing ones)
$allTeachers = $db->query("SELECT id, user_id FROM teachers ORDER BY id")->fetchAll();
$teacherIdMap = []; // user_id => teacher_id
foreach ($allTeachers as $t) {
    $teacherIdMap[$t['user_id']] = $t['id'];
}

$totalTeachers = count($allTeachers);
echo "  Total teachers now: {$totalTeachers}\n\n";

// ═══════════════════════════════════════════════════════════════
// 2. SUBJECT ALLOCATIONS
// ═══════════════════════════════════════════════════════════════
echo "--- Assigning teachers to subjects...\n";
$db->beginTransaction();

// Clear existing subject_allocations (but keep the ones we seeded)
$db->exec("DELETE FROM subject_allocations");

// Session 1 = current
$sessionId = 1;

// Build assignments: for each class, for each subject, assign a teacher
$allocationRows = [];
$teacherIndex = 0;
$numTeachers = count($allTeachers);

foreach ($SUBJECTS_BY_CLASS as $classId => $subjects) {
    foreach ($subjects as $subj) {
        $teacherId = $allTeachers[$teacherIndex % $numTeachers]['id'];
        $teacherIndex++;
        $allocationRows[] = [$teacherId, $classId, $subj['id'], $sessionId];
    }
}

$allocCols = ['teacher_id', 'class_id', 'subject_id', 'academic_session_id'];
$insertedA = dbBatchInsert($db, 'subject_allocations', $allocCols, $allocationRows);
echo "  Created {$insertedA} subject-teacher-class assignments\n";
$db->commit();

// Re-query subject_allocations to build lookup
$saStmt = $db->query("SELECT sa.teacher_id, sa.class_id, sa.subject_id, t.user_id
    FROM subject_allocations sa
    JOIN teachers t ON sa.teacher_id = t.id");
$teacherSubjects = [];
while ($row = $saStmt->fetch()) {
    $teacherSubjects[$row['teacher_id']]['classes'][$row['class_id']][] = $row['subject_id'];
}

echo "\n";

// ═══════════════════════════════════════════════════════════════
// 3. CREATE STUDENTS
// ═══════════════════════════════════════════════════════════════
echo "--- Creating {$OPT_STUDENTS} students...\n";
$db->beginTransaction();

$admissionNo = (int)$db->query("SELECT MAX(CAST(SUBSTRING(admission_no, 8) AS UNSIGNED)) FROM students")->fetchColumn();
if (!$admissionNo) $admissionNo = 0;
$admissionNo++; // start from next available (PEC2025001 exists → start at 2)

$studentUserRows = [];
$studentDataRows = [];

$genders = ['male', 'female'];

foreach ($STUDENT_DIST as $classId => $count) {
    $classInfo = $CLASSES[$classId];
    $subjectsForClass = $SUBJECTS_BY_CLASS[$classId] ?? [];
    
    for ($s = 0; $s < $count; $s++) {
        $uid = $nextUserId++;
        $gender = $genders[mt_rand(0, 1)];
        $firstNamePool2 = ($gender === 'male') ? $NIGERIAN_FIRST_NAMES_MALE : $NIGERIAN_FIRST_NAMES_FEMALE;
        $fname = $firstNamePool2[mt_rand(0, count($firstNamePool2) - 1)];
        $lname = $SURNAMES[mt_rand(0, count($SURNAMES) - 1)];
        
        $suffix = str_pad($uid, 4, '0', STR_PAD_LEFT);
        $username = 'student' . $suffix;
        $email   = strtolower($fname . '.' . $lname . $suffix) . '@peculiarcollege.edu.ng';
        $phone   = '080' . str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        $studentUserRows[] = [$username, $email, hashPwd('Password@123'), $fname, $lname, $phone, 'student', 'active'];
        
        $admNo = 'PEC2025' . str_pad($admissionNo++, 3, '0', STR_PAD_LEFT);
        $dob   = sprintf('%d-%02d-%02d', mt_rand(2006, 2014), mt_rand(1, 12), mt_rand(1, 28));
        $enr   = '2025-09-' . str_pad(mt_rand(10, 20), 2, '0', STR_PAD_LEFT);
        
        $studentDataRows[] = [$uid, $admNo, $classId, $dob, $gender, $enr, 'active'];
    }
}

// Batch INSERT student users
$insertedSU = dbBatchInsert($db, 'users', $userCols, $studentUserRows);
echo "  Inserted {$insertedSU} student user accounts\n";
$db->commit();

    // Get the actual student user IDs (newly inserted)
    $allStudentUsers = $db->query("SELECT id FROM users WHERE role = 'student' AND id NOT IN (SELECT user_id FROM students WHERE user_id IS NOT NULL) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $actualStudentUserIds = $allStudentUsers;

// Batch INSERT students
$studentCols = ['user_id', 'admission_no', 'class_id', 'date_of_birth', 'gender', 'enrollment_date', 'status'];
$insertedS = dbBatchInsert($db, 'students', $studentCols, $studentDataRows);
echo "  Inserted {$insertedS} student records\n";

// Get all student IDs with class info
$allStudents = $db->query("SELECT s.id, s.user_id, s.class_id, s.admission_no FROM students s ORDER BY s.id")->fetchAll();
$studentIdsByClass = [];
foreach ($allStudents as $st) {
    $studentIdsByClass[$st['class_id']][] = $st;
}

$totalStudents = count($allStudents);
echo "  Total students now: {$totalStudents}\n";
echo "\n";

// ═══════════════════════════════════════════════════════════════
// 4. CREATE PARENTS & LINK TO STUDENTS
// ═══════════════════════════════════════════════════════════════
$numParents = $OPT_NO_PARENTS ? 0 : min($OPT_PARENTS, (int)($totalStudents / 4));
if ($numParents > 0) {
    echo "--- Creating {$numParents} parent accounts...\n";
    $db->beginTransaction();

    $parentUserRows = [];
    $parentDataRows = [];
    $studentParentRows = [];

    for ($i = 0; $i < $numParents; $i++) {
        $uid = $nextUserId++;
        $fname = $NIGERIAN_FIRST_NAMES_MALE[mt_rand(0, count($NIGERIAN_FIRST_NAMES_MALE) - 1)];
        $lname = $SURNAMES[mt_rand(0, count($SURNAMES) - 1)];
        $username = 'parent' . $uid;
        $email   = strtolower($fname . '.' . $lname . $uid) . '@gmail.com';
        $phone   = '080' . str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        
        $parentUserRows[] = [$username, $email, hashPwd('Password@123'), $fname, $lname, $phone, 'parent', 'active'];
        $occupation = ['Business Owner', 'Civil Servant', 'Teacher', 'Engineer', 'Doctor', 'Lawyer', 'Accountant', 'Trader', 'Farmer', 'Banker'][mt_rand(0, 9)];
        $relationship = ['Father', 'Mother', 'Guardian', 'Uncle', 'Aunt'][mt_rand(0, 4)];
        $parentDataRows[] = [$uid, $occupation, $relationship];
    }

    dbBatchInsert($db, 'users', $userCols, $parentUserRows);
    $db->commit();
    $db->beginTransaction();

    $allParentUsers = $db->query("SELECT id FROM users WHERE role = 'parent' AND id NOT IN (SELECT user_id FROM parents WHERE user_id IS NOT NULL) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

    $parentInsertRows = [];
    foreach ($allParentUsers as $idx => $uid) {
        $pd = $parentDataRows[$idx];
        $parentInsertRows[] = [$uid, $pd[1], $pd[2]];
    }
    dbBatchInsert($db, 'parents', ['user_id', 'occupation', 'relationship'], $parentInsertRows);
    $db->commit();

    // Get all parent IDs
    $allParentRecords = $db->query("SELECT id, user_id FROM parents ORDER BY id")->fetchAll();

    // Link each student to a parent
    $db->beginTransaction();
    $spRows = [];
    $pIdx = 0;
    $numParentRecords = count($allParentRecords);
    foreach ($allStudents as $st) {
        $parent = $allParentRecords[$pIdx % $numParentRecords];
        $isGuardian = ($pIdx % 5 === 0) ? 1 : 0;
        $spRows[] = [$st['id'], $parent['id'], $isGuardian];
        $pIdx++;
    }
    $insertedSP = dbBatchInsert($db, 'student_parents', ['student_id', 'parent_id', 'is_guardian'], $spRows, 200, true);
    echo "  Created {$numParents} parents, linked {$insertedSP} students\n";
    $db->commit();
} else {
    echo "--- Skipping parent creation (--no-parents or insufficient students)\n";
}

echo "\n";

// ═══════════════════════════════════════════════════════════════
// 5. FEE STRUCTURE (for all classes and terms)
// ═══════════════════════════════════════════════════════════════
echo "--- Setting up fee structure...\n";
$db->beginTransaction();

$terms = $db->query("SELECT id FROM terms WHERE session_id = 1")->fetchAll(PDO::FETCH_COLUMN);

// Fee names per class type
$jssFees = [
    ['Tuition Fee', 80000.00, 1],
    ['Development Levy', 20000.00, 1],
    ['Sports Fee', 10000.00, 0],
    ['Library Fee', 8000.00, 1],
    ['ICT Fee', 5000.00, 1],
    ['Science Lab Fee', 3000.00, 0],
];
$sssFees = [
    ['Tuition Fee', 120000.00, 1],
    ['Development Levy', 25000.00, 1],
    ['Sports Fee', 12000.00, 0],
    ['Library Fee', 10000.00, 1],
    ['ICT Fee', 8000.00, 1],
    ['Science Lab Fee', 10000.00, 1],
];

$feeStructureRows = [];
$feeStructureMap = []; // class_id => [term_id => [fee_name => id]]

foreach ($CLASSES as $cid => $classInfo) {
    $isJss = strpos($classInfo['name'], 'SS') === false;
    $fees = $isJss ? $jssFees : $sssFees;
    $classInfo['name'];
    foreach ($terms as $tid) {
        foreach ($fees as $fee) {
            $feeStructureRows[] = [$fee[0], $fee[1], $cid, $tid, $fee[2]];
        }
    }
}

// Insert fee structure
$db->exec("DELETE FROM fee_structure"); // clear old ones
$fsCols = ['fee_name', 'amount', 'class_id', 'term_id', 'is_mandatory'];
$insertedFS = dbBatchInsert($db, 'fee_structure', $fsCols, $feeStructureRows);
echo "  Created {$insertedFS} fee structure entries\n";
$db->commit();

// Get fee structure IDs
$allFeeStructures = $db->query("SELECT id, class_id, term_id, fee_name, amount FROM fee_structure ORDER BY class_id, term_id")->fetchAll();
$fsByClassTerm = [];
foreach ($allFeeStructures as $fs) {
    $fsByClassTerm[$fs['class_id']][$fs['term_id']][] = $fs;
}

// ═══════════════════════════════════════════════════════════════
// 6. STUDENT FEES
// ═══════════════════════════════════════════════════════════════
echo "--- Generating fee records for all students...\n";
$db->beginTransaction();

$feeRows = [];
$paymentRows = [];
$now = date('Y-m-d');
$paymentMethods = ['cash', 'card', 'transfer', 'pos'];

$studentCount = count($allStudents);
foreach ($allStudents as $si => $st) {
    $cid = $st['class_id'];
    $sid = $st['id'];
    $fsByTerm = $fsByClassTerm[$cid] ?? [];
    
    foreach ($fsByTerm as $tid => $feeItems) {
        foreach ($feeItems as $fs) {
            $totalAmt = $fs['amount'];
            
            $rem = ($si + 1) % 10;
            if ($rem < 3) {
                $paidAmt = $totalAmt;
                $status = 'paid';
            } elseif ($rem < 6) {
                $paidAmt = round($totalAmt * (mt_rand(30, 70) / 100), 2);
                $status = 'partial';
            } elseif ($rem < 8) {
                $paidAmt = 0;
                $status = 'unpaid';
            } else {
                $paidAmt = $totalAmt;
                $status = 'paid';
            }

            $balance = round($totalAmt - $paidAmt, 2);
            $dueDate = sprintf('2025-%02d-15', ($tid == 1) ? 10 : (($tid == 2) ? 2 : 5));
            
            $feeRows[] = [$sid, $fs['id'], $totalAmt, $paidAmt, $balance, $dueDate, $status];
        }
    }
}

$feeCols = ['student_id', 'fee_structure_id', 'total_amount', 'paid_amount', 'balance', 'due_date', 'status'];
$insertedFees = dbBatchInsert($db, 'fees', $feeCols, $feeRows);
echo "  Created {$insertedFees} fee records\n";
$db->commit();

// Now query back fee IDs to build payments
echo "  Building payment records from fee data...\n";
$allFees = $db->query("SELECT f.id, f.fee_structure_id, f.paid_amount, f.student_id, f.due_date 
    FROM fees f WHERE f.paid_amount > 0")->fetchAll();

$paymentRows = [];
foreach ($allFees as $fee) {
    $paidAmt = (float)$fee['paid_amount'];
    if ($paidAmt <= 0) continue;
    
    $numPayments = mt_rand(1, 3);
    $remaining = $paidAmt;
    for ($p = 0; $p < $numPayments; $p++) {
        $thisPayment = ($p < $numPayments - 1) 
            ? round($remaining * mt_rand(30, 70) / 100, 2) 
            : $remaining;
        $thisPayment = round($thisPayment, 2);
        if ($thisPayment <= 0) continue;
        $remaining -= $thisPayment;
        
        static $paySeq = 10000;
        $paySeq++;
        $ref = 'TRX' . str_pad($paySeq, 10, '0', STR_PAD_LEFT);
        $receipt = 'RCP' . str_pad($paySeq, 10, '0', STR_PAD_LEFT);
        $payDate = date('Y-m-d', strtotime($fee['due_date'] . ' -' . mt_rand(0, 30) . ' days'));
        $method  = $paymentMethods[mt_rand(0, 3)];

        $paymentRows[] = [$fee['id'], $thisPayment, $method, $ref, $receipt, $payDate, null, null, 'approved'];
    }
}

$db->beginTransaction();
$payCols = ['fee_id', 'amount_paid', 'payment_method', 'transaction_ref', 'receipt_no', 'payment_date', 'verified_by', 'proof_document', 'status'];
$insertedPays = dbBatchInsert($db, 'payments', $payCols, $paymentRows);
echo "  Created {$insertedPays} payment records\n";
$db->commit();

echo "\n";

// ═══════════════════════════════════════════════════════════════
// 7. ATTENDANCE RECORDS
// ═══════════════════════════════════════════════════════════════
echo "--- Generating attendance records for First Term...\n";
$db->beginTransaction();

$termStart = '2025-09-15';
$termEnd   = '2025-12-19';
$schoolDays = [];
$current = strtotime($termStart);
$end     = strtotime($termEnd);
while ($current <= $end) {
    $dow = (int)date('w', $current);
    if ($dow >= 1 && $dow <= 5) { // Monday-Friday
        $schoolDays[] = date('Y-m-d', $current);
    }
    $current = strtotime('+1 day', $current);
}

echo "  School days in term: " . count($schoolDays) . "\n";

$statuses = ['present', 'present', 'present', 'present', 'present', 'present', 'present', 'present', 'absent', 'late'];
// Use first admin user as marked_by
$markedBy = 1;

// Generate attendance for all students (batch)
$studentChunkSize = 50;
$studentChunks = array_chunk($allStudents, $studentChunkSize);
$attCount = 0;

foreach ($studentChunks as $sChunk) {
    $attRows = [];
    foreach ($sChunk as $st) {
        $classId = $st['class_id'];
        foreach ($schoolDays as $day) {
            $status = $statuses[mt_rand(0, 9)];
            $attRows[] = [$st['id'], $classId, $day, $status, null, $markedBy];
        }
    }
    $attCols = ['student_id', 'class_id', 'date', 'status', 'remark', 'marked_by'];
    $attCount += dbBatchInsert($db, 'attendance', $attCols, $attRows, 500);
    echo "  Attendance: {$attCount} records so far...\n";
}

echo "  Total attendance records: {$attCount}\n";
$db->commit();

echo "\n";

// ═══════════════════════════════════════════════════════════════
// 8. RESULTS — First, Second, Third Terms
// ═══════════════════════════════════════════════════════════════
echo "--- Generating results for all 3 terms...\n";
$db->beginTransaction();

// Get result_settings for all terms
$rsStmt = $db->query("SELECT id, session_id, term_id, ca_max, max_exam FROM result_settings WHERE session_id = 1");
$resultSettings = [];
while ($row = $rsStmt->fetch()) {
    $resultSettings[$row['term_id']] = $row;
}

// For each term
$termLabels = [1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'];
$totalResultRows = 0;

foreach ([1, 2, 3] as $tid) {
    $settings = $resultSettings[$tid] ?? ['ca_max' => 40, 'max_exam' => 60];
    $caMax = $settings['ca_max'];
    $examMax = $settings['max_exam'];
    
    echo "  {$termLabels[$tid]}: ";
    
    $resultRows = [];
    // Get students by class, for each class compute their subjects
    foreach ($studentIdsByClass as $classId => $students) {
        $subjects = $SUBJECTS_BY_CLASS[$classId] ?? [];
        if (empty($subjects)) continue;
        
        // Find teachers for these subjects
        $allocStmt = $db->prepare("SELECT subject_id, teacher_id FROM subject_allocations 
            WHERE class_id = ? AND academic_session_id = 1");
        $allocStmt->execute([$classId]);
        $allocMap = [];
        while ($a = $allocStmt->fetch()) {
            $allocMap[$a['subject_id']] = $a['teacher_id'];
        }
        
        foreach ($students as $st) {
            foreach ($subjects as $subj) {
                $subjId = $subj['id'];
                
                // Generate realistic scores with variation per term
                // Term 1: lower, Term 2: improving, Term 3: best
                $termBias = ($tid == 1) ? 0.55 : (($tid == 2) ? 0.65 : 0.70);
                
                $assign1 = randScore(0, 10, $termBias * 0.9);
                $assign2 = randScore(0, 10, $termBias * 0.9);
                $test1   = randScore(0, 10, $termBias * 0.9);
                $test2   = randScore(0, 10, $termBias * 0.9);
                $caTotal = min($caMax, round($assign1 + $assign2 + $test1 + $test2));
                
                // Make CA total correlate somewhat with exam
                $examMean = $termBias * $examMax;
                $examStd  = $examMax * 0.15;
                $examScore = round(gaussRandom($examMean, $examStd));
                $examScore = max(0, min($examMax, $examScore));
                
                $totalScore = $caTotal + $examScore;
                $grade = gradeFromScore($totalScore);
                
                $teacherId = $allocMap[$subjId] ?? 1; // fallback to admin
                
                $resultRows[] = [
                    $st['id'], $classId, $subjId, 1, $tid,
                    $assign1, $assign2, $test1, $test2, $caTotal,
                    $examScore, $totalScore, $grade,
                    'published', $teacherId
                ];
            }
        }
    }
    
    $resCols = [
        'student_id', 'class_id', 'subject_id', 'session_id', 'term_id',
        'assignment_score', 'assignment2_score', 'test_score', 'test2_score', 'ca_total',
        'exam_score', 'total_score', 'grade',
        'status', 'entered_by'
    ];
    
    $insertedRes = dbBatchInsert($db, 'result_scores', $resCols, $resultRows, 300);
    $totalResultRows += $insertedRes;
    echo "{$insertedRes} rows\n";
}

echo "  Total result records: {$totalResultRows}\n";
$db->commit();

// ═══════════════════════════════════════════════════════════════
// 9. COMPUTE CLASS POSITIONS
// ═══════════════════════════════════════════════════════════════
echo "\n--- Computing class positions and averages...\n";
$db->beginTransaction();

// For each class, term, subject, compute positions
$updatedPositions = 0;

foreach ($CLASSES as $cid => $classInfo) {
    foreach ([1, 2, 3] as $tid) {
        // Get all results for this class + term + subject, ordered by total_score descending
        $posStmt = $db->prepare("
            SELECT rs.id, rs.total_score, rs.subject_id, s.id as student_id
            FROM result_scores rs
            JOIN students s ON rs.student_id = s.id
            WHERE s.class_id = ? AND rs.term_id = ? AND rs.session_id = 1
            ORDER BY rs.subject_id, rs.total_score DESC
        ");
        $posStmt->execute([$cid, $tid]);
        $results = $posStmt->fetchAll();
        
        $currentSubject = null;
        $position = 0;
        $prevScore = null;
        
        // We need a simple UPDATE for each - let's use a prepared statement
        $updateStmt = $db->prepare("UPDATE result_scores SET subject_position = ? WHERE id = ?");
        
        foreach ($results as $r) {
            if ($r['subject_id'] !== $currentSubject) {
                $currentSubject = $r['subject_id'];
                $position = 0;
                $prevScore = null;
            }
            $position++;
            if ($r['total_score'] === $prevScore) {
                // Tie - keep same position, but we'll adjust
                // Actually, same position for ties is typical
                $position--; // will be incremented back effectively
                // Let's just keep the same position for ties
                $position--; // back to previous
                $position = max(1, $position);
            }
            $prevScore = $r['total_score'];
            $updateStmt->execute([$position, $r['id']]);
            $updatedPositions++;
        }
    }
}

echo "  Updated {$updatedPositions} subject positions\n";
$db->commit();

// ═══════════════════════════════════════════════════════════════
// DONE
// ═══════════════════════════════════════════════════════════════
$elapsed = round(microtime(true) - $START, 2);
echo "\n=== Seeding Complete! ===\n";
echo "Time: {$elapsed}s\n";
echo "Students: {$totalStudents}\n";
echo "Teachers: {$totalTeachers}\n";
echo "Results: {$totalResultRows}\n\n";
echo "Login credentials for all accounts: Password@123\n";
echo "Student emails: firstname.lastnameXXXX@peculiarcollege.edu.ng\n";
echo "Teacher emails: teacherXXXX@peculiarcollege.edu.ng\n";
echo "Parent emails:  parentXXXX@gmail.com\n";

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
