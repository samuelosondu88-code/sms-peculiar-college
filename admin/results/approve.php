<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Result Approval Workflow';
$db = getDB();
$msg = '';
$msgType = 'success';

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT id, term_name, session_id FROM terms ORDER BY session_id, id")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_GET['term_id'] ?? $_POST['term_id'] ?? 0);
$selectedClass = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_action'])) {
    $classId = (int)$_POST['class_id'];
    $sessionId = (int)$_POST['session_id'];
    $termId = (int)$_POST['term_id'];
    $stage = sanitizeInput($_POST['stage'] ?? '');
    $action = sanitizeInput($_POST['approve_action']);
    $comment = sanitizeInput($_POST['comment'] ?? '');

    $stages = ['subject_teacher', 'class_teacher', 'principal', 'published'];
    $currentIdx = array_search($stage, $stages);
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    $stmt = $db->prepare("SELECT id FROM result_approvals WHERE class_id = ? AND session_id = ? AND term_id = ? AND subject_id IS NULL AND approval_stage = ?");
    $stmt->execute([$classId, $sessionId, $termId, $stage]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE result_approvals SET status = ?, comment = ?, approved_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $comment, $_SESSION['user_id'], $existing['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO result_approvals (class_id, session_id, term_id, approval_stage, status, comment, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$classId, $sessionId, $termId, $stage, $newStatus, $comment, $_SESSION['user_id']]);
    }

    if ($newStatus === 'approved' && $currentIdx < count($stages) - 1) {
        $nextStage = $stages[$currentIdx + 1];
        $check = $db->prepare("SELECT id FROM result_approvals WHERE class_id = ? AND session_id = ? AND term_id = ? AND subject_id IS NULL AND approval_stage = ?");
        $check->execute([$classId, $sessionId, $termId, $nextStage]);
        if (!$check->fetch()) {
            $stmt = $db->prepare("INSERT INTO result_approvals (class_id, session_id, term_id, approval_stage, status, comment) VALUES (?, ?, ?, ?, 'pending', 'Awaiting approval')");
            $stmt->execute([$classId, $sessionId, $termId, $nextStage]);
        }
    }

    logAudit("result_{$action}_{$stage}", 'result_approvals', null, null, "Class=$classId, Session=$sessionId, Term=$termId");
    $msg = "Result approval '$action' for stage '$stage' processed successfully.";
}

$approvalData = [];
if ($selectedClass && $selectedSession && $selectedTerm) {
    $approvalData = getResultApprovalStatus($db, $selectedClass, $selectedSession, $selectedTerm);
}

$pendingClasses = $db->prepare("
    SELECT DISTINCT c.id, c.name, c.section, ac.session_name, t.term_name, ra.approval_stage, ra.updated_at
    FROM result_approvals ra
    JOIN classes c ON ra.class_id = c.id
    JOIN academic_sessions ac ON ra.session_id = ac.id
    JOIN terms t ON ra.term_id = t.id
    WHERE ra.status = 'pending'
    ORDER BY ra.updated_at ASC
");
$pendingClasses->execute();
$pendingClasses = $pendingClasses->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-check-double me-2"></i>Approval Workflow</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header"><i class="fas fa-clock me-2"></i>Pending Approvals</div>
            <div class="card-body p-0">
                <?php if (!empty($pendingClasses)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>Class</th><th>Session</th><th>Term</th><th>Stage</th><th>Requested</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingClasses as $pc): ?>
                            <tr>
                                <td><?= sanitizeInput($pc['name'] . ' ' . $pc['section']) ?></td>
                                <td><?= sanitizeInput($pc['session_name']) ?></td>
                                <td><?= sanitizeInput($pc['term_name']) ?></td>
                                <td><?= ucwords(str_replace('_', ' ', $pc['approval_stage'])) ?></td>
                                <td><small><?= timeAgo($pc['updated_at']) ?></small></td>
                                <td>
                                    <a href="?session_id=<?= $selectedSession ?>&term_id=<?= $selectedTerm ?>&class_id=<?= $pc['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-arrow-right me-1"></i>Review</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-4 text-center text-muted">No pending approvals.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select">
                    <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>View</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($approvalData)): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-timeline me-2"></i>Approval Stages</div>
    <div class="card-body">
        <?php
        $allStages = ['subject_teacher' => 'Subject Teacher', 'class_teacher' => 'Class Teacher', 'principal' => 'Principal', 'published' => 'Published'];
        foreach ($allStages as $key => $label):
            $current = array_filter($approvalData, fn($a) => $a['approval_stage'] === $key);
            $current = reset($current);
        ?>
        <div class="row mb-3 align-items-center">
            <div class="col-md-2 fw-bold"><?= $label ?></div>
            <div class="col-md-2">
                <?php if ($current): ?>
                    <?php if ($current['status'] === 'approved'): ?>
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>
                    <?php elseif ($current['status'] === 'rejected'): ?>
                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                    <?php endif; ?>
                <?php else: ?>
                <span class="badge bg-secondary">Not Started</span>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <?php if ($current && $current['approved_by_name']): ?>
                <small class="text-muted">by <?= sanitizeInput($current['approved_by_name']) ?></small>
                <?php endif; ?>
            </div>
            <div class="col-md-5">
                <?php if ($current && $current['comment']): ?>
                <small class="text-muted fst-italic">"<?= sanitizeInput($current['comment']) ?>"</small>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php
        $lastApproved = null;
        $lastIdx = -1;
        foreach ($allStages as $key => $label) {
            $current = array_filter($approvalData, fn($a) => $a['approval_stage'] === $key);
            $current = reset($current);
            if ($current && $current['status'] === 'approved') {
                $lastApproved = $key;
                $lastIdx = array_search($key, array_keys($allStages));
            }
        }
        $stagesArr = array_keys($allStages);
        $nextStage = $stagesArr[$lastIdx + 1] ?? null;
        $isPublished = $lastApproved === 'published';
        ?>

        <?php if (!$isPublished && $nextStage): ?>
        <hr>
        <form method="POST" class="row g-3">
            <input type="hidden" name="session_id" value="<?= $selectedSession ?>">
            <input type="hidden" name="term_id" value="<?= $selectedTerm ?>">
            <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
            <input type="hidden" name="stage" value="<?= $nextStage ?>">
            <div class="col-md-8">
                <label class="form-label">Comment (optional)</label>
                <textarea name="comment" class="form-control" rows="2" placeholder="Add a comment for this approval stage..."></textarea>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" name="approve_action" value="approve" class="btn btn-success"><i class="fas fa-check me-1"></i>Approve <?= $allStages[$nextStage] ?></button>
                <button type="submit" name="approve_action" value="reject" class="btn btn-danger" onclick="return confirm('Reject this approval stage?')"><i class="fas fa-times me-1"></i>Reject</button>
            </div>
        </form>
        <?php elseif ($isPublished): ?>
        <div class="alert alert-success mt-3 mb-0"><i class="fas fa-check-circle me-2"></i>Results are fully approved and published.</div>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($selectedClass && $selectedSession && $selectedTerm): ?>
<div class="card">
    <div class="card-body text-center text-muted py-4">
        <p class="mb-2"><i class="fas fa-info-circle fa-2x mb-2"></i></p>
        <p>No approval records found for this class, session, and term.</p>
        <p class="small">Start the approval process by submitting scores first, then use the form above.</p>
        <form method="POST" class="d-inline">
            <input type="hidden" name="session_id" value="<?= $selectedSession ?>">
            <input type="hidden" name="term_id" value="<?= $selectedTerm ?>">
            <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
            <input type="hidden" name="stage" value="subject_teacher">
            <button type="submit" name="approve_action" value="approve" class="btn btn-primary"><i class="fas fa-play me-1"></i>Start Approval Workflow</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
