<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Manage CBT Exams';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

// Toggle publish
if ($action === 'publish' && $id) {
    $db->prepare("UPDATE cbt_exams SET is_published = 1 WHERE id = ?")->execute([$id]);
    logActivity($_SESSION['user_id'], 'publish_cbt_exam', 'cbt_exams', $id);
    $message = 'Exam published successfully.';
    $action = 'list';
}
if ($action === 'unpublish' && $id) {
    $db->prepare("UPDATE cbt_exams SET is_published = 0 WHERE id = ?")->execute([$id]);
    logActivity($_SESSION['user_id'], 'unpublish_cbt_exam', 'cbt_exams', $id);
    $message = 'Exam unpublished successfully.';
    $action = 'list';
}
if ($action === 'delete' && $id) {
    $db->prepare("DELETE FROM cbt_exams WHERE id = ?")->execute([$id]);
    logActivity($_SESSION['user_id'], 'delete_cbt_exam', 'cbt_exams', $id);
    $message = 'Exam deleted successfully.';
    $action = 'list';
}

// Handle save (create/edit)
if ($action === 'save') {
    $title = trim($_POST['title'] ?? '');
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $duration = (int)($_POST['duration_minutes'] ?? 30);
    $total_q = (int)($_POST['total_questions'] ?? 30);
    $pass_score = (float)($_POST['pass_score'] ?? 50);
    $instructions = trim($_POST['instructions'] ?? '');
    $question_ids = $_POST['question_ids'] ?? [];

    if (!$title || !$subject_id || !$duration || !$total_q) {
        $error = 'Please fill all required fields.';
    } else {
        $edit_id = (int)($_POST['id'] ?? 0);
        if ($edit_id) {
            $stmt = $db->prepare("UPDATE cbt_exams SET title=?, subject_id=?, duration_minutes=?, total_questions=?, pass_score=?, instructions=? WHERE id=?");
            $stmt->execute([$title, $subject_id, $duration, $total_q, $pass_score, $instructions, $edit_id]);
            $db->prepare("DELETE FROM cbt_exam_questions WHERE exam_id = ?")->execute([$edit_id]);
            logActivity($_SESSION['user_id'], 'update_cbt_exam', 'cbt_exams', $edit_id);
            $examId = $edit_id;
            $message = 'Exam updated successfully.';
        } else {
            $stmt = $db->prepare("INSERT INTO cbt_exams (title, subject_id, duration_minutes, total_questions, pass_score, instructions, is_published, created_by) VALUES (?,?,?,?,?,?,0,?)");
            $stmt->execute([$title, $subject_id, $duration, $total_q, $pass_score, $instructions, $_SESSION['user_id']]);
            $examId = (int)$db->lastInsertId();
            logActivity($_SESSION['user_id'], 'create_cbt_exam', 'cbt_exams', $examId);
            $message = 'Exam created successfully.';
        }

        // Add selected questions
        $eqStmt = $db->prepare("INSERT INTO cbt_exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");
        $order = 1;
        foreach ($question_ids as $qid) {
            $eqStmt->execute([$examId, (int)$qid, $order]);
            $order++;
        }
        $db->prepare("UPDATE cbt_exams SET total_questions = ? WHERE id = ?")->execute([$order - 1, $examId]);
        $action = 'list';
    }
}

// Auto-generate exam from random questions
if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $count = (int)($_POST['question_count'] ?? 30);
    $title = trim($_POST['title'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 30);
    $pass_score = (float)($_POST['pass_score'] ?? 50);

    if (!$subject_id || !$title) {
        $error = 'Please fill required fields.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM cbt_questions WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $totalAvail = (int)$stmt->fetchColumn();
        $count = min($count, $totalAvail);

        $stmt = $db->prepare("INSERT INTO cbt_exams (title, subject_id, duration_minutes, total_questions, pass_score, instructions, is_published, created_by) VALUES (?,?,?,?,?,?,0,?)");
        $stmt->execute([$title, $subject_id, $duration, $count, $pass_score, "Auto-generated exam. Answer all questions.", $_SESSION['user_id']]);
        $examId = (int)$db->lastInsertId();

        $questions = $db->prepare("SELECT id FROM cbt_questions WHERE subject_id = ? ORDER BY RAND() LIMIT ?");
        $questions->execute([$subject_id, $count]);
        $eqStmt = $db->prepare("INSERT INTO cbt_exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");
        $order = 1;
        foreach ($questions->fetchAll() as $qr) {
            $eqStmt->execute([$examId, (int)$qr['id'], $order]);
            $order++;
        }
        logActivity($_SESSION['user_id'], 'generate_cbt_exam', 'cbt_exams', $examId);
        $message = "Exam '$title' generated with $count questions.";
        $action = 'list';
    }
}

$subjects = $db->query("SELECT id, name, code FROM cbt_subjects ORDER BY name")->fetchAll();

// Get exams list
$exams = $db->query("
    SELECT e.*, s.name as subject_name, s.code as subject_code,
           (SELECT COUNT(*) FROM cbt_attempts WHERE exam_id = e.id) as attempt_count,
           (SELECT COUNT(*) FROM cbt_attempts WHERE exam_id = e.id AND status = 'completed') as completed_count
    FROM cbt_exams e
    JOIN cbt_subjects s ON e.subject_id = s.id
    ORDER BY e.created_at DESC
")->fetchAll();

$editExam = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM cbt_exams WHERE id = ?");
    $stmt->execute([$id]);
    $editExam = $stmt->fetch();
}

// Get questions for a subject (for exam builder)
$buildSubjectId = (int)($_GET['build_subject'] ?? ($editExam['subject_id'] ?? 0));
$availQuestions = [];
if ($buildSubjectId || ($action === 'edit' && $editExam)) {
    $sid = $buildSubjectId ?: $editExam['subject_id'];
    $stmt = $db->prepare("SELECT q.* FROM cbt_questions q WHERE q.subject_id = ? ORDER BY q.id");
    $stmt->execute([$sid]);
    $availQuestions = $stmt->fetchAll();
}

// Get selected question IDs for edit
$selectedIds = [];
if ($editExam) {
    $stmt = $db->prepare("SELECT question_id FROM cbt_exam_questions WHERE exam_id = ? ORDER BY question_order");
    $stmt->execute([$id]);
    $selectedIds = array_map(function($r) { return (int)$r['question_id']; }, $stmt->fetchAll());
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">CBT Exams</h4>
        <p class="text-muted small mb-0">Create and manage computer-based tests</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/cbt/exams.php" class="btn btn-outline-primary me-2 <?= $action === 'list' ? 'd-none' : '' ?>">
            <i class="fas fa-list me-1"></i>All Exams
        </a>
        <a href="<?= BASE_URL ?>/admin/cbt/exams.php?action=add" class="btn btn-primary me-2 <?= $action !== 'list' ? 'd-none' : '' ?>">
            <i class="fas fa-plus me-1"></i>Create Exam
        </a>
        <a href="<?= BASE_URL ?>/admin/cbt/exams.php?action=generate" class="btn btn-gold <?= $action !== 'list' ? 'd-none' : '' ?>">
            <i class="fas fa-magic me-1"></i>Auto-Generate
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $editExam)): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-<?= $editExam ? 'edit' : 'plus' ?> me-2"></i><?= $editExam ? 'Edit Exam' : 'Create New Exam' ?></div>
    <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>/admin/cbt/exams.php?action=save">
            <?php if ($editExam): ?>
            <input type="hidden" name="id" value="<?= $editExam['id'] ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Exam Title</label>
                    <input type="text" name="title" class="form-control" value="<?= sanitizeInput($editExam['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" required onchange="window.location='?action=<?= $action ?>&id=<?= $id ?>&build_subject='+this.value">
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($buildSubjectId ?: ($editExam['subject_id'] ?? 0)) == $s['id'] ? 'selected' : '' ?>>
                            <?= sanitizeInput($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="form-control" value="<?= $editExam['duration_minutes'] ?? 30 ?>" min="1" max="180" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pass Score (%)</label>
                    <input type="number" name="pass_score" class="form-control" value="<?= $editExam['pass_score'] ?? 50 ?>" min="0" max="100" step="0.5">
                </div>
                <div class="col-12">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions" class="form-control" rows="3"><?= sanitizeInput($editExam['instructions'] ?? '') ?></textarea>
                </div>
            </div>

            <?php if ($availQuestions): ?>
            <div class="mt-4">
                <label class="form-label fw-bold">Select Questions (<?= count($availQuestions) ?> available)</label>
                <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($availQuestions as $q): ?>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="question_ids[]" value="<?= $q['id'] ?>" class="form-check-input question-checkbox"
                               id="q_<?= $q['id'] ?>" <?= in_array((int)$q['id'], $selectedIds) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="q_<?= $q['id'] ?>">
                            <strong><?= sanitizeInput($q['question_text']) ?></strong>
                            <br><small class="text-muted">A: <?= sanitizeInput($q['option_a']) ?> | B: <?= sanitizeInput($q['option_b']) ?> | C: <?= sanitizeInput($q['option_c']) ?> | D: <?= sanitizeInput($q['option_d']) ?></small>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mt-2">Selected: <span id="selectedCount"><?= count($selectedIds) ?></span> questions</p>
            </div>
            <?php elseif ($buildSubjectId): ?>
            <div class="alert alert-warning mt-3">No questions available for this subject. <a href="<?= BASE_URL ?>/admin/cbt/questions.php?action=add&subject_id=<?= $buildSubjectId ?>">Add questions first.</a></div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Exam</button>
                <a href="<?= BASE_URL ?>/admin/cbt/exams.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.question-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        document.getElementById('selectedCount').textContent = document.querySelectorAll('.question-checkbox:checked').length;
    });
});
</script>

<?php elseif ($action === 'generate'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-magic me-2"></i>Auto-Generate Exam</div>
    <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>/admin/cbt/exams.php?action=generate">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Exam Title</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Number of Questions</label>
                    <input type="number" name="question_count" class="form-control" value="30" min="5" max="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="form-control" value="30" min="1" max="180">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pass Score (%)</label>
                    <input type="number" name="pass_score" class="form-control" value="50" min="0" max="100">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-gold"><i class="fas fa-magic me-1"></i>Generate Exam</button>
                <a href="<?= BASE_URL ?>/admin/cbt/exams.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header"><i class="fas fa-list me-2"></i>All Exams</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Duration</th>
                    <th>Questions</th>
                    <th>Pass (%)</th>
                    <th>Attempts</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $e): ?>
                <tr>
                    <td><strong><?= sanitizeInput($e['title']) ?></strong></td>
                    <td><span class="badge bg-primary"><?= sanitizeInput($e['subject_code']) ?></span></td>
                    <td><?= $e['duration_minutes'] ?> min</td>
                    <td><?= $e['total_questions'] ?></td>
                    <td><?= $e['pass_score'] ?>%</td>
                    <td><?= $e['completed_count'] ?>/<?= $e['attempt_count'] ?></td>
                    <td>
                        <?php if ($e['is_published']): ?>
                        <span class="badge bg-success">Published</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/cbt/exams.php?action=edit&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                        <?php if ($e['is_published']): ?>
                        <a href="<?= BASE_URL ?>/admin/cbt/exams.php?action=unpublish&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning" title="Unpublish"><i class="fas fa-eye-slash"></i></a>
                        <?php else: ?>
                        <a href="<?= BASE_URL ?>/admin/cbt/exams.php?action=publish&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-success" title="Publish"><i class="fas fa-eye"></i></a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/admin/cbt/exams.php?action=delete&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this exam?\nThis will also delete all student attempts for this exam.')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($exams)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No exams yet. Create your first exam!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
