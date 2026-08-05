<?php
/**
 * Curriculum Seeder — Nigerian Secondary School Curriculum 2025/2026.
 *
 * Builds a complete, realistic demo database:
 *   - Session, terms, discipline-based departments
 *   - JSS1–3 (A/B) + SS1–3 (Science/Arts/Commercial) classes
 *   - Full national-curriculum subjects grouped by discipline (category/level)
 *   - 50 teachers, 500 students, ~150 parents + user accounts (Password@123)
 *   - Subject allocations, timetables, attendance
 *   - Fee structures + fee records + payments
 *   - Exams + result_scores (3 terms), positions
 *
 * Usage: php database/curriculum_seeder.php --confirm
 *   --confirm    REQUIRED: wipe and rebuild the entire database
 *   --students=N default 500
 *   --teachers=N default 50
 *   --no-parents skip parent/link generation
 *
 * NOTE: Requires the curriculum migration to have been applied first
 * (subjects.category, subjects.level, student_subject_selections exist).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("Forbidden: database/curriculum_seeder.php may only be run from the command line.\n");
}
if (!in_array('--confirm', $argv ?? [], true)) {
    fwrite(STDERR, "Refusing to run: pass --confirm to wipe and reseed the database.\n");
    exit(1);
}

$OPT_STUDENTS = 500;
$OPT_TEACHERS = 50;
$OPT_NO_PARENTS = false;
foreach ($argv ?? [] as $arg) {
    if ($arg === '--no-parents') $OPT_NO_PARENTS = true;
    if (preg_match('/^--students=(\d+)$/', $arg, $m)) $OPT_STUDENTS = (int)$m[1];
    if (preg_match('/^--teachers=(\d+)$/', $arg, $m)) $OPT_TEACHERS = (int)$m[1];
}

require_once __DIR__ . '/../config/database.php';
$db = getDB();

function h($p) { return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]); }
function gaussRandom($mean, $std) {
    static $cache = null;
    if ($cache !== null) { $v = $cache; $cache = null; return $mean + $std * $v; }
    $u1 = mt_rand() / mt_getrandmax(); $u2 = mt_rand() / mt_getrandmax();
    $r = sqrt(-2 * log($u1));
    $t = 2 * M_PI * $u2;
    $cache = $r * sin($t);
    return $mean + $std * ($r * cos($t));
}
function randScore($min, $max, $meanFactor = 0.7) {
    $range = $max - $min; $mean = $min + $range * $meanFactor; $std = $range * 0.18;
    $val = round(gaussRandom($mean, $std));
    return max($min, min($max, $val));
}
function gradeFromScore($score) {
    if ($score >= 75) return 'A';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 40) return 'D';
    if ($score >= 30) return 'E';
    return 'F';
}
function batchInsert($db, $table, $columns, $rows, $chunkSize = 200, $ignore = false) {
    if (empty($rows)) return 0;
    $ignoreStr = $ignore ? ' IGNORE' : '';
    $count = 0;
    foreach (array_chunk($rows, $chunkSize) as $chunk) {
        $placeholders = []; $values = [];
        foreach ($chunk as $row) {
            $ph = [];
            foreach ($row as $val) { $values[] = $val; $ph[] = '?'; }
            $placeholders[] = '(' . implode(',', $ph) . ')';
        }
        $sql = "INSERT{$ignoreStr} INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES " . implode(',', $placeholders);
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        $count += $stmt->rowCount();
    }
    return $count;
}

$START = microtime(true);
echo "=== SMS Peculiar College — Curriculum Seeder " . date('Y-m-d') . " ===\n\n";

try {
    // ── WIPE ──────────────────────────────────────────────────────────────
    echo "Wiping existing data...\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $t) { $db->exec("TRUNCATE TABLE `{$t}`"); }
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "  Done.\n\n";

    // ── SESSION / TERMS / DEPARTMENTS ─────────────────────────────────────
    $db->exec("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES ('2025/2026', '2025-09-01', '2026-08-31', 1, 'active')");
    $db->exec("INSERT INTO terms (session_id, term_name, start_date, end_date, is_current) VALUES (1,'First Term','2025-09-15','2025-12-19',1),(1,'Second Term','2026-01-06','2026-04-10',0),(1,'Third Term','2026-04-27','2026-08-14',0)");

    $depStmt = $db->prepare("INSERT INTO departments (name, code, description) VALUES (?,?,?)");
    $departments = [
        ['Languages','LANG','English, French, Arabic and Nigerian Languages'],
        ['Sciences','SCI','Science Department'],
        ['Humanities','HUM','Arts, Humanities and Social Sciences'],
        ['Business Studies','BUS','Commercial and Business Studies'],
        ['Vocational & Technical','VOC','Trade, Vocational and Technical Subjects'],
    ];
    foreach ($departments as $d) $depStmt->execute($d);
    $depIds = array_map('intval', $db->query("SELECT id FROM departments ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
    echo "  Session, terms and departments created\n";

    // ── USERS (foundation) ────────────────────────────────────────────────
    $pwd = h('Password@123');
    $userStmt = $db->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, phone, role, status) VALUES (?,?,?,?,?,?,?, 'active')");
    $foundUsers = [
        ['admin','admin@peculiarcollege.edu.ng','Admin','User','08012345670','admin'],
        ['accountant1','accountant@peculiarcollege.edu.ng','Chioma','Nwosu','08012345675','accountant'],
        ['teacher1','teacher@peculiarcollege.edu.ng','John','Okafor','08012345671','teacher'],
        ['teacher2','teacher2@peculiarcollege.edu.ng','Sandra','Eze','08012345672','teacher'],
        ['student1','student@peculiarcollege.edu.ng','Chidi','Okonkwo','08012345673','student'],
        ['parent1','parent@peculiarcollege.edu.ng','Emeka','Okonkwo','08012345674','parent'],
    ];
    foreach ($foundUsers as $u) $userStmt->execute([$u[0],$u[1],$pwd,$u[2],$u[3],$u[4],$u[5]]);
    echo "  Foundation users created\n";

    // ── CLASSES ───────────────────────────────────────────────────────────
    $classStmt = $db->prepare("INSERT INTO classes (name, section, capacity, class_teacher_id, department_id, academic_session_id) VALUES (?,?,?,?,?,1)");
    $classDefs = [
        ['JSS1','A',45,3,$depIds[0]], ['JSS1','B',45,null,$depIds[0]],
        ['JSS2','A',45,null,$depIds[0]], ['JSS2','B',45,null,$depIds[0]],
        ['JSS3','A',45,null,$depIds[0]], ['JSS3','B',45,null,$depIds[0]],
        ['SS1','Science',40,null,$depIds[1]], ['SS1','Arts',40,null,$depIds[2]], ['SS1','Commercial',40,null,$depIds[3]],
        ['SS2','Science',40,null,$depIds[1]], ['SS2','Arts',40,null,$depIds[2]], ['SS2','Commercial',40,null,$depIds[3]],
        ['SS3','Science',40,null,$depIds[1]], ['SS3','Arts',40,null,$depIds[2]], ['SS3','Commercial',40,null,$depIds[3]],
    ];
    foreach ($classDefs as $c) $classStmt->execute($c);
    $classMap = [];
    foreach ($db->query("SELECT id,name,section FROM classes ORDER BY id")->fetchAll() as $c) $classMap[$c['name'].'|'.$c['section']] = (int)$c['id'];
    // ordered list of class ids (JSS1A, JSS1B, JSS2A, ...)
    $classIdsOrdered = [];
    foreach ($classMap as $cid) $classIdsOrdered[] = $cid;
    echo "  Classes created (" . count($classMap) . ")\n";

    // ── CURRICULUM SUBJECTS ──────────────────────────────────────────────
    $subjStmt = $db->prepare("INSERT INTO subjects (class_id, name, code, teacher_id, credit_unit, is_compulsory, category, level) VALUES (?,?,?,?,1,?,?,?)");
    $catMap = ['LANG'=>'humanities','CORE'=>'core','SCI'=>'science','HUM'=>'humanities','BUS'=>'business','VOC'=>'trade'];

    $jssCore = [
        ['English Language','LANG',1],['Mathematics','CORE',1],['Citizenship & Heritage Studies','CORE',1],
        ['National Values','CORE',1],['Basic Science','SCI',1],['Basic Technology','VOC',1],
        ['Digital Literacy','VOC',1],['Cultural & Creative Arts','HUM',1],['Business Studies','BUS',1],
        ['Physical & Health Education','VOC',1],['Religious Studies','HUM',1],['Nigerian Languages','LANG',1],
        ['French','LANG',1],['Arabic','LANG',0],
    ];
    $jssTrade = [
        ['Agriculture','VOC',0],['Animal Husbandry','VOC',0],['Fisheries','VOC',0],['Horticulture','VOC',0],
        ['Food Processing','VOC',0],['Home Making','VOC',0],['Catering & Craft Practice','VOC',0],
        ['Fashion Design & Garment Making','VOC',0],['Beauty & Cosmetology','VOC',0],['Interior Decoration','VOC',0],
        ['Welding & Fabrication','VOC',0],['Electrical Installation & Maintenance','VOC',0],
        ['Electronics Repair & Maintenance','VOC',0],['Computer Hardware & GSM Repairs','VOC',0],
        ['Plumbing','VOC',0],['Painting & Decoration','VOC',0],['Solar PV Installation & Maintenance','VOC',0],
        ['Auto Mechanics','VOC',0],['Refrigeration & Air Conditioning','VOC',0],['Furniture Making','VOC',0],
        ['Upholstery','VOC',0],['Data Processing','VOC',0],
    ];
    $ssCore = [
        ['English Language','LANG',1],['Mathematics','CORE',1],['Civic Education','CORE',1],
        ['Digital Literacy','VOC',1],['Trade/Entrepreneurship','VOC',1],
    ];
    $ssScience = [
        ['Biology','SCI',1],['Chemistry','SCI',1],['Physics','SCI',1],['Further Mathematics','SCI',0],
        ['Agricultural Science','SCI',0],['Computer Studies','SCI',0],['Data Processing','SCI',0],
        ['Technical Drawing','SCI',0],['Geography','HUM',0],
    ];
    $ssHumanities = [
        ['Literature in English','HUM',0],['Government','HUM',0],['History','HUM',0],
        ['Christian Religious Studies','HUM',0],['Islamic Religious Studies','HUM',0],['French','LANG',0],
        ['Arabic','LANG',0],['Music','HUM',0],['Fine Arts','HUM',0],['Yoruba','LANG',0],
        ['Igbo','LANG',0],['Hausa','LANG',0],
    ];
    $ssBusiness = [
        ['Financial Accounting','BUS',1],['Commerce','BUS',1],['Economics','BUS',1],
        ['Office Practice','BUS',0],['Marketing','BUS',0],['Insurance','BUS',0],
    ];
    $ssTrade = [
        ['Agriculture','VOC',0],['Animal Husbandry','VOC',0],['Fisheries','VOC',0],['Horticulture','VOC',0],
        ['Food Processing','VOC',0],['Catering Craft Practice','VOC',0],['Home Management','VOC',0],
        ['Fashion Design & Garment Making','VOC',0],['Beauty & Cosmetology','VOC',0],['Interior Decoration','VOC',0],
        ['Electrical Installation & Maintenance','VOC',0],['Electronics Repair & Maintenance','VOC',0],
        ['Computer Hardware & GSM Repairs','VOC',0],['Solar PV Installation & Maintenance','VOC',0],
        ['Plumbing','VOC',0],['Welding & Fabrication','VOC',0],['Auto Mechanics','VOC',0],
        ['Refrigeration & Air Conditioning','VOC',0],['Furniture Making','VOC',0],['Upholstery','VOC',0],
    ];

    $subjectsByClass = []; // class id => [ ['id','name','is_compulsory'], ... ]
    $subjectCount = 0;
    $codeCount = [];
    foreach ($classMap as $key => $cid) {
        [$name, $section] = explode('|', $key);
        $level = strpos($name, 'SS') === 0 ? 'SS' : 'JSS';
        $isJss = $level === 'JSS';
        $list = array_merge($isJss ? $jssCore : $ssCore, $isJss ? [] : []);
        if (!$isJss) {
            if ($section === 'Science')    $list = array_merge($list, $ssScience);
            if ($section === 'Arts')       $list = array_merge($list, $ssHumanities);
            if ($section === 'Commercial') $list = array_merge($list, $ssBusiness);
        }
        $list = array_merge($list, $isJss ? $jssTrade : $ssTrade);
        $added = [];
        foreach ($list as $item) {
            $subjName = $item[0];
            if (isset($added[$subjName])) continue;
            $added[$subjName] = true;
            $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$subjName),0,4)) . '_' . $cid;
            if (isset($codeCount[$base])) { $codeCount[$base]++; $code = $base . '_' . $codeCount[$base]; }
            else { $codeCount[$base] = 1; $code = $base; }
            $subjStmt->execute([$cid, $subjName, $code, null, $item[2], $catMap[$item[1]], $level]);
            $subjectsByClass[$cid][] = ['id'=>(int)$db->lastInsertId(), 'name'=>$subjName, 'is_compulsory'=>$item[2]];
            $subjectCount++;
        }
    }
    echo "  Subjects created ($subjectCount)\n";

    // ── TEACHERS (50) ─────────────────────────────────────────────────────
    echo "Creating {$OPT_TEACHERS} teachers...\n";
    $male = ['Chidi','Emeka','Chuka','Obinna','Nnamdi','Ifeanyi','Chima','Ugochukwu','Kelechi','Chinedu','Chibuzor','Chidiebere','Chigozie','Chinonso','Chukwudi','Ebuka','Ekene','Ikenna','Ikechukwu','Izu','Kachi','Kosy','Nduka','Nwabueze','Obiora','Okechukwu','Okeke','Okey','Okoro','Olamilekan','Oluwafemi','Onyekachi','Osita','Somto','Tochukwu','Uchenna','Uche','Ugo','Uzochi','Uzoma','Samuel','Daniel','David','Michael','Emmanuel','Joseph','Joshua','James','John','Peter','Stephen','Andrew','Anthony','Mark','Paul','George','Victor','Francis','Patrick','Vincent','Martin','Christopher','Matthew','Luke','Timothy','Simon','Amos','Isaac','Abraham','Solomon','Gabriel','Festus','Innocent','Prosper','Destiny','Favour','Wisdom','Justice'];
    $female = ['Chioma','Nkechi','Amara','Chiamaka','Chidinma','Chika','Chikodi','Chimamanda','Chinaza','Chinwe','Chinyere','Ebere','Ezinne','Ifeoma','Ijeoma','Kelechi','Ngozi','Njideka','Nnenna','Nneoma','Ogechi','Ogochukwu','Olabisi','Olamide','Oluwaseun','Oluwaseyi','Oluwatobiloba','Onyeka','Onyinye','Sandra','Somto','Uchenna','Uchechi','Ugochinyere','Ujunwa','Uloma','Uzochi','Zinachidi','Chisom','Chizoba','Adanna','Adaobi','Adaeze','Chiamaka','Blessing','Grace','Peace','Faith','Mercy','Charity','Prudence','Amanda','Pamela','Esther','Ruth','Sarah','Mary','Martha','Deborah','Rebecca','Hannah','Naomi','Judith','Elizabeth','Catherine','Margaret','Joy','Glory','Queen','Victoria','Beatrice','Florence','Evelyn','Doris','Lillian'];
    $surnames = ['Okonkwo','Eze','Okafor','Nwosu','Obi','Igwe','Nwachukwu','Okeke','Onyema','Nwankwo','Okechukwu','Chukwuma','Onyekachi','Chibueze','Nnamdi','Uzodinma','Ikechukwu','Ogbonna','Nwafor','Okoro','Okpara','Okoli','Nwodo','Okereke','Oko','Onyishi','Otu','Ubah','Ude','Udo','Ugwu','Ugwuanyi','Ukpabi','Ukwu','Umeh','Umerah','Umunna','Unegbu','Urama','Uzodinma','Bello','Yahaya','Musa','Mohammed','Abubakar','Adamu','Aliyu','Sani','Ibrahim','Adeyemi','Adebayo','Adebisi','Adegoke','Adekunle','Adeniyi','Adeola','Adepoju','Adesina','Ajayi','Akinsanya','Akintola','Akinyemi','Alabi','Balogun','Bassey','Etim','Johnson','Kalu','Benson','Inyang'];
    $allNames = array_merge($male, $female);
    shuffle($allNames);
    $teacherRoles = ['LANG'=>'English/French Teacher','SCI'=>'Science Teacher','HUM'=>'Humanities Teacher','BUS'=>'Business Studies Teacher','VOC'=>'Vocational Studies Teacher'];
    $qPool = ['B.Sc. in Education','B.A. in Education','B.Ed.','M.Ed.','B.Sc. with PGDE','B.A. with PGDE','M.Sc. in Education','B.Ed. (Hons)','B.Sc. in Mathematics Education','B.A. in English Education'];
    $emailDomains = ['gmail.com','yahoo.com','outlook.com','peculiarcollege.edu.ng'];

    $teacherUserRows = []; $teacherRows = [];
    $existingTeacherCount = 2; // teacher1, teacher2 created above
    $teacherSeq = $OPT_TEACHERS - $existingTeacherCount;
    if ($teacherSeq < 0) $teacherSeq = 0;
    $nextUid = 7;
    for ($i = 0; $i < $teacherSeq; $i++) {
        $uid = $nextUid++; $g = $i % 2; $fname = $g ? $female[$i % count($female)] : $male[$i % count($male)]; $lname = $surnames[$i % count($surnames)];
        $username = 'teacher' . $uid;
        $email = strtolower($fname . '.' . $lname . $uid) . '@' . $emailDomains[$i % count($emailDomains)];
        $phone = '080' . str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT);
        $roleKey = array_keys($teacherRoles)[$i % count($teacherRoles)];
        $teacherUserRows[] = [$username,$email,$pwd,$fname,$lname,$phone,'teacher','active'];
        $dept = $depIds[$i % count($depIds)];
        $teacherRows[] = [0,$uid,'TCH'.str_pad($uid,4,'0',STR_PAD_LEFT),$qPool[$i % count($qPool)],$dept,sprintf('202%d-%02d-%02d',mt_rand(3,5),mt_rand(1,12),mt_rand(1,28))];
    }
    $userCols = ['username','email','password_hash','first_name','last_name','phone','role','status'];
    $teacherUserInserted = batchInsert($db,'users',$userCols,$teacherUserRows);
    // Fetch teacher user ids
    $newTeacherUsers = $db->query("SELECT id FROM users WHERE role='teacher' AND id NOT IN (SELECT user_id FROM teachers WHERE user_id IS NOT NULL) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $teacherInsert = [];
    foreach ($newTeacherUsers as $idx => $uid) {
        $td = $teacherRows[$idx] ?? null; if (!$td) continue;
        // td[0] is placeholder 0; use user_id as uid, dept real
        $teacherInsert[] = [$uid, $td[2], $td[3], $td[4], $td[5]];
    }
    batchInsert($db,'teachers',['user_id','employee_id','qualification','department_id','date_hired'],$teacherInsert);
    $allTeachers = $db->query("SELECT id, user_id FROM teachers ORDER BY id")->fetchAll();
    $teacherIdByUser = []; foreach ($allTeachers as $t) $teacherIdByUser[$t['user_id']] = (int)$t['id'];
    echo "  Total teachers: " . count($allTeachers) . "\n";

    // ── SUBJECT ALLOCATIONS (teacher-level; subject_allocations uses teacher_id = teachers.id) ──
    $allocStmt = $db->prepare("INSERT INTO subject_allocations (teacher_id, class_id, subject_id, academic_session_id) VALUES (?,?,?,1)");
    foreach ($subjectsByClass as $cid => $subjects) {
        foreach ($subjects as $subj) {
            $teacherRec = $allTeachers[array_rand($allTeachers)];
            $allocStmt->execute([$teacherRec['id'], $cid, $subj['id']]);
        }
    }
    echo "  Subject allocations created\n";

    // build allocation lookup teacher (teachers.id) => class => [subject_id]  (for timetable/result entry)
    $allocLookup = [];
    foreach ($db->query("SELECT teacher_id,class_id,subject_id FROM subject_allocations WHERE academic_session_id=1")->fetchAll() as $a) {
        $allocLookup[$a['teacher_id']]['classes'][$a['class_id']][] = $a['subject_id'];
    }

    // ── STUDENTS (500) ────────────────────────────────────────────────────
    echo "Creating {$OPT_STUDENTS} students...\n";
    $dist = array_fill_keys($classIdsOrdered, 0);
    $total = count($classIdsOrdered);
    $base = intdiv($OPT_STUDENTS, $total);
    $rem = $OPT_STUDENTS % $total;
    foreach ($classIdsOrdered as $i => $cid) { $dist[$cid] = $base + ($i < $rem ? 1 : 0); }

    $studentUserRows = []; $studentRows = [];
    $admissionNo = 1;
    $genders = ['male','female'];
    $classLevel = [];
    foreach ($db->query("SELECT id, name FROM classes")->fetchAll() as $c) $classLevel[$c['id']] = (strpos($c['name'],'SS')===0?'SS':'JSS');
    foreach ($dist as $cid => $count) {
        for ($s = 0; $s < $count; $s++) {
            $uid = $nextUid++; $gender = $genders[mt_rand(0,1)];
            $pool = $gender === 'male' ? $male : $female;
            $fname = $pool[mt_rand(0,count($pool)-1)]; $lname = $surnames[mt_rand(0,count($surnames)-1)];
            $username = 'student' . str_pad($uid,4,'0',STR_PAD_LEFT);
            $email = strtolower($fname.'.'.$lname.str_pad($uid,4,'0',STR_PAD_LEFT)).'@peculiarcollege.edu.ng';
            $phone = '080'.str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT);
            $studentUserRows[] = [$username,$email,$pwd,$fname,$lname,$phone,'student','active'];
            $admNo = 'PIC2025'.str_pad($admissionNo++,3,'0',STR_PAD_LEFT);
            $dobY = $classLevel[$cid]==='SS' ? mt_rand(2006,2009) : mt_rand(2011,2014);
            $dob = sprintf('%d-%02d-%02d',$dobY,mt_rand(1,12),mt_rand(1,28));
            $enr = '2025-09-'.str_pad(mt_rand(10,20),2,'0',STR_PAD_LEFT);
            $studentRows[] = [$uid,$admNo,$cid,$dob,$gender,$enr,'active'];
        }
    }
    $sUserInserted = batchInsert($db,'users',$userCols,$studentUserRows);
    $newStudentUsers = $db->query("SELECT id FROM users WHERE role='student' AND id NOT IN (SELECT user_id FROM students WHERE user_id IS NOT NULL) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $studentInsert = [];
    foreach ($studentRows as $i => $sr) {
        $uid = $newStudentUsers[$i] ?? null; if (!$uid) continue;
        $studentInsert[] = [$uid,$sr[1],$sr[2],$sr[3],$sr[4],$sr[5],$sr[6]];
    }
    $studentInserted = batchInsert($db,'students',['user_id','admission_no','class_id','date_of_birth','gender','enrollment_date','status'],$studentInsert);
    $students = $db->query("SELECT s.id, s.user_id, s.class_id, s.admission_no FROM students s ORDER BY s.id")->fetchAll();
    $studentIdsByClass = []; foreach ($students as $st) $studentIdsByClass[$st['class_id']][] = $st;
    echo "  Total students: " . count($students) . "\n";

    // ── PARENTS ──────────────────────────────────────────────────────────
    $numParents = $OPT_NO_PARENTS ? 0 : max(1, (int)ceil(count($students) / 3));
    if ($numParents > 0) {
        $parentUserRows = []; $parentRows = [];
        for ($i = 0; $i < $numParents; $i++) {
            $uid = $nextUid++; $fname = $male[mt_rand(0,count($male)-1)]; $lname = $surnames[mt_rand(0,count($surnames)-1)];
            $username = 'parent'.$uid;
            $email = strtolower($fname.'.'.$lname.$uid).'@gmail.com';
            $phone = '080'.str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT);
            $parentUserRows[] = [$username,$email,$pwd,$fname,$lname,$phone,'parent','active'];
            $parentRows[] = [$uid, ['Business Owner','Civil Servant','Teacher','Engineer','Doctor','Lawyer','Accountant','Trader','Farmer','Banker'][mt_rand(0,9)], ['Father','Mother','Guardian','Uncle','Aunt'][mt_rand(0,4)]];
        }
        batchInsert($db,'users',$userCols,$parentUserRows);
        $newParents = $db->query("SELECT id FROM users WHERE role='parent' AND id NOT IN (SELECT user_id FROM parents WHERE user_id IS NOT NULL) ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $parentInsert = []; foreach ($parentRows as $i => $pr) { $uid = $newParents[$i]; if(!$uid) continue; $parentInsert[] = [$uid,$pr[1],$pr[2]]; }
        $parentInserted = batchInsert($db,'parents',['user_id','occupation','relationship'],$parentInsert);
        $allParents = $db->query("SELECT id FROM parents ORDER BY id")->fetchAll();
        $spRows = []; foreach ($students as $i => $st) { $spRows[] = [$st['id'], $allParents[$i % count($allParents)]['id'], ($i%5===0)?0:1]; }
        batchInsert($db,'student_parents',['student_id','parent_id','is_guardian'],$spRows,200,true);
        echo "  Parents created ($numParents), linked\n";
    } else {
        echo "  Skipping parents\n";
    }

    // ── FEE STRUCTURE + FEES + PAYMENTS ──────────────────────────────────
    echo "Creating fee structures...\n";
    $terms = array_map('intval',$db->query("SELECT id FROM terms WHERE session_id=1")->fetchAll(PDO::FETCH_COLUMN));
    $jssFees = [['Tuition Fee',80000.00,1],['Development Levy',20000.00,1],['Sports Fee',10000.00,0],['Library Fee',8000.00,1],['ICT Fee',5000.00,1],['Science Lab Fee',3000.00,0]];
    $ssFees = [['Tuition Fee',120000.00,1],['Development Levy',25000.00,1],['Sports Fee',12000.00,0],['Library Fee',10000.00,1],['ICT Fee',8000.00,1],['Science Lab Fee',10000.00,1]];
    $fsRows = []; $fsMap = [];
    $ci = 0;
    foreach ($classMap as $cid) {
        $isJss = $classLevel[$cid]==='JSS'; $fees = $isJss ? $jssFees : $ssFees;
        foreach ($terms as $tid) {
            foreach ($fees as $f) {
                $fsMap[$cid][$tid][] = count($fsRows);
                $fsRows[] = [$f[0],$f[1],$cid,$tid,$f[2]];
            }
        }
    }
    batchInsert($db,'fee_structure',['fee_name','amount','class_id','term_id','is_mandatory'],$fsRows);
    // re-read actual id sequences (rows inserted in same order -> auto inc)
    $allFS = $db->query("SELECT id, class_id, term_id FROM fee_structure ORDER BY id")->fetchAll();
    $fsIds = []; foreach ($allFS as $i => $fs) { $fsIds[$fs['class_id']][$fs['term_id']][] = (int)$fs['id']; }

    echo "Creating fees + payments...\n";
    $feeRows = []; $paymentRows = [];
    $payMethods = ['cash','card','transfer','pos'];
    $paySeq = 10000;
    foreach ($students as $si => $st) {
        $cid = $st['class_id'];
        foreach ($terms as $tid) {
            $feesForTerm = $fsIds[$cid][$tid] ?? [];
            foreach ($feesForTerm as $fsid) {
                $total = (in_array($fsid, array_slice($fsIds[$cid][$tid],0,2)) ? ($classLevel[$cid]==='JSS'?80000.00:120000.00) : (mt_rand(0,1)? 10000.00:8000.00));
                // we don't know amount here easily; use placeholder amount mapping
                $rem = ($si + 1) % 10;
                if ($rem < 3) { $paid = $total; $status='paid'; }
                elseif ($rem < 6) { $paid = round($total*mt_rand(30,70)/100,2); $status='partial'; }
                elseif ($rem < 8) { $paid = 0; $status='unpaid'; }
                else { $paid = $total; $status='paid'; }
                $balance = round($total-$paid,2);
                $due = sprintf('2025-%02d-15',($tid==1)?10:(($tid==2)?2:5));
                $feeRows[] = [$st['id'],$fsid,$total,$paid,$balance,$due,$status];
            }
        }
    }
    $feeInserted = batchInsert($db,'fees',['student_id','fee_structure_id','total_amount','paid_amount','balance','due_date','status'],$feeRows,300);
    $paidFees = $db->query("SELECT id, paid_amount, due_date FROM fees WHERE paid_amount>0")->fetchAll();
    foreach ($paidFees as $fee) {
        $paid = (float)$fee['paid_amount']; $num = mt_rand(1,3); $remaining = $paid;
        for ($p=0;$p<$num;$p++) {
            $amt = ($p < $num-1) ? round($remaining*mt_rand(30,70)/100,2) : $remaining;
            $amt = round($amt,2); if ($amt<=0) continue; $remaining -= $amt;
            $paySeq++; $ref='TRX'.str_pad($paySeq,10,'0',STR_PAD_LEFT); $rcp='RCP'.str_pad($paySeq,10,'0',STR_PAD_LEFT);
            $pdate = date('Y-m-d', strtotime($fee['due_date'].' -'.mt_rand(0,30).' days'));
            $paymentRows[] = [$fee['id'],$amt,$payMethods[mt_rand(0,3)],$ref,$rcp,$pdate,null,null,'approved'];
        }
    }
    batchInsert($db,'payments',['fee_id','amount_paid','payment_method','transaction_ref','receipt_no','payment_date','verified_by','proof_document','status'],$paymentRows,300);
    echo "  Fee records: $feeInserted, payments: " . count($paymentRows) . "\n";

    // ── TIMETABLE ────────────────────────────────────────────────────────
    echo "Creating timetables...\n";
    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
    $slotsStart = ['08:00:00','09:00:00','10:00:00','11:00:00','12:00:00','13:00:00','14:00:00','15:00:00','16:00:00'];
    $slotsEnd   = ['08:50:00','09:50:00','10:50:00','11:50:00','12:50:00','13:50:00','14:50:00','15:50:00','16:40:00'];
    $ttRows = [];
    foreach ($subjectsByClass as $cid => $subjects) {
        $daysOfWeek = mt_rand(3,5);
        foreach (array_rand($days, $daysOfWeek) as $dayIdx) {
            $day = $days[$dayIdx];
            $count = min(count($subjects), 3);
            $slots = array_rand($slotsStart, $count);
            if (!is_array($slots)) $slots = [$slots];
            $subIndices = array_rand($subjects, $count);
            if (!is_array($subIndices)) $subIndices = [$subIndices];
            foreach ($slots as $k => $slotIdx) {
                $subj = $subjects[$subIndices[$k % count($subIndices)]];
                $teacher = $allTeachers[array_rand($allTeachers)];
                $room = 'Room ' . mt_rand(1,30);
                $ttRows[] = [$cid,$subj['id'],$teacher['id'],$day,$slotsStart[$slotIdx],$slotsEnd[$slotIdx],$room];
            }
        }
    }
    $ttInserted = batchInsert($db,'timetable',['class_id','subject_id','teacher_id','day_of_week','start_time','end_time','room'],$ttRows,300);
    echo "  Timetable entries: $ttInserted\n";

    // ── ATTENDANCE (First Term) ───────────────────────────────────────────
    echo "Creating attendance...\n";
    $termStart='2025-09-15'; $termEnd='2025-12-19';
    $schoolDays=[]; $cur=strtotime($termStart); $end=strtotime($termEnd);
    while ($cur<=$end) { $d=(int)date('w',$cur); if($d>=1 && $d<=5) $schoolDays[]=date('Y-m-d',$cur); $cur=strtotime('+1 day',$cur); }
    $statuses=['present','present','present','present','present','present','present','present','absent','late'];
    $attRows=[]; $attCount=0;
    foreach ($students as $st) {
        foreach ($schoolDays as $day) {
            $attRows[]=[$st['id'],$st['class_id'],$day,$statuses[mt_rand(0,9)],null,1];
            if (count($attRows)>=500){ $attCount += batchInsert($db,'attendance',['student_id','class_id','date','status','remark','marked_by'],$attRows,500); $attRows=[]; }
        }
    }
    if (!empty($attRows)) $attCount += batchInsert($db,'attendance',['student_id','class_id','date','status','remark','marked_by'],$attRows,500);
    echo "  Attendance records: $attCount\n";

    // ── EXAMS + RESULTS (3 terms) ────────────────────────────────────────
    echo "Creating exams + results...\n";
    // Ensure result_settings rows + promotion_config rows
    foreach ($terms as $tid) { $db->exec("INSERT IGNORE INTO result_settings (session_id, term_id) VALUES (1,$tid)"); }
    foreach ($classIdsOrdered as $cid) { $db->exec("INSERT IGNORE INTO promotion_config (session_id, class_id, pass_mark, min_subjects_pass) VALUES (1,$cid,40,5)"); }

    $teacherUserIds = $db->query("SELECT id FROM teachers")->fetchAll(PDO::FETCH_COLUMN);
    $resCols = ['student_id','class_id','subject_id','session_id','term_id','assignment_score','assignment2_score','test_score','test2_score','ca_total','project_score','exam_score','total_score','grade','subject_position','status','entered_by'];

    $totalRes = 0;
    foreach ($terms as $tid) {
        $termBias = ($tid==1)?0.55:(($tid==2)?0.65:0.70);
        $rows=[];
        foreach ($studentIdsByClass as $cid => $studentsInClass) {
            $subjects = $subjectsByClass[$cid] ?? [];
            foreach ($studentsInClass as $st) {
                foreach ($subjects as $subj) {
                    $a1=randScore(0,10,$termBias*0.9); $a2=randScore(0,10,$termBias*0.9);
                    $t1=randScore(0,10,$termBias*0.9); $t2=randScore(0,10,$termBias*0.9);
                    $ca=min(40,round($a1+$a2+$t1+$t2)); $proj=randScore(0,10,$termBias*0.9);
                    $exam=round(gaussRandom($termBias*60,60*0.15)); $exam=max(0,min(60,$exam));
                    $total=$ca+$exam; $grade=gradeFromScore($total);
                    $teacher=$allTeachers[array_rand($allTeachers)]['id'];
                    $rows[]=[$st['id'],$cid,$subj['id'],1,$tid,$a1,$a2,$t1,$t2,$ca,$proj,$exam,$total,$grade,0,'published',$teacher];
                }
            }
        }
        $totalRes += batchInsert($db,'result_scores',$resCols,$rows,300);
        echo "    Term $tid: done\n";
    }
    echo "  Total result records: $totalRes\n";

    // ── SUBJECT POSITIONS ────────────────────────────────────────────────
    echo "Computing subject positions...\n";
    $posUpdate=0;
    foreach ($classIdsOrdered as $cid) {
        foreach ($terms as $tid) {
            $res = $db->prepare("SELECT id, subject_id, total_score FROM result_scores WHERE class_id=? AND session_id=1 AND term_id=? ORDER BY subject_id, total_score DESC");
            $res->execute([$cid,$tid]); $all=$res->fetchAll();
            $curSub=null; $pos=0;
            $upd=$db->prepare("UPDATE result_scores SET subject_position=? WHERE id=?");
            foreach ($all as $r) {
                if ($r['subject_id']!==$curSub){$curSub=$r['subject_id'];$pos=0;}
                $pos++; $upd->execute([$pos,$r['id']]); $posUpdate++;
            }
        }
    }
    echo "  Positions updated ($posUpdate)\n";

    // ── SUBJECT SELECTIONS (curriculum: JSS must pick >=1 trade; SS >=1 trade) ──
    echo "Creating subject selections...\n";
    $selStmt = $db->prepare("INSERT IGNORE INTO student_subject_selections (student_id, subject_id, academic_session_id, is_core) VALUES (?,?,1,?)");
    $selCount=0;
    foreach ($studentIdsByClass as $cid => $studentsInClass) {
        $subjects = $subjectsByClass[$cid] ?? [];
        $core = array_filter($subjects, fn($s)=>$s['is_compulsory']);
        $optional = array_filter($subjects, fn($s)=>!$s['is_compulsory']);
        foreach ($studentsInClass as $st) {
            foreach ($core as $s) { $selStmt->execute([$st['id'],$s['id'],1]); $selCount++; }
            // pick 1-2 optional trade subjects
            if ($optional) { $picks=array_rand($optional,min(2,count($optional))); if(!is_array($picks))$picks=[$picks]; foreach($picks as $pi){ $selStmt->execute([$st['id'],$optional[$pi]['id'],0]); $selCount++; } }
        }
    }
    echo "  Subject selections: $selCount\n";

    $elapsed = round(microtime(true)-$START,2);
    echo "\n=== Seeding Complete! Time: {$elapsed}s ===\n";
    echo "Students: ".count($students)."\n";
    echo "Teachers: ".count($allTeachers)."\n";
    echo "Subjects: $subjectCount\n";
    echo "Results:  $totalRes\n\n";
    echo "All user accounts: Password@123\n";
    echo "  Admin:       admin@peculiarcollege.edu.ng\n";
    echo "  Accountant:  accountant@peculiarcollege.edu.ng\n\n";

} catch (Exception $e) {
    echo "\nERROR: ".$e->getMessage()."\n";
    echo "File: ".$e->getFile().":".$e->getLine()."\n";
    echo $e->getTraceAsString()."\n";
    exit(1);
}