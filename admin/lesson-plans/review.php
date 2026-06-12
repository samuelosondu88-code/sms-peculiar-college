<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Review Lesson Plan';
$db = getDB();
$userId = $_SESSION['user_id'];
$lpId = (int)($_GET['id'] ?? 0);
$msg = '';
$msgType = 'success';

$stmt = $db->prepare("SELECT lp.*, sub.name as subject_name, c.name as class_name, c.section,
    u.first_name, u.last_name, u.email, tr.employee_id, tr.qualification,
    term.term_name, sess.session_name
    FROM lesson_plans lp
    JOIN subjects sub ON lp.subject_id = sub.id
    JOIN classes c ON lp.class_id = c.id
    JOIN users u ON lp.teacher_id = u.id
    LEFT JOIN teachers tr ON lp.teacher_id = tr.user_id
    LEFT JOIN terms term ON lp.term_id = term.id
    LEFT JOIN academic_sessions sess ON lp.academic_session_id = sess.id
    WHERE lp.id = ?");
$stmt->execute([$lpId]);
$plan = $stmt->fetch();

if (!$plan) {
    redirect('/admin/lesson-plans/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    $action = sanitizeInput($_POST['review_action']);
    $comment = sanitizeInput($_POST['comment'] ?? '');

    if (in_array($action, ['under_review', 'approved', 'rejected', 'correction_requested'])) {
        $newStatus = $action === 'correction_requested' ? 'rejected' : $action;
        if ($action === 'under_review') $newStatus = 'under_review';

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO lesson_plan_reviews (lesson_plan_id, reviewer_id, status, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$lpId, $userId, $action, $comment]);

            $stmt = $db->prepare("UPDATE lesson_plans SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $lpId]);

            $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
            $subject = "Lesson Plan " . ucfirst($action);
            $message = "Your lesson plan '{$plan['topic']}' has been " . str_replace('_', ' ', $action) . ".";
            if ($comment) $message .= "\n\nComment: $comment";
            $stmt->execute([$userId, $plan['teacher_id'], $subject, $message]);

            $db->commit();
            $msg = "Lesson plan marked as '" . ucfirst(str_replace('_', ' ', $action)) . "' and teacher notified.";
        } catch (Exception $e) {
            $db->rollBack();
            $msg = 'Error: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

$reviews = $db->prepare("SELECT lpr.*, u.first_name, u.last_name, u.role FROM lesson_plan_reviews lpr JOIN users u ON lpr.reviewer_id = u.id WHERE lpr.lesson_plan_id = ? ORDER BY lpr.reviewed_at DESC");
$reviews->execute([$lpId]);
$reviewList = $reviews->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-clipboard-check me-2"></i>Review Lesson Plan</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($plan['subject_name']) ?> - <?= sanitizeInput($plan['class_name'] . ' ' . $plan['section']) ?></p>
    </div>
    <div>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Reviews</a>
        <a href="<?= BASE_URL ?>/teacher/lesson-plans/view.php?id=<?= $lpId ?>" class="btn btn-outline-primary" target="_blank"><i class="fas fa-external-link-alt me-1"></i>Open Teacher View</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-info-circle me-2"></i>Lesson Details</span>
                <?php $badge = ['draft' => 'secondary', 'submitted' => 'primary', 'under_review' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; ?>
                <span class="badge bg-<?= $badge[$plan['status']] ?? 'secondary' ?> fs-6"><?= ucfirst(str_replace('_', ' ', $plan['status'])) ?></span>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tr><th style="width:180px">Teacher</th><td><?= sanitizeInput($plan['first_name'] . ' ' . $plan['last_name']) ?></td></tr>
                    <tr><th>Staff ID</th><td><?= sanitizeInput($plan['employee_id'] ?? '-') ?></td></tr>
                    <tr><th>Email</th><td><?= sanitizeInput($plan['email']) ?></td></tr>
                    <tr><th>Subject</th><td><?= sanitizeInput($plan['subject_name']) ?></td></tr>
                    <tr><th>Class</th><td><?= sanitizeInput($plan['class_name'] . ' ' . $plan['section']) ?></td></tr>
                    <tr><th>Term</th><td><?= sanitizeInput($plan['term_name'] ?? '-') ?> (<?= sanitizeInput($plan['session_name'] ?? '-') ?>)</td></tr>
                    <tr><th>Week</th><td><?= $plan['week_no'] ?: '-' ?></td></tr>
                    <tr><th>Date</th><td><?= $plan['date_planned'] ? formatDate($plan['date_planned']) : '-' ?></td></tr>
                    <tr><th>Duration</th><td><?= sanitizeInput($plan['duration'] ?: '-') ?></td></tr>
                    <tr><th class="fs-5" colspan="2"><?= sanitizeInput($plan['topic']) ?></th></tr>
                    <?php if ($plan['sub_topic']): ?>
                    <tr><th>Sub-topic</th><td><?= sanitizeInput($plan['sub_topic']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <?php $sections = [
            'Learning Objectives' => 'learning_objectives',
            'Previous Knowledge' => 'previous_knowledge',
            'Instructional Materials' => 'instructional_materials',
            'Teaching Methods' => 'teaching_methods',
            'Introduction / Set Induction' => 'introduction',
            'Presentation / Lesson Development' => 'presentation_steps',
            'Teacher Activities' => 'classroom_activities',
            'Student Activities' => 'student_activities',
            'Assessment / Evaluation' => 'assessment',
            'Assignment / Homework' => 'assignment',
            'Reference Materials' => 'reference_materials',
            'Remarks' => 'remarks',
        ]; ?>
        <?php foreach ($sections as $label => $col): ?>
        <?php if (!empty($plan[$col])): ?>
        <div class="card mb-3">
            <div class="card-header"><?= $label ?></div>
            <div class="card-body"><?= nl2br(sanitizeInput($plan[$col])) ?></div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-check-circle me-2"></i>Review Actions</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="d-flex flex-column gap-2">
                            <?php if ($plan['status'] === 'submitted'): ?>
                            <button type="submit" name="review_action" value="under_review" class="btn btn-warning w-100"><i class="fas fa-eye me-1"></i>Mark as Under Review</button>
                            <?php endif; ?>
                            <button type="submit" name="review_action" value="approved" class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Approve</button>
                            <button type="submit" name="review_action" value="correction_requested" class="btn btn-info w-100"><i class="fas fa-edit me-1"></i>Request Correction</button>
                            <button type="submit" name="review_action" value="rejected" class="btn btn-danger w-100" onclick="return confirm('Reject this lesson plan? The teacher will be notified.')"><i class="fas fa-times me-1"></i>Reject</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comment / Feedback</label>
                        <textarea name="comment" class="form-control" rows="4" placeholder="Provide feedback for the teacher..."><?= sanitizeInput($_POST['comment'] ?? '') ?></textarea>
                    </div>
                    <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i>The teacher will be notified via message.</p>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Review History</div>
            <div class="card-body p-0">
                <?php if (empty($reviewList)): ?>
                <div class="p-3 text-muted small">No reviews yet.</div>
                <?php endif; ?>
                <?php foreach ($reviewList as $r): ?>
                <div class="border-bottom p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong><?= sanitizeInput($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                        <?php $rbadge = ['under_review' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'correction_requested' => 'info']; ?>
                        <span class="badge bg-<?= $rbadge[$r['status']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_', ' ', $r['status'])) ?></span>
                    </div>
                    <small class="text-muted"><?= timeAgo($r['reviewed_at']) ?></small>
                    <?php if ($r['comment']): ?>
                    <p class="mb-0 mt-1 small"><?= nl2br(sanitizeInput($r['comment'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
