<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Assignments';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignment'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $dueDate = sanitizeInput($_POST['due_date'] ?? '');
    $filePath = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filePath = uploadFile($_FILES['file'], 'documents/assignments');
    }

    if ($title && $subjectId && $classId && $dueDate) {
        $stmt = $db->prepare("INSERT INTO assignments (title, description, subject_id, teacher_id, class_id, file_path, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $subjectId, $userId, $classId, $filePath, $dueDate]);
        $msg = 'Assignment created successfully.';
    } else {
        $error = 'Please fill all required fields.';
    }
}

$subjects = $db->prepare("SELECT s.id, s.name, c.name as class_name FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
$subjects->execute([$userId]);
$mySubjects = $subjects->fetchAll();

$assignments = $db->prepare("SELECT a.*, sub.name as subject_name, c.name as class_name FROM assignments a JOIN subjects sub ON a.subject_id = sub.id JOIN classes c ON a.class_id = c.id WHERE a.teacher_id = ? ORDER BY a.created_at DESC");
$assignments->execute([$userId]);
$assignmentsList = $assignments->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-tasks me-2"></i>Assignments</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignmentModal">
        <i class="fas fa-plus me-1"></i>New Assignment
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<?php foreach ($assignmentsList as $a): ?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <h5 class="fw-bold mb-1"><?= sanitizeInput($a['title']) ?></h5>
            <small class="text-muted">Due: <?= formatDate($a['due_date']) ?></small>
        </div>
        <p class="text-muted small mb-2">
            <?= sanitizeInput($a['subject_name']) ?> | <?= sanitizeInput($a['class_name']) ?>
            | <?= timeAgo($a['created_at']) ?>
        </p>
        <?php if ($a['description']): ?>
        <p><?= nl2br(sanitizeInput($a['description'])) ?></p>
        <?php endif; ?>
        <?php if ($a['file_path']): ?>
        <a href="/<?= $a['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
            <i class="fas fa-download me-1"></i>Download File
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($assignmentsList)): ?>
<div class="alert alert-info">No assignments created yet.</div>
<?php endif; ?>

<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Create Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Subject *</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($mySubjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['name'] . ' - ' . $s['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date *</label>
                            <input type="datetime-local" name="due_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment (optional)</label>
                        <input type="file" name="file" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="save_assignment" value="1">
                    <button type="submit" class="btn btn-primary">Create Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
