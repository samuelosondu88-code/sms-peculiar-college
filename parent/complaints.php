<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Submit Complaint';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? 'general');
    if ($subject && $description) {
        $stmt = $db->prepare("INSERT INTO complaints (user_id, subject, description, category) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $subject, $description, $category]);
        $msg = 'Complaint submitted successfully. We will review and respond.';
    }
}

$stmt = $db->prepare("SELECT * FROM complaints WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$complaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-comment-dots me-2"></i>Complaints & Suggestions</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#complaintModal">
        <i class="fas fa-plus me-1"></i>New Complaint
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="row">
    <?php foreach ($complaints as $c): ?>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h6 class="fw-bold"><?= sanitizeInput($c['subject']) ?></h6>
                    <?= getStatusBadge($c['status']) ?>
                </div>
                <p class="small text-muted mb-1"><?= sanitizeInput($c['category']) ?> | <?= formatDate($c['created_at']) ?></p>
                <p><?= nl2br(sanitizeInput($c['description'])) ?></p>
                <?php if ($c['response']): ?>
                <div class="bg-light p-3 rounded mt-2">
                    <small class="fw-bold text-primary">Response:</small>
                    <p class="mb-0 small"><?= nl2br(sanitizeInput($c['response'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($complaints)): ?>
    <div class="col-12"><div class="alert alert-info">No complaints submitted yet.</div></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="complaintModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Complaint / Suggestion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="general">General</option>
                            <option value="academic">Academic</option>
                            <option value="behavior">Behavior</option>
                            <option value="facilities">Facilities</option>
                            <option value="administrative">Administrative</option>
                            <option value="suggestion">Suggestion</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="submit_complaint" value="1">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
