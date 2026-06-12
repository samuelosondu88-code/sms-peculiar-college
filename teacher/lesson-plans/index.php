<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Lesson Plans';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$stmt = $db->prepare("SELECT id, employee_id FROM teachers WHERE user_id = ?");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

$currentTerm = getCurrentTerm();
$termId = $currentTerm['id'] ?? 0;
$sessionId = $currentTerm['session_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_lp'])) {
        $lpId = (int)$_POST['lp_id'];
        $stmt = $db->prepare("DELETE FROM lesson_plans WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$lpId, $userId]);
        $msg = 'Lesson plan deleted.';
    }
    if (isset($_POST['submit_lp'])) {
        $lpId = (int)$_POST['lp_id'];
        $stmt = $db->prepare("UPDATE lesson_plans SET status = 'submitted' WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$lpId, $userId]);
        $msg = 'Lesson plan submitted for review.';
    }
    if (isset($_POST['copy_lp'])) {
        $lpId = (int)$_POST['lp_id'];
        $stmt = $db->prepare("SELECT * FROM lesson_plans WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$lpId, $userId]);
        $orig = $stmt->fetch();
        if ($orig) {
            $stmt = $db->prepare("INSERT INTO lesson_plans (teacher_id, staff_id, subject_id, class_id, term_id, academic_session_id, week_no, topic, sub_topic, learning_objectives, previous_knowledge, instructional_materials, teaching_methods, introduction, presentation_steps, classroom_activities, student_activities, assessment, assignment, reference_materials, remarks, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft')");
            $stmt->execute([
                $userId, $orig['staff_id'], $orig['subject_id'], $orig['class_id'],
                $orig['term_id'], $orig['academic_session_id'], $orig['week_no'],
                $orig['topic'] . ' (Copy)', $orig['sub_topic'],
                $orig['learning_objectives'], $orig['previous_knowledge'],
                $orig['instructional_materials'], $orig['teaching_methods'],
                $orig['introduction'], $orig['presentation_steps'],
                $orig['classroom_activities'], $orig['student_activities'],
                $orig['assessment'], $orig['assignment'],
                $orig['reference_materials'], $orig['remarks']
            ]);
            $msg = 'Lesson plan copied.';
        }
    }
}

$search = sanitizeInput($_GET['search'] ?? '');
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterStatus = sanitizeInput($_GET['status'] ?? '');
$filterWeek = (int)($_GET['week'] ?? 0);

$sql = "SELECT lp.*, sub.name as subject_name, c.name as class_name, c.section,
        (SELECT COUNT(*) FROM lesson_plan_reviews lpr WHERE lpr.lesson_plan_id = lp.id) as review_count
        FROM lesson_plans lp
        JOIN subjects sub ON lp.subject_id = sub.id
        JOIN classes c ON lp.class_id = c.id
        WHERE lp.teacher_id = ?";
$params = [$userId];

if ($search) {
    $sql .= " AND (lp.topic LIKE ? OR lp.sub_topic LIKE ? OR lp.learning_objectives LIKE ?)";
    $p = "%$search%";
    $params[] = $p; $params[] = $p; $params[] = $p;
}
if ($filterSubject) { $sql .= " AND lp.subject_id = ?"; $params[] = $filterSubject; }
if ($filterClass) { $sql .= " AND lp.class_id = ?"; $params[] = $filterClass; }
if ($filterStatus) { $sql .= " AND lp.status = ?"; $params[] = $filterStatus; }
if ($filterWeek) { $sql .= " AND lp.week_no = ?"; $params[] = $filterWeek; }
$sql .= " ORDER BY lp.updated_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$plans = $stmt->fetchAll();

$subjects = $db->prepare("SELECT DISTINCT s.id, s.name FROM subjects s WHERE s.teacher_id = ?");
$subjects->execute([$userId]);
$mySubjects = $subjects->fetchAll();

$classes = $db->prepare("SELECT DISTINCT c.id, c.name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
$classes->execute([$userId]);
$myClasses = $classes->fetchAll();

$totalPlans = count($plans);
$stmt = $db->prepare("SELECT COUNT(*) FROM lesson_plans WHERE teacher_id = ? AND status = 'submitted'");
$stmt->execute([$userId]); $submittedCount = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM lesson_plans WHERE teacher_id = ? AND status = 'approved'");
$stmt->execute([$userId]); $approvedCount = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM lesson_plans WHERE teacher_id = ? AND status IN ('under_review','submitted')");
$stmt->execute([$userId]); $pendingCount = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM lesson_plans WHERE teacher_id = ? AND status = 'rejected'");
$stmt->execute([$userId]); $rejectedCount = (int)$stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-book-open me-2"></i>Lesson Plans</h4>
        <p class="text-muted small mb-0">Manage your lesson plans</p>
    </div>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Lesson Plan</a>
        <a href="ai-assistant.php" class="btn btn-gold"><i class="fas fa-robot me-1"></i>AI Assistant</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-book stat-icon"></i>
            <div class="stat-value"><?= $totalPlans ?></div>
            <div class="stat-label">Total Plans</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-paper-plane stat-icon"></i>
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $approvedCount ?></div>
            <div class="stat-label">Approved</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
            <i class="fas fa-times-circle stat-icon"></i>
            <div class="stat-value"><?= $rejectedCount ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Topic, objectives..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($mySubjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSubject === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($myClasses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="submitted" <?= $filterStatus === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="under_review" <?= $filterStatus === 'under_review' ? 'selected' : '' ?>>Under Review</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Week</label>
                <input type="number" name="week" class="form-control" min="1" max="15" value="<?= $filterWeek ?: '' ?>">
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
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Week</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $lp): ?>
                    <tr>
                        <td><a href="view.php?id=<?= $lp['id'] ?>" class="fw-semibold"><?= sanitizeInput(mb_substr($lp['topic'], 0, 60)) ?></a></td>
                        <td><?= sanitizeInput($lp['subject_name']) ?></td>
                        <td><?= sanitizeInput($lp['class_name']) ?></td>
                        <td>Week <?= $lp['week_no'] ?: '-' ?></td>
                        <td>
                            <?php $badge = ['draft' => 'secondary', 'submitted' => 'primary', 'under_review' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; ?>
                            <span class="badge bg-<?= $badge[$lp['status']] ?? 'secondary' ?>">
                                <?= ucfirst(str_replace('_', ' ', $lp['status'])) ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= timeAgo($lp['updated_at']) ?></small></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="view.php?id=<?= $lp['id'] ?>"><i class="fas fa-eye me-2"></i>View</a></li>
                                    <?php if ($lp['status'] === 'draft' || $lp['status'] === 'rejected'): ?>
                                    <li><a class="dropdown-item" href="create.php?id=<?= $lp['id'] ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <?php endif; ?>
                                    <?php if ($lp['status'] === 'draft'): ?>
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="lp_id" value="<?= $lp['id'] ?>">
                                            <button type="submit" name="submit_lp" class="dropdown-item"><i class="fas fa-paper-plane me-2"></i>Submit</button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="lp_id" value="<?= $lp['id'] ?>">
                                            <button type="submit" name="copy_lp" class="dropdown-item"><i class="fas fa-copy me-2"></i>Copy</button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if ($lp['status'] === 'draft'): ?>
                                    <li>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this lesson plan?')">
                                            <input type="hidden" name="lp_id" value="<?= $lp['id'] ?>">
                                            <button type="submit" name="delete_lp" class="dropdown-item text-danger"><i class="fas fa-trash me-2"></i>Delete</button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($plans)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No lesson plans found. <a href="create.php">Create your first lesson plan</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
