<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Assignments';
$db = getDB();
$userId = (int)$_SESSION['user_id'];
$msg = '';
$msgType = 'success';
$error = '';

$subjectsStmt = $db->prepare("SELECT s.id, s.name, s.class_id, c.name as class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ? ORDER BY c.name, c.section, s.name");
$subjectsStmt->execute([$userId]);
$mySubjects = $subjectsStmt->fetchAll();

/* ------------------------------------------------------------------ *
 *  Actions
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignment'])) {
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $dueDate = sanitizeInput($_POST['due_date'] ?? '');
    $maxScore = (float)($_POST['max_score'] ?? 100);

    /* Class is always derived from the selected subject — never client-supplied. */
    $classId = 0;
    foreach ($mySubjects as $s) {
        if ((int)$s['id'] === $subjectId) { $classId = (int)$s['class_id']; break; }
    }

    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filePath = uploadFile($_FILES['file'], 'documents/assignments', ['pdf', 'doc', 'docx']);
        if ($filePath === null) {
            $error = 'File could not be uploaded. Only allowed types under ' . round(UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB are permitted.';
        }
    }

    if ($title && $subjectId && $classId && $dueDate && !$error) {
        if ($assignmentId > 0) {
            $old = $db->prepare("SELECT file_path FROM assignments WHERE id = ? AND teacher_id = ?");
            $old->execute([$assignmentId, $userId]);
            $oldFile = $old->fetchColumn() ?: null;
            if (!$filePath) $filePath = $oldFile;
            $stmt = $db->prepare("UPDATE assignments SET title = ?, description = ?, subject_id = ?, class_id = ?, file_path = ?, due_date = ?, max_score = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$title, $description, $subjectId, $classId, $filePath, $dueDate, $maxScore, $assignmentId, $userId]);
            logActivity($userId, 'update_assignment', 'assignments', $assignmentId);
            $msg = 'Assignment updated.';
        } else {
            $stmt = $db->prepare("INSERT INTO assignments (title, description, subject_id, teacher_id, class_id, file_path, due_date, max_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $subjectId, $userId, $classId, $filePath, $dueDate, $maxScore]);
            logActivity($userId, 'create_assignment', 'assignments', (int)$db->lastInsertId());
            $msg = 'Assignment created successfully.';
        }
        redirect('/teacher/assignments.php');
    } elseif (!$error) {
        $error = 'Please fill all required fields and select a subject you teach.';
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT file_path FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$id, $userId]);
    $file = $stmt->fetchColumn();
    $db->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?")->execute([$id, $userId]);
    if ($file) {
        $target = __DIR__ . '/../' . $file;
        if (is_file($target)) @unlink($target);
    }
    logActivity($userId, 'delete_assignment', 'assignments', $id);
    redirect('/teacher/assignments.php');
}

/* ---------- Grade a submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $score = (float)($_POST['score'] ?? 0);
    $feedback = sanitizeInput($_POST['feedback'] ?? '');

    /* Submission must belong to an assignment owned by this teacher. */
    $chk = $db->prepare("SELECT s.id FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.id = ? AND a.teacher_id = ?");
    $chk->execute([$submissionId, $userId]);
    if ($chk->fetchColumn()) {
        $db->prepare("UPDATE submissions SET score = ?, feedback = ?, status = 'graded', graded_by = ? WHERE id = ?")->execute([$score, $feedback, $userId, $submissionId]);
        logActivity($userId, 'grade_submission', 'submissions', $submissionId);
        $msg = 'Submission graded.';
        redirect('/teacher/assignments.php?submissions=' . $assignmentId);
    }
}

/* ------------------------------------------------------------------ *
 *  Data
 * ------------------------------------------------------------------ */
$assignmentsList = $db->prepare("SELECT a.*, sub.name as subject_name, c.name as class_name, c.section,
        (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submission_count
        FROM assignments a
        JOIN subjects sub ON a.subject_id = sub.id
        JOIN classes c ON a.class_id = c.id
        WHERE a.teacher_id = ?
        ORDER BY a.created_at DESC");
$assignmentsList->execute([$userId]);
$assignmentsList = $assignmentsList->fetchAll();

$editing = null;
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
    $stmt = $db->prepare("SELECT * FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $editing = $stmt->fetch();
    if (!$editing) redirect('/teacher/assignments.php');
}

$viewingSubmissions = (int)($_GET['submissions'] ?? 0);
$submissions = [];
if ($viewingSubmissions) {
    $chk = $db->prepare("SELECT id FROM assignments WHERE id = ? AND teacher_id = ?");
    $chk->execute([$viewingSubmissions, $userId]);
    if ($chk->fetchColumn()) {
        $subs = $db->prepare("SELECT s.*, u.first_name, u.last_name, u.avatar, st.admission_no
                              FROM submissions s
                              JOIN students st ON s.student_id = st.id
                              JOIN users u ON st.user_id = u.id
                              WHERE s.assignment_id = ?
                              ORDER BY s.submitted_at DESC");
        $subs->execute([$viewingSubmissions]);
        $submissions = $subs->fetchAll();
    } else {
        $viewingSubmissions = 0;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-tasks me-2"></i>Assignments</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignmentModal">
        <i class="fas fa-plus me-1"></i>New Assignment
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<?php if ($viewingSubmissions): ?>
<a href="assignments.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i>Back to Assignments</a>
<div class="card">
    <div class="card-header"><i class="fas fa-inbox me-2"></i>Submissions — <?= sanitizeInput($assignmentsList[array_search($viewingSubmissions, array_column($assignmentsList, 'id'))]['title'] ?? 'Assignment') ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Admission No</th>
                        <th>Submitted</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th class="text-end">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td class="fw-semibold"><?= sanitizeInput($sub['first_name'] . ' ' . $sub['last_name']) ?></td>
                        <td><?= sanitizeInput($sub['admission_no']) ?></td>
                        <td><small class="text-muted"><?= timeAgo($sub['submitted_at']) ?></small></td>
                        <td>
                            <?php if ($sub['file_path']): ?>
                            <a href="/<?= $sub['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>View</a>
                            <?php else: ?><span class="text-muted">No file</span><?php endif; ?>
                        </td>
                        <td><?= getStatusBadge($sub['status']) ?></td>
                        <td>
                            <?= $sub['score'] !== null ? $sub['score'] . '/' . ($assignmentsList[array_search($viewingSubmissions, array_column($assignmentsList, 'id'))]['max_score'] ?? '') : '—' ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary grade-btn"
                                    data-id="<?= (int)$sub['id'] ?>"
                                    data-student="<?= sanitizeInput($sub['first_name'] . ' ' . $sub['last_name']) ?>"
                                    data-score="<?= $sub['score'] !== null ? $sub['score'] : '' ?>"
                                    data-feedback="<?= sanitizeInput($sub['feedback'] ?? '') ?>"
                                    data-max="<?= (float)($assignmentsList[array_search($viewingSubmissions, array_column($assignmentsList, 'id'))]['max_score'] ?? 100) ?>">
                                <i class="fas fa-pen me-1"></i><?= $sub['status'] === 'graded' ? 'Edit Grade' : 'Grade' ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($submissions)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No submissions yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Grade modal -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Grade Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Student: <strong id="gradeStudent"></strong></p>
                    <input type="hidden" name="submission_id" id="gradeSubmissionId">
                    <input type="hidden" name="assignment_id" value="<?= $viewingSubmissions ?>">
                    <div class="mb-3">
                        <label class="form-label">Score</label>
                        <input type="number" name="score" id="gradeScore" class="form-control" min="0" step="0.5" required>
                        <div class="form-text">Out of <span id="gradeMax">100</span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Feedback</label>
                        <textarea name="feedback" id="gradeFeedback" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="grade_submission" value="1">
                    <button type="submit" class="btn btn-primary">Save Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php else: ?>

<?php foreach ($assignmentsList as $a): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <div>
                <h5 class="fw-bold mb-1"><?= sanitizeInput($a['title']) ?></h5>
                <p class="text-muted small mb-2">
                    <?= sanitizeInput($a['subject_name']) ?> | <?= sanitizeInput(className($a['class_name'], $a['section'])) ?>
                    | Due: <?= formatDate($a['due_date']) ?> | Max score: <?= $a['max_score'] ?>
                    | Created: <?= timeAgo($a['created_at']) ?>
                </p>
            </div>
            <div class="text-end">
                <a href="assignments.php?submissions=<?= $a['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-inbox me-1"></i>Submissions (<?= (int)$a['submission_count'] ?>)</a>
            </div>
        </div>
        <?php if ($a['description']): ?>
        <p><?= nl2br(sanitizeInput($a['description'])) ?></p>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <?php if ($a['file_path']): ?>
            <a href="/<?= $a['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-download me-1"></i>Download File</a>
            <?php endif; ?>
            <a href="assignments.php?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit me-1"></i>Edit</a>
            <a href="assignments.php?delete=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this assignment? Submissions will also be removed.');"><i class="fas fa-trash me-1"></i>Delete</a>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($assignmentsList)): ?>
<div class="alert alert-info">No assignments created yet.</div>
<?php endif; ?>

<?php if ($editing): ?>
<div class="alert alert-info"><i class="fas fa-edit me-1"></i>Editing: <strong><?= sanitizeInput($editing['title']) ?></strong> — update the fields below and save.</div>
<?php endif; ?>

<div class="modal fade show d-block" id="assignmentModal" tabindex="-1" <?= $editing ? '' : 'data-noshow' ?> style="<?= $editing ? 'display:block;background:rgba(0,0,0,.4);' : 'display:none;' ?>">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $editing ? 'Edit Assignment' : 'Create Assignment' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="window.location='assignments.php'"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" value="<?= (int)($editing['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?= sanitizeInput($editing['title'] ?? '') ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Subject *</label>
                            <select name="subject_id" id="asgSubject" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($mySubjects as $s): ?>
                                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" <?= (int)($editing['subject_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= sanitizeInput($s['name'] . ' - ' . className($s['class_name'], $s['section'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date *</label>
                            <input type="datetime-local" name="due_date" class="form-control" required value="<?= $editing ? date('Y-m-d\TH:i', strtotime($editing['due_date'])) : '' ?>">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Max Score</label>
                            <input type="number" name="max_score" class="form-control" min="1" step="0.5" value="<?= (float)($editing['max_score'] ?? 100) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <input type="text" class="form-control" id="asgClass" readonly placeholder="Auto-set from subject">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= sanitizeInput($editing['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment (optional)</label>
                        <input type="file" name="file" class="form-control">
                        <?php if (!empty($editing['file_path'])): ?>
                        <div class="form-text">Current file: <a href="/<?= $editing['file_path'] ?>" target="_blank"><?= basename($editing['file_path']) ?></a></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="window.location='assignments.php'">Cancel</button>
                    <button type="submit" name="save_assignment" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Create Assignment' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('asgSubject')?.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    var out = document.getElementById('asgClass');
    if (opt.getAttribute('data-class')) {
        var classOpts = <?= json_encode(array_map(function ($s) { return ['id' => (int)$s['class_id'], 'name' => className($s['class_name'], $s['section'])]; }, $mySubjects)) ?>;
        for (var i = 0; i < classOpts.length; i++) {
            if (classOpts[i].id === parseInt(opt.getAttribute('data-class'))) {
                out.value = classOpts[i].name;
                break;
            }
        }
    }
});
document.getElementById('asgSubject')?.dispatchEvent(new Event('change'));

document.querySelectorAll('.grade-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('gradeStudent').textContent = btn.getAttribute('data-student');
        document.getElementById('gradeSubmissionId').value = btn.getAttribute('data-id');
        document.getElementById('gradeScore').value = btn.getAttribute('data-score');
        document.getElementById('gradeScore').max = btn.getAttribute('data-max');
        document.getElementById('gradeMax').textContent = btn.getAttribute('data-max');
        document.getElementById('gradeFeedback').value = btn.getAttribute('data-feedback');
        new bootstrap.Modal(document.getElementById('gradeModal')).show();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
