<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Assignments';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';

$stmt = $db->prepare("SELECT s.id, s.class_id FROM students s WHERE s.user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $filePath = uploadFile($_FILES['file'], 'documents/submissions');
        if ($filePath) {
            $stmt = $db->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, status) VALUES (?, ?, ?, 'submitted') ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), status = 'submitted', submitted_at = NOW()");
            $stmt->execute([$assignmentId, $student['id'], $filePath]);
            $msg = 'Assignment submitted successfully.';
        }
    }
}

$assignments = [];
$submissions = [];
if ($student) {
    $assignments = $db->prepare("
        SELECT a.*, sub.name as subject_name, u.first_name, u.last_name
        FROM assignments a
        JOIN subjects sub ON a.subject_id = sub.id
        JOIN users u ON a.teacher_id = u.id
        WHERE a.class_id = ?
        ORDER BY a.due_date DESC
    ");
    $assignments->execute([$student['class_id']]);
    $assignments = $assignments->fetchAll();

    $sub = $db->prepare("SELECT assignment_id, status, score, feedback, file_path FROM submissions WHERE student_id = ?");
    $sub->execute([$student['id']]);
    foreach ($sub->fetchAll() as $s) {
        $submissions[$s['assignment_id']] = $s;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-tasks me-2"></i>My Assignments</h4>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<?php foreach ($assignments as $a):
    $sub = $submissions[$a['id']] ?? null;
    $isLate = strtotime($a['due_date']) < time();
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <h5 class="fw-bold"><?= sanitizeInput($a['title']) ?></h5>
            <div>
                <?php if ($sub): ?>
                    <?= getStatusBadge($sub['status']) ?>
                <?php elseif ($isLate): ?>
                    <span class="badge bg-danger">Overdue</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Pending</span>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-muted small mb-2">
            <?= sanitizeInput($a['subject_name']) ?> | Teacher: <?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?>
            | Due: <?= formatDate($a['due_date']) ?>
            <?php if ($isLate && !$sub): ?>
            <span class="text-danger fw-bold">(Overdue by <?= floor((time() - strtotime($a['due_date'])) / 86400) ?> days)</span>
            <?php endif; ?>
        </p>
        <?php if ($a['description']): ?>
        <p><?= nl2br(sanitizeInput($a['description'])) ?></p>
        <?php endif; ?>
        <?php if ($a['file_path']): ?>
        <a href="/<?= $a['file_path'] ?>" class="btn btn-sm btn-outline-primary mb-2" target="_blank"><i class="fas fa-download me-1"></i>Download Assignment</a>
        <?php endif; ?>

        <?php if ($sub): ?>
            <?php if ($sub['file_path']): ?>
            <p class="small text-success"><i class="fas fa-check me-1"></i>Submitted: <a href="/<?= $sub['file_path'] ?>" target="_blank">View Submission</a></p>
            <?php endif; ?>
            <?php if ($sub['score'] !== null): ?>
            <p class="mb-0"><strong>Score: <?= $sub['score'] ?> | Feedback: </strong><?= sanitizeInput($sub['feedback'] ?? 'None') ?></p>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <input type="file" name="file" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <button type="submit" name="submit_assignment" class="btn btn-sm btn-primary">Submit</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($assignments)): ?>
<div class="alert alert-info">No assignments posted yet.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
