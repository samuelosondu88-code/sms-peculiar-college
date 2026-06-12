<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Lesson Notes';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $topic = sanitizeInput($_POST['topic'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $weekNo = (int)($_POST['week_no'] ?? 0);
    $dateTaught = sanitizeInput($_POST['date_taught'] ?? '');

    $filePath = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filePath = uploadFile($_FILES['file'], 'documents/assignments');
    }

    if ($topic && $subjectId && $classId) {
        $stmt = $db->prepare("INSERT INTO lesson_notes (teacher_id, subject_id, class_id, topic, content, file_path, week_no, date_taught) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $subjectId, $classId, $topic, $content, $filePath, $weekNo, $dateTaught]);
        $msg = 'Lesson note saved.';
    }
}

$notes = $db->prepare("SELECT ln.*, sub.name as subject_name, c.name as class_name FROM lesson_notes ln JOIN subjects sub ON ln.subject_id = sub.id JOIN classes c ON ln.class_id = c.id WHERE ln.teacher_id = ? ORDER BY ln.created_at DESC");
$notes->execute([$userId]);
$notesList = $notes->fetchAll();

$subjects = $db->prepare("SELECT s.id, s.name, c.name as class_name, c.id as class_id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
$subjects->execute([$userId]);
$mySubjects = $subjects->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-sticky-note me-2"></i>Lesson Notes</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noteModal">
        <i class="fas fa-plus me-1"></i>New Lesson Note
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<?php foreach ($notesList as $n): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <h5 class="fw-bold"><?= sanitizeInput($n['topic']) ?></h5>
            <small class="text-muted">Week <?= $n['week_no'] ?: '-' ?></small>
        </div>
        <p class="text-muted small">
            <?= sanitizeInput($n['subject_name']) ?> | <?= sanitizeInput($n['class_name']) ?>
            <?= $n['date_taught'] ? '| Taught: ' . formatDate($n['date_taught']) : '' ?>
        </p>
        <?php if ($n['content']): ?>
        <p><?= nl2br(sanitizeInput(mb_substr($n['content'], 0, 300))) ?><?= mb_strlen($n['content']) > 300 ? '...' : '' ?></p>
        <?php endif; ?>
        <?php if ($n['file_path']): ?>
        <a href="/<?= $n['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="fas fa-download me-1"></i>File</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($notesList)): ?>
<div class="alert alert-info">No lesson notes yet.</div>
<?php endif; ?>

<div class="modal fade" id="noteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">New Lesson Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Topic *</label>
                        <input type="text" name="topic" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($mySubjects as $s): ?>
                                <option value="<?= $s['id'] ?>" data-class="<?= $s['class_id'] ?>"><?= sanitizeInput($s['name'] . ' - ' . $s['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Week No.</label>
                            <input type="number" name="week_no" class="form-control" min="1" max="15">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date Taught</label>
                            <input type="date" name="date_taught" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="6"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment</label>
                        <input type="file" name="file" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="class_id" id="note_class_id" value="0">
                    <input type="hidden" name="save_note" value="1">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelector('[name="subject_id"]')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    document.getElementById('note_class_id').value = opt.getAttribute('data-class') || 0;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
