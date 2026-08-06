<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Lesson Notes';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$currentTerm = getCurrentTerm();

/* ------------------------------------------------------------------ *
 *  Teacher scoping data
 * ------------------------------------------------------------------ */
$subjectsStmt = $db->prepare("SELECT s.id, s.name, c.name AS class_name, c.section, c.id AS class_id
                              FROM subjects s JOIN classes c ON s.class_id = c.id
                              WHERE s.teacher_id = ? ORDER BY c.name, c.section, s.name");
$subjectsStmt->execute([$userId]);
$mySubjects = $subjectsStmt->fetchAll();

$plansStmt = $db->prepare("SELECT lp.id, lp.topic, c.name AS class_name, c.section
                           FROM lesson_plans lp JOIN classes c ON lp.class_id = c.id
                           WHERE lp.teacher_id = ? ORDER BY lp.updated_at DESC");
$plansStmt->execute([$userId]);
$myPlans = $plansStmt->fetchAll();

$sessions = $db->query("SELECT id, session_name, is_current FROM academic_sessions ORDER BY id DESC")->fetchAll();
$terms = $db->query("SELECT t.id, t.term_name, t.session_id, t.is_current, s.session_name
                     FROM terms t JOIN academic_sessions s ON t.session_id = s.id
                     ORDER BY s.id DESC, t.id")->fetchAll();

/* ------------------------------------------------------------------ *
 *  Actions
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $noteId = (int)($_POST['note_id'] ?? 0);
    $topic = sanitizeInput($_POST['topic'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $week = (int)($_POST['week'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);
    $lessonPlanId = (int)($_POST['lesson_plan_id'] ?? 0);
    $dateTaught = sanitizeInput($_POST['date_taught'] ?? '');
    $summary = sanitizeInput($_POST['summary'] ?? '');
    $content = isset($_POST['content']) ? sanitizeRichText($_POST['content']) : '';
    $status = in_array($_POST['status'] ?? '', ['draft', 'published'], true) ? $_POST['status'] : 'draft';
    $removeFile = isset($_POST['remove_file']) ? 1 : 0;

    if ($topic && $subjectId && $classId) {
        $filePath = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploaded = uploadFile($_FILES['file'], 'documents/lesson-notes', ['pdf', 'doc', 'docx']);
            if ($uploaded === null) {
                $msg = 'File could not be uploaded. Only PDF, DOC or DOCX under ' . round(UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB are allowed.';
                $msgType = 'danger';
            } else {
                $filePath = $uploaded;
            }
        }

        if ($msgType === 'success') {
            if ($noteId > 0) {
                // Load existing row (scoped to teacher) to know the old file.
                $old = $db->prepare("SELECT file_path FROM lesson_notes WHERE id = ? AND teacher_id = ?");
                $old->execute([$noteId, $userId]);
                $oldFile = $old->fetchColumn() ?: null;
                if ($removeFile && $oldFile) {
                    $target = __DIR__ . '/../' . $oldFile;
                    if (is_file($target)) @unlink($target);
                    $oldFile = null;
                }
                $stmt = $db->prepare("UPDATE lesson_notes SET topic = ?, subject_id = ?, class_id = ?, academic_session_id = ?, term_id = ?, lesson_plan_id = ?, week = ?, date_taught = ?, summary = ?, content = ?, status = ?, file_path = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$topic, $subjectId, $classId, $sessionId, $termId, $lessonPlanId, $week, $dateTaught, $summary, $content, $status, $filePath !== null ? $filePath : $oldFile, $noteId, $userId]);
                logActivity($userId, 'update_lesson_note', 'lesson_notes', $noteId);
                $msg = 'Lesson note updated.';
            } else {
                $stmt = $db->prepare("INSERT INTO lesson_notes (teacher_id, subject_id, class_id, academic_session_id, term_id, lesson_plan_id, topic, content, week, date_taught, summary, file_path, status, is_ai_generated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
                $stmt->execute([$userId, $subjectId, $classId, $sessionId, $termId, $lessonPlanId, $topic, $content, $week, $dateTaught, $summary, $filePath, $status]);
                $noteId = (int)$db->lastInsertId();
                logActivity($userId, 'create_lesson_note', 'lesson_notes', $noteId);
                $msg = 'Lesson note ' . ($status === 'published' ? 'created and published.' : 'saved as draft.');
            }
        }
    } else {
        $msg = 'Please fill all required fields.';
        $msgType = 'danger';
    }
    redirect('/teacher/lesson-notes.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("SELECT file_path FROM lesson_notes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$id, $userId]);
    $file = $stmt->fetchColumn();
    $db->prepare("DELETE FROM lesson_notes WHERE id = ? AND teacher_id = ?")->execute([$id, $userId]);
    if ($file) {
        $target = __DIR__ . '/../' . $file;
        if (is_file($target)) @unlink($target);
    }
    logActivity($userId, 'delete_lesson_note', 'lesson_notes', $id);
    redirect('/teacher/lesson-notes.php');
}

$statusActions = ['publish' => 'published', 'unpublish' => 'draft', 'archive' => 'archived', 'restore' => 'draft'];
if (isset($_GET['set_status']) && isset($statusActions[$_GET['set_status']])) {
    $id = (int)($_GET['id'] ?? 0);
    $newStatus = $statusActions[$_GET['set_status']];
    $db->prepare("UPDATE lesson_notes SET status = ? WHERE id = ? AND teacher_id = ?")->execute([$newStatus, $id, $userId]);
    logActivity($userId, 'lesson_note_status_' . $newStatus, 'lesson_notes', $id);
    redirect('/teacher/lesson-notes.php');
}

/* ------------------------------------------------------------------ *
 *  Edit form view
 * ------------------------------------------------------------------ */
$editing = null;
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
    $stmt = $db->prepare("SELECT * FROM lesson_notes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([(int)$_GET['edit'], $userId]);
    $editing = $stmt->fetch();
    if (!$editing) {
        redirect('/teacher/lesson-notes.php');
    }
}

$showForm = isset($_GET['new']) || $editing !== null;

/* ------------------------------------------------------------------ *
 *  List view data
 * ------------------------------------------------------------------ */
$filterStatus = sanitizeInput($_GET['status'] ?? '');
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$search = sanitizeInput($_GET['search'] ?? '');

$sql = "SELECT ln.*, sub.name AS subject_name, c.name AS class_name, c.section,
               lp.topic AS plan_topic, sess.session_name, t.term_name
        FROM lesson_notes ln
        JOIN subjects sub ON ln.subject_id = sub.id
        JOIN classes c ON ln.class_id = c.id
        LEFT JOIN lesson_plans lp ON ln.lesson_plan_id = lp.id
        LEFT JOIN academic_sessions sess ON ln.academic_session_id = sess.id
        LEFT JOIN terms t ON ln.term_id = t.id
        WHERE ln.teacher_id = ?";
$params = [$userId];
if (in_array($filterStatus, ['draft', 'published', 'archived'], true)) { $sql .= " AND ln.status = ?"; $params[] = $filterStatus; }
if ($filterSubject) { $sql .= " AND ln.subject_id = ?"; $params[] = $filterSubject; }
if ($search) { $sql .= " AND (ln.topic LIKE ? OR ln.summary LIKE ?)"; $like = "%$search%"; $params[] = $like; $params[] = $like; }
$sql .= " ORDER BY ln.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

$counts = [
    'total' => count($notes),
    'draft' => 0,
    'published' => 0,
    'archived' => 0,
];
foreach ($notes as $n) { $counts[$n['status']] = ($counts[$n['status']] ?? 0) + 1; }

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($showForm): ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-sticky-note me-2"></i><?= $editing ? 'Edit Lesson Note' : 'New Lesson Note' ?></h4>
        <p class="text-muted small mb-0">Draft or publish a note for your students. Published notes are visible to students in the selected class.</p>
    </div>
    <a href="lesson-notes.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="row g-4" id="noteForm">
    <input type="hidden" name="note_id" value="<?= (int)($editing['id'] ?? 0) ?>">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-heading me-2"></i>Note Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Topic *</label>
                    <input type="text" name="topic" class="form-control" required value="<?= sanitizeInput($editing['topic'] ?? '') ?>" placeholder="e.g. Fractions, Photosynthesis, The Solar System">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Subject *</label>
                        <select name="subject_id" class="form-select" id="noteSubject" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($mySubjects as $s): ?>
                            <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>" <?= (int)($editing['subject_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput($s['name'] . ' - ' . className($s['class_name'], $s['section'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Class *</label>
                        <select name="class_id" id="noteClass" class="form-select" required>
                            <option value="">Auto-set from subject</option>
                            <?php $seen = []; foreach ($mySubjects as $s): if (isset($seen[$s['class_id']])) continue; $seen[$s['class_id']] = 1; ?>
                            <option value="<?= $s['class_id'] ?>" <?= (int)($editing['class_id'] ?? 0) === (int)$s['class_id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput(className($s['class_name'], $s['section'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Week</label>
                        <input type="number" name="week" class="form-control" min="1" max="15" value="<?= (int)($editing['week'] ?? 1) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date Taught</label>
                        <input type="date" name="date_taught" class="form-control" value="<?= sanitizeInput($editing['date_taught'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Term</label>
                        <select name="term_id" class="form-select" id="noteTerm">
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $t): ?>
                            <option value="<?= $t['id'] ?>" data-session="<?= $t['session_id'] ?>" <?= (int)($editing['term_id'] ?? ($currentTerm['id'] ?? 0)) === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput($t['session_name'] . ' - ' . $t['term_name']) ?><?= $t['is_current'] ? ' (current)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Academic Session</label>
                        <select name="academic_session_id" class="form-select" id="noteSession">
                            <option value="">Auto-set from term</option>
                            <?php foreach ($sessions as $sess): ?>
                            <option value="<?= $sess['id'] ?>" <?= (int)($editing['academic_session_id'] ?? ($currentTerm['session_id'] ?? 0)) === (int)$sess['id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput($sess['session_name']) ?><?= $sess['is_current'] ? ' (current)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Linked Lesson Plan</label>
                        <select name="lesson_plan_id" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($myPlans as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (int)($editing['lesson_plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= sanitizeInput($p['topic'] . ' (' . className($p['class_name'], $p['section']) . ')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Summary</label>
                    <textarea name="summary" class="form-control" rows="2" placeholder="A short summary shown to students in the list view."><?= sanitizeInput($editing['summary'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-edit me-2"></i>Lesson Content</span>
                <span class="text-muted small">Use the toolbar to format</span>
            </div>
            <div class="card-body">
                <div class="btn-toolbar gap-1 mb-2" role="toolbar" id="noteToolbar">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="bold" title="Bold"><b>B</b></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="italic" title="Italic"><i>I</i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="underline" title="Underline"><u>U</u></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                    <div class="vr"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="insertUnorderedList" title="Bullet list"><i class="fas fa-list-ul"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="insertOrderedList" title="Numbered list"><i class="fas fa-list-ol"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="h3" title="Heading"><i class="fas fa-heading"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="blockquote" title="Quote"><i class="fas fa-quote-right"></i></button>
                    <div class="vr"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cmd="removeFormat" title="Clear formatting"><i class="fas fa-eraser"></i></button>
                </div>
                <div id="noteEditor" class="form-control editor-box" contenteditable="true" style="min-height: 300px;"><?= $editing ? $editing['content'] : '' ?></div>
                <textarea name="content" id="noteContent" class="d-none"></textarea>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-cog me-2"></i>Publish &amp; Save</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?= ($editing['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (only you can see)</option>
                        <option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (visible to students)</option>
                    </select>
                    <div class="form-text">Published notes are immediately available to students in the selected class for the current term.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Attachment (PDF, DOC, DOCX)</label>
                    <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx">
                    <?php if (!empty($editing['file_path'])): ?>
                    <div class="form-text mt-2">
                        Current file: <a href="/<?= $editing['file_path'] ?>" target="_blank" class="fw-semibold"><i class="fas fa-paperclip me-1"></i><?= basename($editing['file_path']) ?></a>
                    </div>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="remove_file" value="1" id="removeFile">
                        <label class="form-check-label small" for="removeFile">Remove this file</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer d-flex flex-column gap-2">
                <button type="button" class="btn btn-outline-secondary" id="previewBtn"><i class="fas fa-eye me-1"></i>Preview</button>
                <button type="submit" name="save_note" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
            </div>
        </div>
    </div>
</form>

<!-- Preview modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewBody"></div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('#noteToolbar [data-cmd]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var cmd = btn.getAttribute('data-cmd');
        var editor = document.getElementById('noteEditor');
        editor.focus();
        if (cmd === 'h3' || cmd === 'blockquote' || cmd === 'p') {
            document.execCommand('formatBlock', false, cmd === 'h3' ? 'h3' : cmd);
        } else {
            document.execCommand(cmd, false, null);
        }
    });
});

document.getElementById('noteSubject').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var classSel = document.getElementById('noteClass');
    if (opt.getAttribute('data-class')) {
        for (var i = 0; i < classSel.options.length; i++) {
            if (classSel.options[i].value === opt.getAttribute('data-class')) {
                classSel.value = opt.getAttribute('data-class');
                break;
            }
        }
    }
});

document.getElementById('noteTerm').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var sess = document.getElementById('noteSession');
    if (opt.getAttribute('data-session')) {
        for (var i = 0; i < sess.options.length; i++) {
            if (sess.options[i].value === opt.getAttribute('data-session')) {
                sess.value = opt.getAttribute('data-session');
                break;
            }
        }
    }
});

document.getElementById('previewBtn').addEventListener('click', function() {
    var body = document.getElementById('previewBody');
    var title = document.querySelector('[name="topic"]').value;
    body.innerHTML = '<h4>' + title.replace(/[<>&]/g, '') + '</h4><hr>' + document.getElementById('noteEditor').innerHTML;
    var m = new bootstrap.Modal(document.getElementById('previewModal'));
    m.show();
});

document.getElementById('noteForm').addEventListener('submit', function() {
    document.getElementById('noteContent').value = document.getElementById('noteEditor').innerHTML;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; exit; ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-sticky-note me-2"></i>Lesson Notes</h4>
        <p class="text-muted small mb-0">Write, upload and publish lesson notes for your classes.</p>
    </div>
    <a href="lesson-notes.php?new=1" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Lesson Note</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card stat-primary"><i class="fas fa-sticky-note stat-icon"></i><div class="stat-value"><?= $counts['total'] ?></div><div class="stat-label">Total Notes</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-warning"><i class="fas fa-pen stat-icon"></i><div class="stat-value"><?= $counts['draft'] ?></div><div class="stat-label">Drafts</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-success"><i class="fas fa-check-circle stat-icon"></i><div class="stat-value"><?= $counts['published'] ?></div><div class="stat-label">Published</div></div></div>
    <div class="col-md-3"><div class="stat-card" style="background: linear-gradient(135deg,#64748b,#475569);"><i class="fas fa-archive stat-icon"></i><div class="stat-value"><?= $counts['archived'] ?></div><div class="stat-label">Archived</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Topic or summary..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($mySubjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSubject === (int)$s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name'] . ' - ' . className($s['class_name'], $s['section'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Week</th>
                        <th>Term / Session</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $n): ?>
                    <tr>
                        <td class="fw-semibold"><?= sanitizeInput($n['topic']) ?>
                            <?php if (!empty($n['plan_topic'])): ?><span class="badge bg-light text-dark ms-1" title="Linked lesson plan"><i class="fas fa-link"></i></span><?php endif; ?>
                            <?php if ($n['is_ai_generated']): ?><span class="badge bg-info ms-1">AI</span><?php endif; ?>
                        </td>
                        <td><?= sanitizeInput($n['subject_name']) ?></td>
                        <td><?= sanitizeInput(className($n['class_name'], $n['section'])) ?></td>
                        <td>Week <?= (int)$n['week'] ?: '-' ?></td>
                        <td><small><?= sanitizeInput($n['term_name'] ?? '') ?><?= $n['term_name'] && $n['session_name'] ? ' / ' : '' ?><?= sanitizeInput($n['session_name'] ?? '') ?></small></td>
                        <td>
                            <?php $b = ['draft' => 'warning', 'published' => 'success', 'archived' => 'secondary']; ?>
                            <span class="badge bg-<?= $b[$n['status']] ?? 'secondary' ?>"><?= ucfirst($n['status']) ?></span>
                        </td>
                        <td><small class="text-muted"><?= timeAgo($n['created_at']) ?></small></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item view-note" data-id="<?= (int)$n['id'] ?>" data-topic="<?= sanitizeInput($n['topic']) ?>"><i class="fas fa-eye me-1"></i>View</button></li>
                                    <li><a class="dropdown-item" href="lesson-notes.php?edit=<?= (int)$n['id'] ?>"><i class="fas fa-edit me-1"></i>Edit</a></li>
                                    <?php if ($n['status'] !== 'published'): ?>
                                    <li><a class="dropdown-item text-success" href="lesson-notes.php?set_status=publish&id=<?= (int)$n['id'] ?>"><i class="fas fa-check-circle me-1"></i>Publish</a></li>
                                    <?php endif; ?>
                                    <?php if ($n['status'] === 'published'): ?>
                                    <li><a class="dropdown-item text-warning" href="lesson-notes.php?set_status=unpublish&id=<?= (int)$n['id'] ?>"><i class="fas fa-eye-slash me-1"></i>Unpublish</a></li>
                                    <?php endif; ?>
                                    <?php if ($n['status'] !== 'archived'): ?>
                                    <li><a class="dropdown-item" href="lesson-notes.php?set_status=archive&id=<?= (int)$n['id'] ?>"><i class="fas fa-archive me-1"></i>Archive</a></li>
                                    <?php endif; ?>
                                    <?php if ($n['status'] === 'archived'): ?>
                                    <li><a class="dropdown-item" href="lesson-notes.php?set_status=restore&id=<?= (int)$n['id'] ?>"><i class="fas fa-undo me-1"></i>Restore</a></li>
                                    <?php endif; ?>
                                    <?php if ($n['file_path']): ?>
                                    <li><a class="dropdown-item" href="/<?= $n['file_path'] ?>" target="_blank"><i class="fas fa-download me-1"></i>Download File</a></li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="lesson-notes.php?delete=<?= (int)$n['id'] ?>" onclick="return confirm('Delete this lesson note permanently?');"><i class="fas fa-trash me-1"></i>Delete</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($notes)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No lesson notes found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewBody"></div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-note').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-id');
        var topic = btn.getAttribute('data-topic');
        fetch('<?= BASE_URL ?>/api/lesson-note-detail.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('viewTitle').textContent = topic;
                document.getElementById('viewBody').innerHTML = data.content || '<p class="text-muted">No content.</p>';
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
