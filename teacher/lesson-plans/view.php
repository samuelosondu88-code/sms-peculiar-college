<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'View Lesson Plan';
$db = getDB();
$userId = $_SESSION['user_id'];
$lpId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT lp.*, sub.name as subject_name, c.name as class_name, c.section,
    t.first_name as teacher_first, t.last_name as teacher_last, t.email as teacher_email,
    tr.employee_id, tr.qualification,
    term.term_name, sess.session_name
    FROM lesson_plans lp
    JOIN subjects sub ON lp.subject_id = sub.id
    JOIN classes c ON lp.class_id = c.id
    JOIN users t ON lp.teacher_id = t.id
    LEFT JOIN teachers tr ON lp.teacher_id = tr.user_id
    LEFT JOIN terms term ON lp.term_id = term.id
    LEFT JOIN academic_sessions sess ON lp.academic_session_id = sess.id
    WHERE lp.id = ? AND lp.teacher_id = ?");
$stmt->execute([$lpId, $userId]);
$plan = $stmt->fetch();

if (!$plan) {
    redirect('/teacher/lesson-plans/index.php');
}

$reviews = $db->prepare("SELECT lpr.*, u.first_name, u.last_name, u.role FROM lesson_plan_reviews lpr JOIN users u ON lpr.reviewer_id = u.id WHERE lpr.lesson_plan_id = ? ORDER BY lpr.reviewed_at DESC");
$reviews->execute([$lpId]);
$reviewList = $reviews->fetchAll();

$fields = [
    'Topic' => 'topic',
    'Sub-topic' => 'sub_topic',
    'Duration' => 'duration',
    'Date' => 'date_planned',
    'Week' => 'week_no',
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-book-open me-2"></i>Lesson Plan</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($plan['subject_name']) ?> - <?= sanitizeInput($plan['class_name'] . ' ' . $plan['section']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($plan['status'] === 'draft' || $plan['status'] === 'rejected'): ?>
        <a href="create.php?id=<?= $plan['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-edit me-1"></i>Edit</a>
        <?php endif; ?>
        <?php if ($plan['status'] === 'draft'): ?>
        <form method="POST" action="index.php" class="d-inline">
            <input type="hidden" name="lp_id" value="<?= $plan['id'] ?>">
            <button type="submit" name="submit_lp" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Submit</button>
        </form>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="fas fa-print me-1"></i>Print</button>
    </div>
</div>

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
                    <tr><th style="width:180px">Teacher</th><td><?= sanitizeInput($plan['teacher_first'] . ' ' . $plan['teacher_last']) ?></td></tr>
                    <tr><th>Staff ID</th><td><?= sanitizeInput($plan['employee_id'] ?? '-') ?></td></tr>
                    <tr><th>Subject</th><td><?= sanitizeInput($plan['subject_name']) ?></td></tr>
                    <tr><th>Class</th><td><?= sanitizeInput($plan['class_name'] . ' ' . $plan['section']) ?></td></tr>
                    <tr><th>Term</th><td><?= sanitizeInput($plan['term_name'] ?? '-') ?></td></tr>
                    <tr><th>Academic Session</th><td><?= sanitizeInput($plan['session_name'] ?? '-') ?></td></tr>
                    <tr><th>Week</th><td><?= $plan['week_no'] ?: '-' ?></td></tr>
                    <tr><th>Date</th><td><?= $plan['date_planned'] ? formatDate($plan['date_planned']) : '-' ?></td></tr>
                    <tr><th>Duration</th><td><?= sanitizeInput($plan['duration'] ?: '-') ?></td></tr>
                    <tr><th>Topic</th><td class="fw-bold"><?= sanitizeInput($plan['topic']) ?></td></tr>
                    <tr><th>Sub-topic</th><td><?= sanitizeInput($plan['sub_topic'] ?: '-') ?></td></tr>
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
            'Classroom Activities' => 'classroom_activities',
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

<style>
@media print {
    #sidebar-wrapper, .navbar, .no-print, .dropdown, .btn, form { display: none !important; }
    #page-content-wrapper { margin-left: 0 !important; }
    .container-fluid { padding: 0 !important; max-width: 100% !important; }
    .card { break-inside: avoid; border: 1px solid #ddd !important; box-shadow: none !important; }
    .card-header { background: #f5f5f5 !important; }
    body { font-size: 12pt; color: #000; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
