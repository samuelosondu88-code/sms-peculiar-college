<?php
/**
 * One-off chunked web seeder for the live DB (InfinityFree).
 *
 * Wipes and rebuilds the 2025/2026 curriculum dataset (500 students, 50
 * teachers, timetables, attendance, fees/payments, 3-term results + positions).
 *
 * Works around the host's request-time limit by splitting work into
 * time-budgeted batches, persisting progress in a state file, and
 * auto-refreshing the page until the build completes. Keep the tab open.
 *
 * Usage: https://<site>/sms-peculiar-college/_deploy_seed.php?key=PIC2026seed
 * DELETE THIS FILE (and .seed_state.json) once the run reports DONE.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

if (($_GET['key'] ?? '') !== 'PIC2026seed') {
    exit('Forbidden: invalid key.');
}

$STATE_FILE = __DIR__ . '/.seed_state.json';
$BUDGET_SECONDS = 22;

require_once __DIR__ . '/config/database.php';
$db = getDB();

// ── helpers ──────────────────────────────────────────────────────────────
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

$male = ['Chidi','Emeka','Chuka','Obinna','Nnamdi','Ifeanyi','Chima','Ugochukwu','Kelechi','Chinedu','Chibuzor','Chidiebere','Chigozie','Chinonso','Chukwudi','Ebuka','Ekene','Ikenna','Ikechukwu','Izu','Kachi','Kosy','Nduka','Nwabueze','Obiora','Okechukwu','Okeke','Okey','Okoro','Olamilekan','Oluwafemi','Onyekachi','Osita','Somto','Tochukwu','Uchenna','Uche','Ugo','Uzochi','Uzoma','Samuel','Daniel','David','Michael','Emmanuel','Joseph','Joshua','James','John','Peter','Stephen','Andrew','Anthony','Mark','Paul','George','Victor','Francis','Patrick','Vincent','Martin','Christopher','Matthew','Luke','Timothy','Simon','Amos','Isaac','Abraham','Solomon','Gabriel','Festus','Innocent','Prosper','Destiny','Favour','Wisdom','Justice'];
$female = ['Chioma','Nkechi','Amara','Chiamaka','Chidinma','Chika','Chikodi','Chimamanda','Chinaza','Chinwe','Chinyere','Ebere','Ezinne','Ifeoma','Ijeoma','Kelechi','Ngozi','Njideka','Nnenna','Nneoma','Ogechi','Ogochukwu','Olabisi','Olamide','Oluwaseun','Oluwaseyi','Oluwatobiloba','Onyeka','Onyinye','Sandra','Somto','Uchenna','Uchechi','Ugochinyere','Ujunwa','Uloma','Uzochi','Zinachidi','Chisom','Chizoba','Adanna','Adaobi','Adaeze','Chiamaka','Blessing','Grace','Peace','Faith','Mercy','Charity','Prudence','Amanda','Pamela','Esther','Ruth','Sarah','Mary','Martha','Deborah','Rebecca','Hannah','Naomi','Judith','Elizabeth','Catherine','Margaret','Joy','Glory','Queen','Victoria','Beatrice','Florence','Evelyn','Doris','Lillian'];
$surnames = ['Okonkwo','Eze','Okafor','Nwosu','Obi','Igwe','Nwachukwu','Okeke','Onyema','Nwankwo','Okechukwu','Chukwuma','Onyekachi','Chibueze','Nnamdi','Uzodinma','Ikechukwu','Ogbonna','Nwafor','Okoro','Okpara','Okoli','Nwodo','Okereke','Oko','Onyishi','Otu','Ubah','Ude','Udo','Ugwu','Ugwuanyi','Ukpabi','Ukwu','Umeh','Umerah','Umunna','Unegbu','Urama','Uzodinma','Bello','Yahaya','Musa','Mohammed','Abubakar','Adamu','Aliyu','Sani','Ibrahim','Adeyemi','Adebayo','Adebisi','Adegoke','Adekunle','Adeniyi','Adeola','Adepoju','Adesina','Ajayi','Akinsanya','Akintola','Akinyemi','Alabi','Balogun','Bassey','Etim','Johnson','Kalu','Benson','Inyang'];
$allNames = array_merge($male, $female);
$emailDomains = ['gmail.com','yahoo.com','outlook.com','peculiarcollege.edu.ng'];
$occupations = ['Business Owner','Civil Servant','Teacher','Engineer','Doctor','Lawyer','Accountant','Trader','Farmer','Banker'];
$relationships = ['Father','Mother','Guardian','Uncle','Aunt'];
$qPool = ['B.Sc. in Education','B.A. in Education','B.Ed.','M.Ed.','B.Sc. with PGDE','B.A. with PGDE','M.Sc. in Education','B.Ed. (Hons)','B.Sc. in Mathematics Education','B.A. in English Education'];
$teacherRoles = ['LANG'=>'English/French Teacher','SCI'=>'Science Teacher','HUM'=>'Humanities Teacher','BUS'=>'Business Studies Teacher','VOC'=>'Vocational Studies Teacher'];

$pwd = password_hash('Password@123', PASSWORD_BCRYPT, ['cost' => 12]);

// ── state load/save ──────────────────────────────────────────────────────
function loadState(string $file): array {
    if (!is_file($file)) return ['phase' => 0, 'cursor' => 0];
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) return ['phase' => 0, 'cursor' => 0];
    return $data;
}
function saveState(string $file, array $s): void {
    @file_put_contents($file, json_encode($s), LOCK_EX);
}
function render($s, $log): void {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="1;url=' . htmlspecialchars($_SERVER['PHP_SELF'] . '?key=PIC2026seed', ENT_QUOTES) . '">';
    echo '<style>body{font-family:monospace;background:#0b1f3a;color:#cfd8e3;padding:20px}pre{white-space:pre-wrap}.log{color:#9fd1a0}.warn{color:#e0c76a}.cur{color:#ffd75e}</style></head><body>';
    echo '<h3>Seeding in progress — keep this tab open, it auto-refreshes.</h3>';
    echo '<p class="cur">Phase ' . (int)($s['phase'] + 1) . ' / 9 — cursor ' . (int)$s['cursor'] . '</p>';
    echo '<pre class="log">' . htmlspecialchars(implode("\n", array_slice($log, -20))) . '</pre>';
    echo '</body></html>';
    flush();
    exit;
}
function donePage(array $log): void {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:monospace;background:#0b1f3a;color:#9fd1a0;padding:20px}pre{white-space:pre-wrap}</style></head><body>';
    echo '<h2 style="color:#ffd75e">DONE — curriculum dataset is live.</h2>';
    echo '<pre>' . htmlspecialchars(implode("\n", $log)) . '</pre>';
    echo '<p>All user accounts: <b>Password@123</b><br>Admin: admin@peculiarcollege.edu.ng</p>';
    echo '<p>You can delete <code>_deploy_seed.php</code> and <code>.seed_state.json</code> from the server now.</p>';
    echo '</body></html>';
    exit;
}

// ── state machine ────────────────────────────────────────────────────────
$log = [];
$s = loadState($STATE_FILE);
$start = microtime(true);

try {
    while ((microtime(true) - $start) < $BUDGET_SECONDS) {
        $phase = (int)$s['phase'];
        $cursor = (int)$s['cursor'];
        $done = false;

        switch ($phase) {
            // 0 ─ wipe + foundation ─────────────────────────────────────
            case 0:
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                foreach ($tables as $t) $db->exec("TRUNCATE TABLE `{$t}`");
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                $log[] = 'Wiped all tables';

                $db->exec("INSERT INTO academic_sessions (session_name, start_date, end_date, is_current, status) VALUES ('2025/2026','2025-09-01','2026-08-31',1,'active')");
                $db->exec("INSERT INTO terms (session_id, term_name, start_date, end_date, is_current) VALUES (1,'First Term','2025-09-15','2025-12-19',1),(1,'Second Term','2026-01-06','2026-04-10',0),(1,'Third Term','2026-04-27','2026-08-14',0)");
                $db->exec("INSERT INTO departments (name, code, description) VALUES ('Languages','LANG','English, French, Arabic and Nigerian Languages'),('Sciences','SCI','Science Department'),('Humanities','HUM','Arts, Humanities and Social Sciences'),('Business Studies','BUS','Commercial and Business Studies'),('Vocational & Technical','VOC','Trade, Vocational and Technical Subjects')");
                $log[] = 'Session, terms, departments';

                $uStmt = $db->prepare("INSERT INTO users (username,email,password_hash,first_name,last_name,phone,role,status) VALUES (?,?,?,?,?,?,?,'active')");
                foreach ([
                    ['admin','admin@peculiarcollege.edu.ng','Admin','User','08012345670','admin'],
                    ['accountant1','accountant@peculiarcollege.edu.ng','Chioma','Nwosu','08012345675','accountant'],
                    ['teacher1','teacher@peculiarcollege.edu.ng','John','Okafor','08012345671','teacher'],
                    ['teacher2','teacher2@peculiarcollege.edu.ng','Sandra','Eze','08012345672','teacher'],
                    ['student1','student@peculiarcollege.edu.ng','Chidi','Okonkwo','08012345673','student'],
                    ['parent1','parent@peculiarcollege.edu.ng','Emeka','Okonkwo','08012345674','parent'],
                ] as $u) $uStmt->execute([$u[0], $u[1], $pwd, $u[2], $u[3], $u[4], $u[5]]);

                $depIds = array_map('intval', $db->query("SELECT id FROM departments ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
                $cStmt = $db->prepare("INSERT INTO classes (name,section,capacity,class_teacher_id,department_id,academic_session_id) VALUES (?,?,?,?,?,1)");
                $classDefs = [
                    ['JSS1','A',45,3,$depIds[0]], ['JSS1','B',45,null,$depIds[0]],
                    ['JSS2','A',45,null,$depIds[0]], ['JSS2','B',45,null,$depIds[0]],
                    ['JSS3','A',45,null,$depIds[0]], ['JSS3','B',45,null,$depIds[0]],
                    ['SS1','Science',40,null,$depIds[1]], ['SS1','Arts',40,null,$depIds[2]], ['SS1','Commercial',40,null,$depIds[3]],
                    ['SS2','Science',40,null,$depIds[1]], ['SS2','Arts',40,null,$depIds[2]], ['SS2','Commercial',40,null,$depIds[3]],
                    ['SS3','Science',40,null,$depIds[1]], ['SS3','Arts',40,null,$depIds[2]], ['SS3','Commercial',40,null,$depIds[3]],
                ];
                foreach ($classDefs as $c) $cStmt->execute($c);
                $classMap = [];
                foreach ($db->query("SELECT id,name,section FROM classes ORDER BY id")->fetchAll() as $c) $classMap[$c['name'].'|'.$c['section']] = (int)$c['id'];
                $classIdsOrdered = array_values($classMap);
                $classLevel = [];
                foreach ($db->query("SELECT id,name FROM classes")->fetchAll() as $c) $classLevel[$c['id']] = (strpos($c['name'],'SS')===0?'SS':'JSS');
                $log[] = 'Classes (' . count($classMap) . ')';

                $catMap = ['LANG'=>'humanities','CORE'=>'core','SCI'=>'science','HUM'=>'humanities','BUS'=>'business','VOC'=>'trade'];
                $jssCore = [['English Language','LANG',1],['Mathematics','CORE',1],['Citizenship & Heritage Studies','CORE',1],['National Values','CORE',1],['Basic Science','SCI',1],['Basic Technology','VOC',1],['Digital Literacy','VOC',1],['Cultural & Creative Arts','HUM',1],['Business Studies','BUS',1],['Physical & Health Education','VOC',1],['Religious Studies','HUM',1],['Nigerian Languages','LANG',1],['French','LANG',1],['Arabic','LANG',0]];
                $jssTrade = [['Agriculture','VOC',0],['Animal Husbandry','VOC',0],['Fisheries','VOC',0],['Horticulture','VOC',0],['Food Processing','VOC',0],['Home Making','VOC',0],['Catering & Craft Practice','VOC',0],['Fashion Design & Garment Making','VOC',0],['Beauty & Cosmetology','VOC',0],['Interior Decoration','VOC',0],['Welding & Fabrication','VOC',0],['Electrical Installation & Maintenance','VOC',0],['Electronics Repair & Maintenance','VOC',0],['Computer Hardware & GSM Repairs','VOC',0],['Plumbing','VOC',0],['Painting & Decoration','VOC',0],['Solar PV Installation & Maintenance','VOC',0],['Auto Mechanics','VOC',0],['Refrigeration & Air Conditioning','VOC',0],['Furniture Making','VOC',0],['Upholstery','VOC',0],['Data Processing','VOC',0]];
                $ssCore = [['English Language','LANG',1],['Mathematics','CORE',1],['Civic Education','CORE',1],['Digital Literacy','VOC',1],['Trade/Entrepreneurship','VOC',1]];
                $ssScience = [['Biology','SCI',1],['Chemistry','SCI',1],['Physics','SCI',1],['Further Mathematics','SCI',0],['Agricultural Science','SCI',0],['Computer Studies','SCI',0],['Data Processing','SCI',0],['Technical Drawing','SCI',0],['Geography','HUM',0]];
                $ssHumanities = [['Literature in English','HUM',0],['Government','HUM',0],['History','HUM',0],['Christian Religious Studies','HUM',0],['Islamic Religious Studies','HUM',0],['French','LANG',0],['Arabic','LANG',0],['Music','HUM',0],['Fine Arts','HUM',0],['Yoruba','LANG',0],['Igbo','LANG',0],['Hausa','LANG',0]];
                $ssBusiness = [['Financial Accounting','BUS',1],['Commerce','BUS',1],['Economics','BUS',1],['Office Practice','BUS',0],['Marketing','BUS',0],['Insurance','BUS',0]];
                $ssTrade = [['Agriculture','VOC',0],['Animal Husbandry','VOC',0],['Fisheries','VOC',0],['Horticulture','VOC',0],['Food Processing','VOC',0],['Catering Craft Practice','VOC',0],['Home Management','VOC',0],['Fashion Design & Garment Making','VOC',0],['Beauty & Cosmetology','VOC',0],['Interior Decoration','VOC',0],['Electrical Installation & Maintenance','VOC',0],['Electronics Repair & Maintenance','VOC',0],['Computer Hardware & GSM Repairs','VOC',0],['Solar PV Installation & Maintenance','VOC',0],['Plumbing','VOC',0],['Welding & Fabrication','VOC',0],['Auto Mechanics','VOC',0],['Refrigeration & Air Conditioning','VOC',0],['Furniture Making','VOC',0],['Upholstery','VOC',0]];

                $subjStmt = $db->prepare("INSERT INTO subjects (class_id,name,code,teacher_id,credit_unit,is_compulsory,category,level) VALUES (?,?,?,?,1,?,?,?)");
                $subjectsByClass = []; $subjectCount = 0; $codeCount = [];
                foreach ($classMap as $key => $cid) {
                    [$name, $section] = explode('|', $key);
                    $level = strpos($name, 'SS') === 0 ? 'SS' : 'JSS';
                    $isJss = $level === 'JSS';
                    $list = $isJss ? $jssCore : $ssCore;
                    if (!$isJss) {
                        if ($section === 'Science')    $list = array_merge($list, $ssScience);
                        if ($section === 'Arts')       $list = array_merge($list, $ssHumanities);
                        if ($section === 'Commercial') $list = array_merge($list, $ssBusiness);
                    }
                    $list = array_merge($list, $isJss ? $jssTrade : $ssTrade);
                    $added = [];
                    foreach ($list as $item) {
                        if (isset($added[$item[0]])) continue;
                        $added[$item[0]] = true;
                        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$item[0]),0,4)) . '_' . $cid;
                        if (isset($codeCount[$base])) { $codeCount[$base]++; $code = $base . '_' . $codeCount[$base]; }
                        else { $codeCount[$base] = 1; $code = $base; }
                        $subjStmt->execute([$cid, $item[0], $code, null, $item[2], $catMap[$item[1]], $level]);
                        $subjectsByClass[$cid][] = ['id'=>(int)$db->lastInsertId(), 'name'=>$item[0], 'is_compulsory'=>$item[2]];
                        $subjectCount++;
                    }
                }
                $log[] = 'Subjects (' . $subjectCount . ')';

                // teachers: user1/2 rows exist; create teacher rows for ALL 50 teacher users
                shuffle($allNames);
                $teacherUsers = $db->query("SELECT id FROM users WHERE role='teacher' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN); // 3,4
                $tRows = [];
                $nextUid = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM users")->fetchColumn() + 1;
                $uStmt2 = $db->prepare("INSERT INTO users (username,email,password_hash,first_name,last_name,phone,role,status) VALUES (?,?,?,?,?,?,?,'active')");
                $tUserRows = [];
                for ($i = 0; $i < 48; $i++) {
                    $uid = $nextUid++;
                    $g = $i % 2; $fname = $g ? $female[$i % count($female)] : $male[$i % count($male)]; $lname = $surnames[$i % count($surnames)];
                    $email = strtolower($fname . '.' . $lname . $uid) . '@' . $emailDomains[$i % count($emailDomains)];
                    $tUserRows[] = [$uid, 'teacher' . $uid, $email, $fname, $lname, '080' . str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT)];
                }
                foreach ($tUserRows as $r) $uStmt2->execute([$r[1], $r[2], $pwd, $r[3], $r[4], $r[5], 'teacher']);
                $teacherUsers = array_merge($teacherUsers, array_map('intval', $db->query("SELECT id FROM users WHERE role='teacher' AND id NOT IN (" . implode(',', $teacherUsers) . ") ORDER BY id")->fetchAll(PDO::FETCH_COLUMN)));
                $deptIdx = 0;
                foreach ($teacherUsers as $uid) {
                    $emp = 'TCH' . str_pad($uid, 4, '0', STR_PAD_LEFT);
                    $tRows[] = [$uid, $emp, $qPool[$deptIdx % count($qPool)], $depIds[$deptIdx % count($depIds)], sprintf('202%d-%02d-%02d', mt_rand(3,5), mt_rand(1,12), mt_rand(1,28))];
                    $deptIdx++;
                }
                batchInsert($db, 'teachers', ['user_id','employee_id','qualification','department_id','date_hired'], $tRows);
                $allTeachers = $db->query("SELECT id,user_id FROM teachers ORDER BY id")->fetchAll();
                $teacherIdByUser = []; foreach ($allTeachers as $t) $teacherIdByUser[$t['user_id']] = (int)$t['id'];
                $log[] = 'Teachers (' . count($allTeachers) . ')';

                $allocStmt = $db->prepare("INSERT INTO subject_allocations (teacher_id,class_id,subject_id,academic_session_id) VALUES (?,?,?,1)");
                foreach ($subjectsByClass as $cid => $subjects) {
                    foreach ($subjects as $subj) {
                        $allocStmt->execute([$allTeachers[array_rand($allTeachers)]['id'], $cid, $subj['id']]);
                    }
                }
                $log[] = 'Subject allocations';
                $s['phase'] = 1; $s['cursor'] = 0; $done = true;
                break;

            // 1 ─ students (batches of 40) ──────────────────────────────
            case 1:
                $totalStudents = 500;
                $nextUid = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM users")->fetchColumn() + 1;
                $classIdsOrdered = array_map('intval', $db->query("SELECT id FROM classes ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
                $dist = array_fill_keys($classIdsOrdered, 0);
                $base = intdiv($totalStudents, count($classIdsOrdered));
                $rem = $totalStudents % count($classIdsOrdered);
                foreach ($classIdsOrdered as $i => $cid) $dist[$cid] = $base + ($i < $rem ? 1 : 0);
                $byClass = [];
                foreach ($dist as $cid => $n) for ($i = 0; $i < $n; $i++) $byClass[] = $cid;
                $cur = $cursor;
                $userCols = ['username','email','password_hash','first_name','last_name','phone','role','status'];
                $genders = ['male','female'];
                $classLevel = [];
                foreach ($db->query("SELECT id,name FROM classes")->fetchAll() as $c) $classLevel[$c['id']] = (strpos($c['name'],'SS')===0?'SS':'JSS');
                $batchSize = 40;
                $end = min($totalStudents, $cur + $batchSize);
                $uRows = []; $sRows = [];
                for ($i = $cur; $i < $end; $i++) {
                    $uid = $nextUid++; $gender = $genders[mt_rand(0,1)];
                    $pool = $gender === 'male' ? $male : $female;
                    $fname = $pool[mt_rand(0,count($pool)-1)]; $lname = $surnames[mt_rand(0,count($surnames)-1)];
                    $cid = $byClass[$i];
                    $uRows[] = ['student' . str_pad($uid,4,'0',STR_PAD_LEFT), strtolower($fname.'.'.$lname.str_pad($uid,4,'0',STR_PAD_LEFT)).'@peculiarcollege.edu.ng', $pwd, $fname, $lname, '080'.str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT), 'student', 'active'];
                    $dobY = $classLevel[$cid]==='SS' ? mt_rand(2006,2009) : mt_rand(2011,2014);
                    $sRows[] = ['PIC2025'.str_pad($i+1,3,'0',STR_PAD_LEFT), $cid, sprintf('%d-%02d-%02d',$dobY,mt_rand(1,12),mt_rand(1,28)), $gender, '2025-09-'.str_pad(mt_rand(10,20),2,'0',STR_PAD_LEFT), 'active'];
                }
                batchInsert($db,'users',$userCols,$uRows);
                $newUsers = $db->query("SELECT id FROM users WHERE role='student' AND id NOT IN (SELECT user_id FROM students WHERE user_id IS NOT NULL) ORDER BY id LIMIT " . count($sRows))->fetchAll(PDO::FETCH_COLUMN);
                $ins = [];
                foreach ($sRows as $i => $sr) { if (!isset($newUsers[$i])) continue; $ins[] = [$newUsers[$i], $sr[0], $sr[1], $sr[2], $sr[3], $sr[4], $sr[5]]; }
                batchInsert($db,'students',['user_id','admission_no','class_id','date_of_birth','gender','enrollment_date','status'],$ins);
                $log[] = "Students: " . min($totalStudents, $end) . "/$totalStudents";
                if ($end >= $totalStudents) { $s['phase'] = 2; $s['cursor'] = 0; }
                else $s['cursor'] = $end;
                $done = true;
                break;

            // 2 ─ parents + links ───────────────────────────────────────
            case 2:
                $students = $db->query("SELECT id FROM students ORDER BY id")->fetchAll();
                $numParents = max(1, (int)ceil(count($students) / 3));
                $nextUid = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM users")->fetchColumn() + 1;
                $uRows = []; $pRows = [];
                for ($i = 0; $i < $numParents; $i++) {
                    $uid = $nextUid++;
                    $fname = $male[mt_rand(0,count($male)-1)]; $lname = $surnames[mt_rand(0,count($surnames)-1)];
                    $uRows[] = ['parent' . $uid, strtolower($fname.'.'.$lname.$uid).'@gmail.com', $pwd, $fname, $lname, '080'.str_pad(mt_rand(10000000,99999999),8,'0',STR_PAD_LEFT), 'parent', 'active'];
                    $pRows[] = [$uid, $occupations[mt_rand(0,9)], $relationships[mt_rand(0,4)]];
                }
                batchInsert($db,'users',['username','email','password_hash','first_name','last_name','phone','role','status'],$uRows);
                $newParents = $db->query("SELECT id FROM users WHERE role='parent' AND id NOT IN (SELECT user_id FROM parents WHERE user_id IS NOT NULL) ORDER BY id LIMIT " . count($pRows))->fetchAll(PDO::FETCH_COLUMN);
                $ins = [];
                foreach ($pRows as $i => $pr) { if (!isset($newParents[$i])) continue; $ins[] = [$newParents[$i], $pr[1], $pr[2]]; }
                batchInsert($db,'parents',['user_id','occupation','relationship'],$ins);
                $allParents = $db->query("SELECT id FROM parents ORDER BY id")->fetchAll();
                $sp = [];
                foreach ($students as $i => $st) $sp[] = [$st['id'], $allParents[$i % count($allParents)]['id'], ($i%5===0)?0:1];
                batchInsert($db,'student_parents',['student_id','parent_id','is_guardian'],$sp,200,true);
                $log[] = 'Parents (' . $numParents . ') linked';
                $s['phase'] = 3; $s['cursor'] = 0; $done = true;
                break;

            // 3 ─ fee structure + fees + payments ───────────────────────
            case 3:
                $jssFees = [['Tuition Fee',80000.00,1],['Development Levy',20000.00,1],['Sports Fee',10000.00,0],['Library Fee',8000.00,1],['ICT Fee',5000.00,1],['Science Lab Fee',3000.00,0]];
                $ssFees = [['Tuition Fee',120000.00,1],['Development Levy',25000.00,1],['Sports Fee',12000.00,0],['Library Fee',10000.00,1],['ICT Fee',8000.00,1],['Science Lab Fee',10000.00,1]];
                $terms = array_map('intval',$db->query("SELECT id FROM terms WHERE session_id=1")->fetchAll(PDO::FETCH_COLUMN));
                if ($cursor === 0) {
                    $classLevel = [];
                    foreach ($db->query("SELECT id,name FROM classes")->fetchAll() as $c) $classLevel[$c['id']] = (strpos($c['name'],'SS')===0?'SS':'JSS');
                    $fsRows = [];
                    foreach ($classLevel as $cid => $lv) {
                        $fees = $lv==='JSS' ? $jssFees : $ssFees;
                        foreach ($terms as $tid) foreach ($fees as $f) $fsRows[] = [$f[0],$f[1],$cid,$tid,$f[2]];
                    }
                    batchInsert($db,'fee_structure',['fee_name','amount','class_id','term_id','is_mandatory'],$fsRows);
                    $log[] = 'Fee structure (' . count($fsRows) . ')';
                    $s['cursor'] = 1;
                    $cursor = 1;
                }
                $allFS = $db->query("SELECT id,class_id,term_id FROM fee_structure ORDER BY id")->fetchAll();
                $fsIds = []; foreach ($allFS as $fs) $fsIds[$fs['class_id']][$fs['term_id']][] = (int)$fs['id'];
                $students = $db->query("SELECT id,class_id FROM students ORDER BY id")->fetchAll();
                $classLevel = [];
                foreach ($db->query("SELECT id,name FROM classes")->fetchAll() as $c) $classLevel[$c['id']] = (strpos($c['name'],'SS')===0?'SS':'JSS');
                $payMethods = ['cash','card','transfer','pos'];
                $batchSize = 60;
                $startI = $cursor - 1;
                $endI = min(count($students), $startI + $batchSize);
                $feeRows = []; $paymentRows = [];
                $minFeeId = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM fees")->fetchColumn();
                for ($si = $startI; $si < $endI; $si++) {
                    $st = $students[$si]; $cid = $st['class_id'];
                    foreach ($terms as $tid) {
                        foreach (($fsIds[$cid][$tid] ?? []) as $fsid) {
                            $total = (in_array($fsid, array_slice($fsIds[$cid][$tid] ?? [],0,2)) ? ($classLevel[$cid]==='JSS'?80000.00:120000.00) : (mt_rand(0,1)?10000.00:8000.00));
                            $rem = ($si + 1) % 10;
                            if ($rem < 3) { $paid = $total; $status='paid'; }
                            elseif ($rem < 6) { $paid = round($total*mt_rand(30,70)/100,2); $status='partial'; }
                            elseif ($rem < 8) { $paid = 0; $status='unpaid'; }
                            else { $paid = $total; $status='paid'; }
                            $due = sprintf('2025-%02d-15',($tid==1)?10:(($tid==2)?2:5));
                            $feeRows[] = [$st['id'],$fsid,$total,$paid,round($total-$paid,2),$due,$status];
                        }
                    }
                }
                batchInsert($db,'fees',['student_id','fee_structure_id','total_amount','paid_amount','balance','due_date','status'],$feeRows,300);
                $maxFeeId = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM fees")->fetchColumn();
                if (!empty($feeRows)) {
                    $paidStmt = $db->prepare("SELECT id,paid_amount,due_date FROM fees WHERE paid_amount>0 AND id > ? AND id <= ?");
                    $paidStmt->execute([$minFeeId, $maxFeeId]);
                    $paidFees = $paidStmt->fetchAll();
                    foreach ($paidFees as $fee) {
                        $paid = (float)$fee['paid_amount']; $num = mt_rand(1,3); $remaining = $paid;
                        for ($p=0;$p<$num;$p++) {
                            $amt = ($p < $num-1) ? round($remaining*mt_rand(30,70)/100,2) : $remaining;
                            $amt = round($amt,2); if ($amt<=0) continue; $remaining -= $amt;
                            $ref = 'TRX' . str_pad($fee['id'],8,'0',STR_PAD_LEFT) . $p;
                            $rcp = 'RCP' . str_pad($fee['id'],8,'0',STR_PAD_LEFT) . $p;
                            $pdate = date('Y-m-d', strtotime($fee['due_date'].' -'.mt_rand(0,30).' days'));
                            $paymentRows[] = [$fee['id'],$amt,$payMethods[mt_rand(0,3)],$ref,$rcp,$pdate,null,null,'approved'];
                        }
                    }
                    batchInsert($db,'payments',['fee_id','amount_paid','payment_method','transaction_ref','receipt_no','payment_date','verified_by','proof_document','status'],$paymentRows,300);
                }
                $log[] = 'Fees+payments: ' . min(count($students), $endI) . '/' . count($students);
                if ($endI >= count($students)) { $s['phase'] = 4; $s['cursor'] = 0; }
                else $s['cursor'] = $endI + 1;
                $done = true;
                break;

            // 4 ─ timetable ─────────────────────────────────────────────
            case 4:
                $subjectsByClass = [];
                foreach ($db->query("SELECT id,class_id,name,is_compulsory FROM subjects ORDER BY class_id")->fetchAll() as $s2) {
                    $subjectsByClass[$s2['class_id']][] = ['id'=>(int)$s2['id'],'name'=>$s2['name'],'is_compulsory'=>(int)$s2['is_compulsory']];
                }
                $allTeachers = $db->query("SELECT id FROM teachers ORDER BY id")->fetchAll();
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
                $slotsStart = ['08:00:00','09:00:00','10:00:00','11:00:00','12:00:00','13:00:00','14:00:00','15:00:00','16:00:00'];
                $slotsEnd   = ['08:50:00','09:50:00','10:50:00','11:50:00','12:50:00','13:50:00','14:50:00','15:50:00','16:40:00'];
                $ttRows = [];
                foreach ($subjectsByClass as $cid => $subjects) {
                    $daysOfWeek = mt_rand(3,5);
                    foreach (array_rand($days, $daysOfWeek) as $dayIdx) {
                        $count = min(count($subjects), 3);
                        $slots = array_rand($slotsStart, $count); if (!is_array($slots)) $slots = [$slots];
                        $subIndices = array_rand($subjects, $count); if (!is_array($subIndices)) $subIndices = [$subIndices];
                        foreach ($slots as $k => $slotIdx) {
                            $subj = $subjects[$subIndices[$k % count($subIndices)]];
                            $ttRows[] = [$cid, $subj['id'], $allTeachers[array_rand($allTeachers)]['id'], $days[$dayIdx], $slotsStart[$slotIdx], $slotsEnd[$slotIdx], 'Room '.mt_rand(1,30)];
                        }
                    }
                }
                batchInsert($db,'timetable',['class_id','subject_id','teacher_id','day_of_week','start_time','end_time','room'],$ttRows,300);
                $log[] = 'Timetable (' . count($ttRows) . ')';
                $s['phase'] = 5; $s['cursor'] = 0; $done = true;
                break;

            // 5 ─ attendance (batches of 60 students) ──────────────────
            case 5:
                $students = $db->query("SELECT id,class_id FROM students ORDER BY id")->fetchAll();
                $termStart='2025-09-15'; $termEnd='2025-12-19';
                $schoolDays=[]; $cur=strtotime($termStart); $end=strtotime($termEnd);
                while ($cur<=$end) { $d=(int)date('w',$cur); if($d>=1 && $d<=5) $schoolDays[]=date('Y-m-d',$cur); $cur=strtotime('+1 day',$cur); }
                $statuses=['present','present','present','present','present','present','present','present','absent','late'];
                $batchSize = 60;
                $endI = min(count($students), $cursor + $batchSize);
                $attRows = []; $attCount = 0;
                for ($si = $cursor; $si < $endI; $si++) {
                    $st = $students[$si];
                    foreach ($schoolDays as $day) $attRows[] = [$st['id'],$st['class_id'],$day,$statuses[mt_rand(0,9)],null,1];
                }
                $attCount += batchInsert($db,'attendance',['student_id','class_id','date','status','remark','marked_by'],$attRows,500);
                $log[] = 'Attendance: ' . min(count($students), $endI) . '/' . count($students);
                if ($endI >= count($students)) { $s['phase'] = 6; $s['cursor'] = 0; }
                else $s['cursor'] = $endI;
                $done = true;
                break;

            // 6 ─ results (per class+term unit) ────────────────────────
            case 6:
                $classIdsOrdered = array_map('intval', $db->query("SELECT id FROM classes ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
                $terms = array_map('intval',$db->query("SELECT id FROM terms WHERE session_id=1")->fetchAll(PDO::FETCH_COLUMN));
                foreach ($terms as $tid) $db->exec("INSERT IGNORE INTO result_settings (session_id,term_id) VALUES (1,$tid)");
                foreach ($classIdsOrdered as $cid) $db->exec("INSERT IGNORE INTO promotion_config (session_id,class_id,pass_mark,min_subjects_pass) VALUES (1,$cid,40,5)");
                $allTeachers = $db->query("SELECT id FROM teachers ORDER BY id")->fetchAll();
                $totalUnits = count($classIdsOrdered) * count($terms);
                if ($cursor >= $totalUnits) { $s['phase'] = 7; $s['cursor'] = 0; $done = true; break; }
                $cid = $classIdsOrdered[intdiv($cursor, count($terms))];
                $tid = $terms[$cursor % count($terms)];
                $termBias = ($tid==1)?0.55:(($tid==2)?0.65:0.70);
                $subjects = $db->query("SELECT id FROM subjects WHERE class_id=$cid ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
                $students = $db->query("SELECT id FROM students WHERE class_id=$cid ORDER BY id")->fetchAll();
                $rows = [];
                $resCols = ['student_id','class_id','subject_id','session_id','term_id','assignment_score','assignment2_score','test_score','test2_score','ca_total','project_score','exam_score','total_score','grade','subject_position','status','entered_by'];
                foreach ($students as $st) {
                    foreach ($subjects as $subjId) {
                        $a1=randScore(0,10,$termBias*0.9); $a2=randScore(0,10,$termBias*0.9);
                        $t1=randScore(0,10,$termBias*0.9); $t2=randScore(0,10,$termBias*0.9);
                        $ca=min(40,round($a1+$a2+$t1+$t2)); $proj=randScore(0,10,$termBias*0.9);
                        $exam=round(gaussRandom($termBias*60,60*0.15)); $exam=max(0,min(60,$exam));
                        $total=$ca+$exam; $grade=gradeFromScore($total);
                        $rows[]=[$st['id'],$cid,$subjId,1,$tid,$a1,$a2,$t1,$t2,$ca,$proj,$exam,$total,$grade,0,'published',$allTeachers[array_rand($allTeachers)]['id']];
                    }
                }
                batchInsert($db,'result_scores',$resCols,$rows,300);
                $log[] = 'Results class ' . $cid . ' term ' . $tid . ' (' . count($rows) . ' rows)';
                $s['cursor'] = $cursor + 1;
                $done = true;
                break;

            // 7 ─ positions (per class+term unit, one SQL each) ────────
            case 7:
                $classIdsOrdered = array_map('intval', $db->query("SELECT id FROM classes ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
                $terms = array_map('intval',$db->query("SELECT id FROM terms WHERE session_id=1")->fetchAll(PDO::FETCH_COLUMN));
                $totalUnits = count($classIdsOrdered) * count($terms);
                if ($cursor >= $totalUnits) { $s['phase'] = 8; $s['cursor'] = 0; $done = true; break; }
                $cid = $classIdsOrdered[intdiv($cursor, count($terms))];
                $tid = $terms[$cursor % count($terms)];
                $db->exec("UPDATE result_scores r JOIN (
                        SELECT r2.id, (SELECT COUNT(*) + 1 FROM result_scores r3
                                      WHERE r3.subject_id=r2.subject_id AND r3.session_id=1
                                        AND r3.term_id=r2.term_id AND r3.total_score>r2.total_score) AS pos
                        FROM result_scores r2 WHERE r2.class_id=$cid AND r2.session_id=1 AND r2.term_id=$tid) t ON r.id=t.id
                    SET r.subject_position=t.pos");
                $log[] = 'Positions class ' . $cid . ' term ' . $tid;
                $s['cursor'] = $cursor + 1;
                $done = true;
                break;

            // 8 ─ subject selections ───────────────────────────────────
            case 8:
                $subjectsByClass = [];
                foreach ($db->query("SELECT id,class_id,is_compulsory FROM subjects ORDER BY class_id")->fetchAll() as $s2) {
                    $subjectsByClass[$s2['class_id']][] = ['id'=>(int)$s2['id'],'is_compulsory'=>(int)$s2['is_compulsory']];
                }
                $studentsByClass = [];
                foreach ($db->query("SELECT id,class_id FROM students ORDER BY id")->fetchAll() as $st) $studentsByClass[$st['class_id']][] = $st['id'];
                $selStmt = $db->prepare("INSERT IGNORE INTO student_subject_selections (student_id,subject_id,academic_session_id,is_core) VALUES (?,?,1,?)");
                $selCount = 0;
                foreach ($studentsByClass as $cid => $studentIds) {
                    $subjects = $subjectsByClass[$cid] ?? [];
                    $core = array_values(array_filter($subjects, fn($x)=>$x['is_compulsory']));
                    $optional = array_values(array_filter($subjects, fn($x)=>!$x['is_compulsory']));
                    foreach ($studentIds as $sid) {
                        foreach ($core as $s2) { $selStmt->execute([$sid,$s2['id'],1]); $selCount++; }
                        if ($optional) {
                            $picks = array_rand($optional, min(2,count($optional))); if (!is_array($picks)) $picks=[$picks];
                            foreach ($picks as $pi) { $selStmt->execute([$sid,$optional[$pi]['id'],0]); $selCount++; }
                        }
                    }
                }
                $log[] = 'Subject selections (' . $selCount . ')';
                $s['phase'] = 9; $s['cursor'] = 0; $done = true;
                break;

            default:
                $done = true;
                break;
        }

        saveState($STATE_FILE, $s);
        if ($phase === 9 || ($phase === 8 && $done)) break;
        if ($done) continue; // phase advanced; keep going within budget
        break;
    }
} catch (Throwable $e) {
    saveState($STATE_FILE, $s);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:monospace;background:#300;color:#fcc;padding:20px}</style></head><body>';
    echo '<h2>ERROR</h2><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
    echo '<p><a href="' . htmlspecialchars($_SERVER['PHP_SELF'] . '?key=PIC2026seed', ENT_QUOTES) . '">Retry</a></p>';
    echo '</body></html>';
    exit;
}

if ((int)$s['phase'] >= 9) {
    $counts = [
        'Students' => (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'Teachers' => (int)$db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
        'Subjects' => (int)$db->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
        'Results' => (int)$db->query("SELECT COUNT(*) FROM result_scores")->fetchColumn(),
        'Fees' => (int)$db->query("SELECT COUNT(*) FROM fees")->fetchColumn(),
        'Payments' => (int)$db->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
        'Attendance' => (int)$db->query("SELECT COUNT(*) FROM attendance")->fetchColumn(),
        'Selections' => (int)$db->query("SELECT COUNT(*) FROM student_subject_selections")->fetchColumn(),
    ];
    foreach ($counts as $k => $v) $log[] = "$k: $v";
    donePage($log);
}

render($s, $log);