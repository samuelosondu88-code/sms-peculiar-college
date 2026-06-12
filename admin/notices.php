<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Notices & Announcements';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notice'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    $targetRole = sanitizeInput($_POST['target_role'] ?? 'all');
    $priority = sanitizeInput($_POST['priority'] ?? 'normal');

    $stmt = $db->prepare("INSERT INTO notices (title, content, target_role, priority, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $content, $targetRole, $priority, $_SESSION['user_id']]);
    redirect('/admin/notices.php?msg=Notice published');
}

$notices = $db->query("SELECT n.*, u.first_name, u.last_name FROM notices n JOIN users u ON n.created_by = u.id ORDER BY n.created_at DESC")->fetchAll();
$msg = sanitizeInput($_GET['msg'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-bullhorn me-2"></i>Notices & Announcements</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#noticeModal">
        <i class="fas fa-plus me-1"></i>New Notice
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<?php foreach ($notices as $n): ?>
<div class="card mb-3 border-<?= $n['priority'] === 'urgent' ? 'danger' : ($n['priority'] === 'important' ? 'warning' : 'primary') ?>">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <h5 class="fw-bold">
                <?php if ($n['priority'] === 'urgent'): ?><span class="badge bg-danger me-1">URGENT</span><?php endif; ?>
                <?php if ($n['priority'] === 'important'): ?><span class="badge bg-warning text-dark me-1">IMPORTANT</span><?php endif; ?>
                <?= sanitizeInput($n['title']) ?>
            </h5>
            <small class="text-muted"><?= timeAgo($n['created_at']) ?></small>
        </div>
        <p class="mb-1"><?= nl2br(sanitizeInput($n['content'])) ?></p>
        <small class="text-muted">
            By: <?= sanitizeInput($n['first_name'] . ' ' . $n['last_name']) ?> |
            For: <?= ucfirst($n['target_role']) ?>
        </small>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="noticeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">New Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Target Audience</label>
                            <select name="target_role" class="form-select">
                                <option value="all">Everyone</option>
                                <option value="admin">Admins Only</option>
                                <option value="teacher">Teachers</option>
                                <option value="student">Students</option>
                                <option value="parent">Parents</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="normal">Normal</option>
                                <option value="important">Important</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="save_notice" value="1">
                    <button type="submit" class="btn btn-primary">Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
