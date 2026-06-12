<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Question Bank';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$editQ = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_upload'])) {
    $subjectId = (int)($_POST['bulk_subject_id'] ?? 0);
    $classId = (int)($_POST['bulk_class_id'] ?? 0);
    $termIdBulk = (int)($_POST['bulk_term_id'] ?? 0);
    $qTypeBulk = sanitizeInput($_POST['bulk_question_type'] ?? 'mcq');

    if (!isset($_FILES['bulk_file']) || $_FILES['bulk_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Please select a valid CSV file to upload.'; $msgType = 'danger';
    } elseif (!$subjectId || !$classId) {
        $msg = 'Subject and class are required for bulk upload.'; $msgType = 'danger';
    } else {
        $file = $_FILES['bulk_file']['tmp_name'];
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        if (!$header || trim(mb_strtolower($header[0])) !== 'question') {
            $msg = 'Invalid CSV format. First column must be "question". Download the sample template.'; $msgType = 'danger';
        } else {
            $imported = 0; $errors = [];
            $lineNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $lineNum++;
                $qText = trim($row[0] ?? '');
                if (empty($qText)) continue;
                $correct = trim($row[5] ?? '');
                $marks = (float)($row[6] ?? 1);
                $explanation = trim($row[7] ?? '');
                $optionA = trim($row[1] ?? '');
                $optionB = trim($row[2] ?? '');
                $optionC = trim($row[3] ?? '');
                $optionD = trim($row[4] ?? '');

                try {
                    $stmt = $db->prepare("INSERT INTO exam_questions (teacher_id, subject_id, class_id, term_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, marks, explanation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$userId, $subjectId, $classId, $termIdBulk, $qTypeBulk, $qText, $optionA, $optionB, $optionC, $optionD, $correct, $marks, $explanation]);
                    $imported++;
                } catch (Exception $e) {
                    $errors[] = "Line $lineNum: " . $e->getMessage();
                }
            }
            fclose($handle);
            $msg = "$imported question(s) imported successfully.";
            if (!empty($errors)) $msg .= ' ' . implode('; ', $errors);
            if ($imported > 0) $msgType = 'success'; else $msgType = 'danger';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_question'])) {
    $qId = (int)($_POST['question_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $term_id = (int)($_POST['term_id'] ?? 0);
    $qType = sanitizeInput($_POST['question_type'] ?? 'mcq');
    $qText = sanitizeInput($_POST['question_text'] ?? '');
    $optionA = sanitizeInput($_POST['option_a'] ?? '');
    $optionB = sanitizeInput($_POST['option_b'] ?? '');
    $optionC = sanitizeInput($_POST['option_c'] ?? '');
    $optionD = sanitizeInput($_POST['option_d'] ?? '');
    $correct = sanitizeInput($_POST['correct_answer'] ?? '');
    $marks = (float)($_POST['marks'] ?? 1);
    $explanation = sanitizeInput($_POST['explanation'] ?? '');

    if ($qText && $subjectId && $classId) {
        if ($qId) {
            $stmt = $db->prepare("UPDATE exam_questions SET subject_id=?, class_id=?, term_id=?, question_type=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, marks=?, explanation=? WHERE id=? AND teacher_id=?");
            $stmt->execute([$subjectId, $classId, $term_id, $qType, $qText, $optionA, $optionB, $optionC, $optionD, $correct, $marks, $explanation, $qId, $userId]);
            $msg = 'Question updated.';
        } else {
            $stmt = $db->prepare("INSERT INTO exam_questions (teacher_id, subject_id, class_id, term_id, question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, marks, explanation) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$userId, $subjectId, $classId, $term_id, $qType, $qText, $optionA, $optionB, $optionC, $optionD, $correct, $marks, $explanation]);
            $msg = 'Question added to bank.';
        }
    } else {$msg = 'Question text, subject, and class are required.'; $msgType = 'danger';}
}

if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="question_import_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['question','option_a','option_b','option_c','option_d','correct_answer','marks','explanation']);
    fputcsv($out, ['What is 2+2?','3','4','5','6','B','1','Basic addition']);
    fputcsv($out, ['The capital of Nigeria is ____','','','','','Abuja','2','Geography question']);
    fputcsv($out, ['Water boils at 100 degrees Celsius','True','False','','','True','1','Basic science']);
    fclose($out);
    exit;
}

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM exam_questions WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$did, $userId]);
    redirect('/teacher/exams/questions.php');
}

$currentTerm = getCurrentTerm();
$termId = $currentTerm['id'] ?? 0;
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterType = sanitizeInput($_GET['qtype'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

$sql = "SELECT eq.*, sub.name as subject_name, c.name as class_name FROM exam_questions eq JOIN subjects sub ON eq.subject_id = sub.id JOIN classes c ON eq.class_id = c.id WHERE eq.teacher_id = ?";
$params = [$userId];
if ($filterSubject) { $sql .= " AND eq.subject_id = ?"; $params[] = $filterSubject; }
if ($filterClass) { $sql .= " AND eq.class_id = ?"; $params[] = $filterClass; }
if ($filterType) { $sql .= " AND eq.question_type = ?"; $params[] = $filterType; }
if ($search) { $sql .= " AND eq.question_text LIKE ?"; $params[] = "%$search%"; }
$sql .= " ORDER BY eq.created_at DESC";

$questions = $db->prepare($sql);
$questions->execute($params);
$questionList = $questions->fetchAll();

$editQuestion = null;
if ($editQ) {
    $stmt = $db->prepare("SELECT * FROM exam_questions WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$editQ, $userId]);
    $editQuestion = $stmt->fetch();
}

$subjects = $db->query("SELECT DISTINCT s.id, s.name, c.name as class_name, c.section, c.id as class_id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = $userId")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$terms = $db->query("SELECT t.*, s.session_name FROM terms t JOIN academic_sessions s ON t.session_id = s.id ORDER BY s.start_date DESC")->fetchAll();

$qTypes = ['mcq'=>'Multiple Choice','true_false'=>'True/False','fill_blank'=>'Fill in the Blank','short_answer'=>'Short Answer','essay'=>'Essay'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-database me-2"></i>Question Bank</h4>
    <div class="d-flex gap-2">
        <a href="questions.php?download_sample=1" class="btn btn-outline-info"><i class="fas fa-download me-1"></i>Sample CSV</a>
        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#bulkModal"><i class="fas fa-upload me-1"></i>Bulk Upload</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qModal"><i class="fas fa-plus me-1"></i>New Question</button>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSubject === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="qtype" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($qTypes as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filterType === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
            <div class="col-md-2">
                <a href="questions.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Question</th><th>Subject</th><th>Class</th><th>Type</th><th>Marks</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($questionList as $q): ?>
                    <tr>
                        <td><?= sanitizeInput(mb_substr($q['question_text'], 0, 100)) ?><?= mb_strlen($q['question_text']) > 100 ? '...' : '' ?></td>
                        <td><?= sanitizeInput($q['subject_name']) ?></td>
                        <td><?= sanitizeInput($q['class_name']) ?></td>
                        <td><span class="badge bg-info"><?= $qTypes[$q['question_type']] ?? $q['question_type'] ?></span></td>
                        <td><?= $q['marks'] ?></td>
                        <td class="text-end">
                            <a href="questions.php?edit=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="questions.php?delete=<?= $q['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this question?')" title="Delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($questionList)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No questions in bank. <a href="#" data-bs-toggle="modal" data-bs-target="#qModal">Add your first question</a>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Bulk Upload Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-info-circle me-1"></i>Upload a CSV file. <a href="questions.php?download_sample=1">Download sample template</a> for format reference.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <select name="bulk_subject_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>"><?= sanitizeInput($s['name'] . ' - ' . $s['class_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class *</label>
                        <select name="bulk_class_id" class="form-select" required>
                            <option value="">Select</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <select name="bulk_question_type" class="form-select">
                            <?php foreach ($qTypes as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CSV File *</label>
                        <input type="file" name="bulk_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="bulk_upload" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="qModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editQuestion ? 'Edit' : 'Add' ?> Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="question_id" value="<?= $editQuestion['id'] ?? 0 ?>">
                    <?php if ($editQuestion): ?>
                    <div class="alert alert-info">Editing question #<?= $editQuestion['id'] ?></div>
                    <?php endif; ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Subject *</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" <?= ($editQuestion['subject_id'] ?? 0) === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name'] . ' - ' . $s['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Class *</label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($editQuestion['class_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Question Type</label>
                            <select name="question_type" class="form-select" id="qTypeSelect">
                                <?php foreach ($qTypes as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($editQuestion['question_type'] ?? 'mcq') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Marks</label>
                            <input type="number" name="marks" class="form-control" min="0.5" step="0.5" value="<?= $editQuestion['marks'] ?? 1 ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <textarea name="question_text" class="form-control" rows="3" required><?= sanitizeInput($editQuestion['question_text'] ?? '') ?></textarea>
                    </div>
                    <div id="mcqOptions" class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Option A</label>
                            <input type="text" name="option_a" class="form-control" value="<?= sanitizeInput($editQuestion['option_a'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option B</label>
                            <input type="text" name="option_b" class="form-control" value="<?= sanitizeInput($editQuestion['option_b'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option C</label>
                            <input type="text" name="option_c" class="form-control" value="<?= sanitizeInput($editQuestion['option_c'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Option D</label>
                            <input type="text" name="option_d" class="form-control" value="<?= sanitizeInput($editQuestion['option_d'] ?? '') ?>">
                        </div>
                    </div>
                    <div id="tfOptions" class="mb-3" style="display:none">
                        <label class="form-label">Correct Answer</label>
                        <select name="correct_answer" class="form-select">
                            <option value="True" <?= ($editQuestion['correct_answer'] ?? '') === 'True' ? 'selected' : '' ?>>True</option>
                            <option value="False" <?= ($editQuestion['correct_answer'] ?? '') === 'False' ? 'selected' : '' ?>>False</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correct Answer</label>
                        <input type="text" name="correct_answer" id="correctAnswerInput" class="form-control" value="<?= sanitizeInput($editQuestion['correct_answer'] ?? '') ?>" placeholder="A, B, C, D or text answer">
                        <small class="text-muted">For MCQ, enter the letter (A/B/C/D). For others, enter the text answer.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Explanation (shown after exam)</label>
                        <textarea name="explanation" class="form-control" rows="2"><?= sanitizeInput($editQuestion['explanation'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_question" class="btn btn-primary">Save Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('qTypeSelect')?.addEventListener('change', function() {
    var val = this.value;
    document.getElementById('mcqOptions').style.display = (val === 'mcq') ? 'flex' : 'none';
    document.getElementById('tfOptions').style.display = (val === 'true_false') ? 'block' : 'none';
    document.getElementById('correctAnswerInput').style.display = (val === 'true_false') ? 'none' : 'block';
});
document.querySelector('[name="subject_id"]')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var cid = opt.getAttribute('data-class');
    if (cid) document.querySelector('[name="class_id"]').value = cid;
});
document.querySelector('[name="bulk_subject_id"]')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var cid = opt.getAttribute('data-class');
    if (cid) document.querySelector('[name="bulk_class_id"]').value = cid;
});
<?php if ($editQuestion): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('qTypeSelect').dispatchEvent(new Event('change'));
    new bootstrap.Modal(document.getElementById('qModal')).show();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
