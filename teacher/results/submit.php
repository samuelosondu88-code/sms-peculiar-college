<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Submit Results';
$db = getDB();
$teacherId = getTeacherId();
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$subjects = $db->prepare("
    SELECT sa.id as allocation_id, s.id as subject_id, s.name as subject_name, s.code, c.id as class_id, c.name as class_name, c.section
    FROM subject_allocations sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN classes c ON sa.class_id = c.id
    WHERE sa.teacher_id = ? AND sa.academic_session_id = ?
    ORDER BY c.name, s.name
");
$subjects->execute([$teacherId, $sessionId]);
$allSubjects = $subjects->fetchAll();

$drafts = [];
$submitted = [];
foreach ($allSubjects as $subj) {
    $sc = $db->prepare("SELECT COUNT(*) FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? AND status = 'draft'");
    $sc->execute([$subj['class_id'], $subj['subject_id'], $sessionId, $termId]);
    $draftCount = (int)$sc->fetchColumn();

    $sc2 = $db->prepare("SELECT COUNT(*) FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? AND status = 'submitted'");
    $sc2->execute([$subj['class_id'], $subj['subject_id'], $sessionId, $termId]);
    $submittedCount = (int)$sc2->fetchColumn();

    $totalSt = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND status = 'active'");
    $totalSt->execute([$subj['class_id']]);
    $totalStudents = (int)$totalSt->fetchColumn();

    if ($draftCount > 0) {
        $drafts[] = [
            'class_id' => $subj['class_id'],
            'subject_id' => $subj['subject_id'],
            'subject_name' => $subj['subject_name'],
            'subject_code' => $subj['code'],
            'class_name' => $subj['class_name'],
            'section' => $subj['section'],
            'draft_count' => $draftCount,
            'total_students' => $totalStudents,
        ];
    }

    if ($submittedCount > 0) {
        $approval = $db->prepare("SELECT * FROM result_approvals WHERE class_id = ? AND session_id = ? AND term_id = ? AND subject_id = ? ORDER BY updated_at DESC LIMIT 1");
        $approval->execute([$subj['class_id'], $sessionId, $termId, $subj['subject_id']]);
        $app = $approval->fetch();

        $submitted[] = [
            'class_id' => $subj['class_id'],
            'subject_id' => $subj['subject_id'],
            'subject_name' => $subj['subject_name'],
            'subject_code' => $subj['code'],
            'class_name' => $subj['class_name'],
            'section' => $subj['section'],
            'submitted_count' => $submittedCount,
            'total_students' => $totalStudents,
            'approval' => $app,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_scores'])) {
    $classId = (int)$_POST['class_id'];
    $subjectId = (int)$_POST['subject_id'];

    $allocation = $db->prepare("SELECT id FROM subject_allocations WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND academic_session_id = ?");
    $allocation->execute([$teacherId, $classId, $subjectId, $sessionId]);
    if (!$allocation->fetch()) {
        $error = 'Invalid subject or class.';
    } else {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE result_scores SET status = 'submitted' WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? AND status = 'draft'")
                ->execute([$classId, $subjectId, $sessionId, $termId]);

            $existingApproval = $db->prepare("SELECT id FROM result_approvals WHERE class_id = ? AND session_id = ? AND term_id = ? AND subject_id = ? AND approval_stage = 'class_teacher'");
            $existingApproval->execute([$classId, $sessionId, $termId, $subjectId]);
            if (!$existingApproval->fetch()) {
                $db->prepare("INSERT INTO result_approvals (class_id, session_id, term_id, subject_id, approval_stage, status, approved_by, comment) VALUES (?, ?, ?, ?, 'class_teacher', 'pending', ?, 'Awaiting class teacher review')")
                    ->execute([$classId, $sessionId, $termId, $subjectId, $_SESSION['user_id']]);
            }

            $db->commit();
            logAudit('result_submitted', 'result_scores', $subjectId, null, "Class: $classId, Subject: $subjectId");
            $success = 'Results submitted for approval successfully.';
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error submitting results: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-check-double me-2"></i>Submit Results</h4>
        <p class="text-muted small mb-0">Submit your draft results for approval</p>
    </div>
    <a href="<?= BASE_URL ?>/teacher/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (!empty($drafts)): ?>
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-pen me-2"></i>Drafts Ready for Submission</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Class</th>
                    <th>Students</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drafts as $d):
                    $isPublished = isResultPublished($db, $d['class_id'], $sessionId, $termId, $d['subject_id']);
                    if ($isPublished) continue;
                ?>
                <tr>
                    <td class="fw-medium"><?= sanitizeInput($d['subject_name']) ?></td>
                    <td><small><?= sanitizeInput($d['subject_code'] ?? '-') ?></small></td>
                    <td><span class="badge bg-secondary"><?= sanitizeInput($d['class_name']) ?> <?= sanitizeInput($d['section'] ?? '') ?></span></td>
                    <td><?= $d['draft_count'] ?> / <?= $d['total_students'] ?> scored</td>
                    <td>
                        <form method="post" class="d-inline" onsubmit="return confirm('Submit <?= sanitizeInput($d['subject_name']) ?> results for <?= sanitizeInput($d['class_name']) ?> for approval? This action cannot be undone.')">
                            <input type="hidden" name="class_id" value="<?= $d['class_id'] ?>">
                            <input type="hidden" name="subject_id" value="<?= $d['subject_id'] ?>">
                            <button type="submit" name="submit_scores" class="btn btn-primary btn-sm"><i class="fas fa-check-double me-1"></i>Submit</button>
                        </form>
                        <a href="<?= BASE_URL ?>/teacher/results/enter.php?class_id=<?= $d['class_id'] ?>&subject_id=<?= $d['subject_id'] ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-body text-center py-5">
        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
        <p class="text-muted mb-0">No draft results to submit. All scores have been submitted or not yet entered.</p>
        <a href="<?= BASE_URL ?>/teacher/results/index.php" class="btn btn-primary mt-3"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($submitted)): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-clock me-2"></i>Submitted Results - Approval Progress</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Students</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submitted as $s):
                    $approval = $s['approval'];
                    $stage = $approval ? $approval['approval_stage'] : 'class_teacher';
                    $appStatus = $approval ? $approval['status'] : 'pending';
                    $comment = $approval ? $approval['comment'] : '-';

                    $stageLabels = ['class_teacher' => 'Class Teacher', 'principal' => 'Principal', 'published' => 'Published'];
                    $stageLabel = $stageLabels[$stage] ?? ucfirst($stage);

                    $isPublished = isResultPublished($db, $s['class_id'], $sessionId, $termId, $s['subject_id']);
                    if ($isPublished) { $appStatus = 'approved'; $stageLabel = 'Published'; }
                ?>
                <tr>
                    <td class="fw-medium"><?= sanitizeInput($s['subject_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= sanitizeInput($s['class_name']) ?> <?= sanitizeInput($s['section'] ?? '') ?></span></td>
                    <td><?= $s['submitted_count'] ?> / <?= $s['total_students'] ?></td>
                    <td><span class="badge bg-info text-dark"><?= $stageLabel ?></span></td>
                    <td>
                        <?php if ($isPublished || $appStatus === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif ($appStatus === 'rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= sanitizeInput($comment) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
