<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Import Results (CSV)';
$db = getDB();

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$subjects = $db->query("SELECT id, name, code FROM subjects ORDER BY name")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_GET['term_id'] ?? 0);
$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$step = $_POST['step'] ?? 'upload';
$error = '';
$success = '';
$previewData = [];

if ($selectedSession) {
    $stmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id");
    $stmt->execute([$selectedSession]);
    $terms = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $data = json_decode($_POST['import_data'] ?? '[]', true);
    $imported = 0;
    $skipped = 0;

    foreach ($data as $row) {
        $admissionNo = trim($row['admission_no'] ?? '');
        $assign1 = (float)($row['assignment1'] ?? 0);
        $assign2 = (float)($row['assignment2'] ?? 0);
        $test1 = (float)($row['test1'] ?? 0);
        $test2 = (float)($row['test2'] ?? 0);
        $exam = (float)($row['exam'] ?? 0);
        $project = (float)($row['project'] ?? 0);

        if (empty($admissionNo)) { $skipped++; continue; }

        $stmt = $db->prepare("SELECT id FROM students WHERE admission_no = ? AND class_id = ? AND status = 'active'");
        $stmt->execute([$admissionNo, $selectedClass]);
        $studentId = $stmt->fetchColumn();
        if (!$studentId) { $skipped++; continue; }

        $settings = getResultSettings($db, $selectedSession, $selectedTerm);
        $caTotal = computeCaTotal($assign1, $assign2, $test1, $test2, $settings['ca_max']);
        $totalScore = computeTotalScore($caTotal, $exam);

        $exists = $db->prepare("SELECT id FROM result_scores WHERE student_id = ? AND class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ?");
        $exists->execute([$studentId, $selectedClass, $selectedSubject, $selectedSession, $selectedTerm]);
        $existingId = $exists->fetchColumn();

        if ($existingId) {
            $db->prepare("UPDATE result_scores SET assignment_score = ?, assignment2_score = ?, test_score = ?, test2_score = ?, exam_score = ?, project_score = ?, ca_total = ?, total_score = ? WHERE id = ?")
                ->execute([$assign1, $assign2, $test1, $test2, $exam, $project, $caTotal, $totalScore, $existingId]);
            computeAndSaveResult($db, $existingId, $selectedSession, $selectedTerm);
        } else {
            $db->prepare("INSERT INTO result_scores (student_id, class_id, subject_id, session_id, term_id, assignment_score, assignment2_score, test_score, test2_score, exam_score, project_score, ca_total, total_score, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'draft')")
                ->execute([$studentId, $selectedClass, $selectedSubject, $selectedSession, $selectedTerm, $assign1, $assign2, $test1, $test2, $exam, $project, $caTotal, $totalScore]);
            computeAndSaveResult($db, $db->lastInsertId(), $selectedSession, $selectedTerm);
        }
        $imported++;
    }
    $success = "Import completed: $imported records imported, $skipped skipped.";
    $step = 'upload';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    $selectedSession = (int)$_POST['session_id'];
    $selectedTerm = (int)$_POST['term_id'];
    $selectedClass = (int)$_POST['class_id'];
    $selectedSubject = (int)$_POST['subject_id'];

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid CSV file.';
    } elseif (!$selectedClass || !$selectedSubject || !$selectedTerm) {
        $error = 'Please select class, subject, and term.';
    } else {
        $tmpFile = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpFile, 'r');
        if (!$handle) { $error = 'Could not read file.'; }
        else {
            $headers = fgetcsv($handle);
            if (!$headers) { $error = 'Invalid CSV format.'; fclose($handle); }
            else {
                $headerMap = [];
                $required = ['admission_no', 'assignment1', 'test1', 'exam'];
                foreach ($headers as $i => $h) {
                    $headerMap[trim(strtolower($h))] = $i;
                }
                $missing = array_diff($required, array_keys($headerMap));
                if (!empty($missing)) {
                    $error = 'Missing required columns: ' . implode(', ', $missing);
                } else {
                    while (($row = fgetcsv($handle)) !== false) {
                        $entry = [
                            'admission_no' => $row[$headerMap['admission_no']] ?? '',
                            'assignment1' => $row[$headerMap['assignment1']] ?? 0,
                            'assignment2' => $row[$headerMap['assignment2']] ?? 0,
                            'test1' => $row[$headerMap['test1']] ?? 0,
                            'test2' => $row[$headerMap['test2']] ?? 0,
                            'exam' => $row[$headerMap['exam']] ?? 0,
                            'project' => $row[$headerMap['project']] ?? 0,
                        ];

                        $stmt = $db->prepare("SELECT id, u.first_name, u.last_name FROM students s JOIN users u ON s.user_id = u.id WHERE s.admission_no = ? AND s.class_id = ?");
                        $stmt->execute([$entry['admission_no'], $selectedClass]);
                        $studentData = $stmt->fetch();
                        $entry['found'] = $studentData ? true : false;
                        $entry['student_name'] = $studentData ? $studentData['first_name'] . ' ' . $studentData['last_name'] : 'NOT FOUND';

                        $previewData[] = $entry;
                    }
                }
                fclose($handle);
            }
        }
        if (!empty($previewData)) $step = 'preview';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-import me-2 text-success"></i>Import Results (CSV)</h4>
    <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($success): ?><div class="alert alert-success py-2"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= $error ?></div><?php endif; ?>

<?php if ($step === 'upload'): ?>
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-upload me-2"></i>Upload CSV File</div>
    <div class="card-body">
        <div class="alert alert-info py-2">
            <i class="fas fa-info-circle me-1"></i>
            CSV must have columns: <strong>admission_no, assignment1, assignment2, test1, test2, exam, project</strong>.
            <a href="#sample" data-bs-toggle="collapse">Show sample</a>
            <div class="collapse mt-2" id="sample">
                <pre class="mb-0 small">admission_no,assignment1,assignment2,test1,test2,exam,project
PIC001,8,7,9,8,45,0
PIC002,7,6,8,7,40,0</pre>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="preview" value="1">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" required>
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select" required>
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $sub): ?>
                    <option value="<?= $sub['id'] ?>" <?= $selectedSubject === $sub['id'] ? 'selected' : '' ?>><?= sanitizeInput($sub['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">CSV File</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-eye me-1"></i>Preview & Import</button>
            </div>
        </form>
    </div>
</div>
<?php elseif ($step === 'preview'): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-eye me-2"></i>Preview Import Data</span>
        <span class="badge bg-<?= count($previewData) > 0 ? 'success' : 'danger' ?>"><?= count($previewData) ?> rows</span>
    </div>
    <div class="card-body p-0">
        <form method="POST">
            <input type="hidden" name="confirm_import" value="1">
            <input type="hidden" name="session_id" value="<?= $selectedSession ?>">
            <input type="hidden" name="term_id" value="<?= $selectedTerm ?>">
            <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
            <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">
            <input type="hidden" name="import_data" value='<?= json_encode($previewData) ?>'>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Assignment 1</th>
                            <th>Assignment 2</th>
                            <th>Test 1</th>
                            <th>Test 2</th>
                            <th>Exam</th>
                            <th>Project</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $row): ?>
                        <tr class="<?= $row['found'] ? '' : 'table-danger' ?>">
                            <td><?= sanitizeInput($row['admission_no']) ?></td>
                            <td><?= sanitizeInput($row['student_name']) ?></td>
                            <td><?= $row['assignment1'] ?></td>
                            <td><?= $row['assignment2'] ?></td>
                            <td><?= $row['test1'] ?></td>
                            <td><?= $row['test2'] ?></td>
                            <td><?= $row['exam'] ?></td>
                            <td><?= $row['project'] ?></td>
                            <td><?= $row['found'] ? '<span class="badge bg-success">Found</span>' : '<span class="badge bg-danger">Not Found</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <a href="<?= BASE_URL ?>/admin/results/import.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirm Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
