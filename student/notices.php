<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Notices & Announcements';
$db = getDB();

/* Notices aimed at everyone or students, that have not expired. */
$sql = "SELECT n.*, u.first_name, u.last_name
        FROM notices n
        JOIN users u ON n.created_by = u.id
        WHERE n.target_role IN ('all','student')
          AND (n.expires_at IS NULL OR n.expires_at >= CURDATE())
        ORDER BY n.priority = 'urgent' DESC, n.priority = 'important' DESC, n.created_at DESC";
$notices = $db->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-bullhorn me-2"></i>Notices &amp; Announcements</h4>
        <p class="text-muted small mb-0">Latest news and announcements from the school.</p>
    </div>
</div>

<?php if (empty($notices)): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>No announcements at this time.</div>
<?php endif; ?>

<?php foreach ($notices as $n): ?>
<div class="card mb-3 border-<?= $n['priority'] === 'urgent' ? 'danger' : ($n['priority'] === 'important' ? 'warning' : 'primary') ?>">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <h5 class="fw-bold mb-1">
                <?php if ($n['priority'] === 'urgent'): ?><span class="badge bg-danger me-1">URGENT</span><?php endif; ?>
                <?php if ($n['priority'] === 'important'): ?><span class="badge bg-warning text-dark me-1">IMPORTANT</span><?php endif; ?>
                <?= sanitizeInput($n['title']) ?>
            </h5>
            <small class="text-muted"><?= timeAgo($n['created_at']) ?></small>
        </div>
        <p class="mb-1"><?= nl2br(sanitizeInput($n['content'])) ?></p>
        <?php if ($n['file_path']): ?>
        <a href="/<?= $n['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1"><i class="fas fa-download me-1"></i>Attachment</a>
        <?php endif; ?>
        <small class="text-muted d-block mt-1">By: <?= sanitizeInput($n['first_name'] . ' ' . $n['last_name']) ?></small>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
