<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Create Examination';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$editId = (int)($_GET['id'] ?? 0);
$exam = null;
$currentTerm = getCurrentTerm();
$termId = $currentTerm['id'] ?? 0;

if ($editId) {
    $pageTitle = 'Edit Examination';
    $stmt = $db->prepare("SELECT * FROM teacher_exams WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$editId, $userId]);
    $exam = $stmt->fetch();
    if (!$exam) redirect('/teacher/exams/index.php');
}

$subjects = $db->query("SELECT DISTINCT s.id, s.name, c.name as class_name, c.section, c.id as class_id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = $userId")->fetchAll();
$terms = $db->query("SELECT t.*, s.session_name FROM terms t JOIN academic_sessions s ON t.session_id = s.id ORDER BY s.start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $term_id = (int)($_POST['term_id'] ?? $termId);
    $examType = sanitizeInput($_POST['exam_type'] ?? 'Test');
    $duration = (int)($_POST['duration_minutes'] ?? 60);
    $totalMarks = (float)($_POST['total_marks'] ?? 0);
    $examDate = sanitizeInput($_POST['exam_date'] ?? '');
    $startTime = sanitizeInput($_POST['start_time'] ?? '');
    $endTime = sanitizeInput($_POST['end_time'] ?? '');
    $instructions = sanitizeInput($_POST['instructions'] ?? '');
    $shuffle = isset($_POST['shuffle_questions']) ? 1 : 0;
    $showResult = isset($_POST['show_result']) ? 1 : 0;
    $status = sanitizeInput($_POST['status'] ?? 'draft');
    $isPublished = $status === 'published' ? 1 : 0;

    if ($title && $subjectId && $classId) {
        if ($editId) {
            $stmt = $db->prepare("UPDATE teacher_exams SET title=?, subject_id=?, class_id=?, term_id=?, exam_type=?, duration_minutes=?, total_marks=?, exam_date=?, start_time=?, end_time=?, instructions=?, shuffle_questions=?, show_result=?, status=?, is_published=? WHERE id=? AND teacher_id=?");
            $stmt->execute([$title, $subjectId, $classId, $term_id, $examType, $duration, $totalMarks, $examDate, $startTime, $endTime, $instructions, $shuffle, $showResult, $status, $isPublished, $editId, $userId]);
            $msg = 'Examination updated.';
        } else {
            $stmt = $db->prepare("INSERT INTO teacher_exams (teacher_id, subject_id, class_id, term_id, title, exam_type, total_marks, duration_minutes, exam_date, start_time, end_time, instructions, shuffle_questions, show_result, status, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$userId, $subjectId, $classId, $term_id, $title, $examType, $totalMarks, $duration, $examDate, $startTime, $endTime, $instructions, $shuffle, $showResult, $status, $isPublished]);
            $editId = (int)$db->lastInsertId();
            $msg = 'Examination created.';
        }
        $stmt = $db->prepare("SELECT * FROM teacher_exams WHERE id = ?");
        $stmt->execute([$editId]);
        $exam = $stmt->fetch();
    } else {
        $msg = 'Title, Subject, and Class are required.';
        $msgType = 'danger';
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-<?= $editId ? 'edit' : 'plus' ?> me-2"></i><?= $editId ? 'Edit' : 'Create' ?> Examination</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<?php if ($editId && $exam): ?>
<div class="alert alert-info d-flex gap-3 align-items-center">
    <span><i class="fas fa-info-circle me-1"></i>Exam ID: #<?= $exam['id'] ?></span>
    <a href="set-questions.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-list me-1"></i>Manage Questions</a>
    <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-chart-bar me-1"></i>View Results</a>
</div>
<?php endif; ?>

<form method="POST">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-cog me-2"></i>Examination Details</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Examination Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?= sanitizeInput($exam['title'] ?? '') ?>" placeholder="e.g. First Term Mathematics Examination">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Subject *</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" <?= ($exam['subject_id'] ?? 0) === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name'] . ' - ' . $s['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Class *</label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($exam['class_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Exam Type</label>
                            <select name="exam_type" class="form-select">
                                <?php foreach (['CA','Test','Mid-Term','Examination','Mock Exam','CBT'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($exam['exam_type'] ?? 'Test') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Term</label>
                            <select name="term_id" class="form-select">
                                <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($exam['term_id'] ?? $termId) === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name'] . ' - ' . $t['session_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control" min="1" max="360" value="<?= $exam['duration_minutes'] ?? 60 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Marks</label>
                            <input type="number" name="total_marks" class="form-control" min="0" step="0.5" value="<?= $exam['total_marks'] ?? 100 ?>">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Exam Date</label>
                            <input type="date" name="exam_date" class="form-control" value="<?= $exam['exam_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="<?= $exam['start_time'] ?? '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="<?= $exam['end_time'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Instructions to Students</label>
                        <textarea name="instructions" class="form-control" rows="4" placeholder="Read each question carefully. Answer all questions. Time allotted is..."><?= sanitizeInput($exam['instructions'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-sliders-h me-2"></i>Settings & Publish</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?= ($exam['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($exam['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publish Immediately</option>
                        </select>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="shuffle_questions" class="form-check-input" id="chkShuffle" value="1" <?= ($exam['shuffle_questions'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkShuffle">Shuffle question order</label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="show_result" class="form-check-input" id="chkResult" value="1" <?= ($exam['show_result'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkResult">Show result after submission</label>
                    </div>
                    <button type="submit" name="save_exam" class="btn btn-primary w-100 mb-2"><i class="fas fa-save me-1"></i>Save Examination</button>
                    <?php if ($editId): ?>
                    <a href="set-questions.php?exam_id=<?= $editId ?>" class="btn btn-outline-primary w-100"><i class="fas fa-list me-1"></i>Add Questions</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.querySelector('[name="subject_id"]')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var classId = opt.getAttribute('data-class');
    if (classId) document.querySelector('[name="class_id"]').value = classId;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
