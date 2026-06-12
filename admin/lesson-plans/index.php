<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Lesson Plan Reviews';
$db = getDB();
$msg = '';
$msgType = 'success';

$filterStatus = sanitizeInput($_GET['status'] ?? '');
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterTeacher = (int)($_GET['teacher_id'] ?? 0);
$search = sanitizeInput($_GET['search'] ?? '');

$sql = "SELECT lp.*, sub.name as subject_name, c.name as class_name, c.section,
        u.first_name, u.last_name,
        (SELECT COUNT(*) FROM lesson_plan_reviews WHERE lesson_plan_id = lp.id) as review_count,
        (SELECT comment FROM lesson_plan_reviews WHERE lesson_plan_id = lp.id ORDER BY reviewed_at DESC LIMIT 1) as latest_comment
        FROM lesson_plans lp
        JOIN subjects sub ON lp.subject_id = sub.id
        JOIN classes c ON lp.class_id = c.id
        JOIN users u ON lp.teacher_id = u.id
        WHERE 1=1";
$params = [];

if ($filterStatus) {
    $sql .= " AND lp.status = ?";
    $params[] = $filterStatus;
} else {
    $sql .= " AND lp.status != 'draft'";
}
if ($filterSubject) { $sql .= " AND lp.subject_id = ?"; $params[] = $filterSubject; }
if ($filterTeacher) { $sql .= " AND lp.teacher_id = ?"; $params[] = $filterTeacher; }
if ($search) { $sql .= " AND (lp.topic LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)"; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }
$sql .= " ORDER BY lp.updated_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$plans = $stmt->fetchAll();

$subjects = $db->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();
$teachers = $db->query("SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.role = 'teacher' ORDER BY u.first_name")->fetchAll();

$stmt = $db->query("SELECT COUNT(*) FROM lesson_plans WHERE status IN ('submitted','under_review')");
$pendingTotal = (int)$stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) FROM lesson_plans WHERE status = 'approved'");
$approvedTotal = (int)$stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) FROM lesson_plans WHERE status = 'rejected'");
$rejectedTotal = (int)$stmt->fetchColumn();
$stmt = $db->query("SELECT COUNT(*) FROM lesson_plans WHERE status != 'draft'");
$submittedTotal = (int)$stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-check me-2"></i>Lesson Plan Reviews</h4>
        <p class="text-muted small mb-0">Review and approve teacher lesson plans</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-value"><?= $submittedTotal ?></div>
            <div class="stat-label">Total Submitted</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value"><?= $pendingTotal ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $approvedTotal ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
            <i class="fas fa-times-circle stat-icon"></i>
            <div class="stat-value"><?= $rejectedTotal ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Topic, teacher..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Non-Draft</option>
                    <option value="submitted" <?= $filterStatus === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="under_review" <?= $filterStatus === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSubject === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Teacher</label>
                <select name="teacher_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filterTeacher === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Teacher</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Week</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $lp): ?>
                    <tr class="<?= $lp['status'] === 'submitted' ? 'table-warning' : '' ?>">
                        <td><a href="review.php?id=<?= $lp['id'] ?>" class="fw-semibold"><?= sanitizeInput(mb_substr($lp['topic'], 0, 60)) ?></a></td>
                        <td><?= sanitizeInput($lp['first_name'] . ' ' . $lp['last_name']) ?></td>
                        <td><?= sanitizeInput($lp['subject_name']) ?></td>
                        <td><?= sanitizeInput($lp['class_name'] . ' ' . $lp['section']) ?></td>
                        <td>Week <?= $lp['week_no'] ?: '-' ?></td>
                        <td>
                            <?php $badge = ['draft' => 'secondary', 'submitted' => 'primary', 'under_review' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; ?>
                            <span class="badge bg-<?= $badge[$lp['status']] ?? 'secondary' ?>">
                                <?= ucfirst(str_replace('_', ' ', $lp['status'])) ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= timeAgo($lp['updated_at']) ?></small></td>
                        <td class="text-end">
                            <a href="review.php?id=<?= $lp['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-clipboard-check me-1"></i>Review</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($plans)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No lesson plans submitted for review.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
