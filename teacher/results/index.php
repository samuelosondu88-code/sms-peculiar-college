<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Results Management';
$db = getDB();
$teacherId = getTeacherId();
$userId = (int)$_SESSION['user_id'];
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);

$subjects = $db->prepare("
    SELECT sa.id as allocation_id, s.id, s.name, s.code, c.id as class_id, c.name as class_name, c.section
    FROM subject_allocations sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN classes c ON sa.class_id = c.id
    WHERE sa.teacher_id = ? AND sa.academic_session_id = ?
    ORDER BY c.name, s.name
");
$subjects->execute([$teacherId, $sessionId]);
$mySubjects = $subjects->fetchAll();

$totalSubjects = count($mySubjects);

$totalStudents = $db->prepare("
    SELECT COUNT(DISTINCT s.id) FROM students s
    JOIN subject_allocations sa ON s.class_id = sa.class_id
    WHERE sa.teacher_id = ? AND sa.academic_session_id = ? AND s.status = 'active'
");
$totalStudents->execute([$teacherId, $sessionId]);
$totalStudentsCount = (int)$totalStudents->fetchColumn();

$draftCount = 0;
$submittedCount = 0;
$publishedCount = 0;
foreach ($mySubjects as $subj) {
    $stmt = $db->prepare("SELECT status, COUNT(*) FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? GROUP BY status");
    $stmt->execute([$subj['class_id'], $subj['id'], $sessionId, $termId]);
    foreach ($stmt as $row) {
        if ($row['status'] === 'draft') $draftCount += (int)$row['COUNT(*)'];
        elseif ($row['status'] === 'submitted') $submittedCount += (int)$row['COUNT(*)'];
        elseif ($row['status'] === 'approved' || $row['status'] === 'published') $publishedCount += (int)$row['COUNT(*)'];
    }
}

$isClassTeacher = $db->prepare("SELECT COUNT(*) FROM classes WHERE class_teacher_id = ?");
$isClassTeacher->execute([$userId]);
$isClassTeacher = $isClassTeacher->fetchColumn() > 0;

$classTeacherClasses = [];
if ($isClassTeacher) {
    $stmt = $db->prepare("SELECT id, name, section FROM classes WHERE class_teacher_id = ?");
    $stmt->execute([$userId]);
    $classTeacherClasses = $stmt->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>Results Management</h4>
        <p class="text-muted small mb-0">
            <?= sanitizeInput($currentTerm['term_name'] ?? 'N/A') ?> -
            <?= sanitizeInput($currentTerm['session_name'] ?? 'N/A') ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isClassTeacher): ?>
        <a href="<?= BASE_URL ?>/teacher/results/psychomotor.php" class="btn btn-outline-gold btn-sm"><i class="fas fa-running me-1"></i>Psychomotor</a>
        <a href="<?= BASE_URL ?>/teacher/results/affective.php" class="btn btn-outline-gold btn-sm"><i class="fas fa-heart me-1"></i>Affective</a>
        <a href="<?= BASE_URL ?>/teacher/results/comments.php" class="btn btn-outline-gold btn-sm"><i class="fas fa-comment me-1"></i>Comments</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/teacher/results/submit.php" class="btn btn-primary btn-sm"><i class="fas fa-check-double me-1"></i>Submit Results</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <i class="fas fa-book stat-icon"></i>
            <div class="stat-value"><?= $totalSubjects ?></div>
            <div class="stat-label">Subjects Taught</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= $totalStudentsCount ?></div>
            <div class="stat-label">Students Assessed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-pen stat-icon"></i>
            <div class="stat-value"><?= $draftCount ?></div>
            <div class="stat-label">Draft</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $submittedCount ?></div>
            <div class="stat-label">Submitted</div>
        </div>
    </div>
</div>

<?php if (empty($mySubjects)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">You have not been assigned to any subjects this session.</p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-book me-2"></i>My Subjects</span>
        <span class="badge bg-primary"><?= $totalSubjects ?> subjects</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Code</th>
                    <th>Class</th>
                    <th>Students</th>
                    <th>Entered</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mySubjects as $subj):
                    $sc = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND status = 'active'");
                    $sc->execute([$subj['class_id']]);
                    $studentCount = (int)$sc->fetchColumn();

                    $ec = $db->prepare("SELECT COUNT(*) FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ?");
                    $ec->execute([$subj['class_id'], $subj['id'], $sessionId, $termId]);
                    $enteredCount = (int)$ec->fetchColumn();

                    $statusRow = $db->prepare("SELECT status FROM result_scores WHERE class_id = ? AND subject_id = ? AND session_id = ? AND term_id = ? LIMIT 1");
                    $statusRow->execute([$subj['class_id'], $subj['id'], $sessionId, $termId]);
                    $status = $statusRow->fetchColumn() ?: 'not_started';

                    $isPublished = isResultPublished($db, $subj['class_id'], $sessionId, $termId, $subj['id']);
                    if ($isPublished) $status = 'published';
                ?>
                <tr>
                    <td class="fw-medium"><?= sanitizeInput($subj['name']) ?></td>
                    <td><small><?= sanitizeInput($subj['code'] ?? '-') ?></small></td>
                    <td><span class="badge bg-secondary"><?= sanitizeInput($subj['class_name']) ?> <?= sanitizeInput($subj['section'] ?? '') ?></span></td>
                    <td><?= $studentCount ?></td>
                    <td><?= $enteredCount ?> / <?= $studentCount ?></td>
                    <td>
                        <?php if ($status === 'published'): ?>
                            <span class="badge bg-success">Published</span>
                        <?php elseif ($status === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif ($status === 'submitted'): ?>
                            <span class="badge bg-info text-dark">Submitted</span>
                        <?php elseif ($status === 'draft'): ?>
                            <span class="badge bg-warning text-dark">Draft</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Started</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/teacher/results/enter.php?class_id=<?= $subj['class_id'] ?>&subject_id=<?= $subj['id'] ?>" class="btn btn-sm btn-primary" title="Enter Scores">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/teacher/results/preview.php?class_id=<?= $subj['class_id'] ?>&subject_id=<?= $subj['id'] ?>" class="btn btn-sm btn-outline-primary" title="Preview">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($isClassTeacher && !empty($classTeacherClasses)): ?>
<div class="card mt-3">
    <div class="card-header"><i class="fas fa-chalkboard me-2"></i>My Classes (Class Teacher)</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classTeacherClasses as $class): ?>
                <tr>
                    <td class="fw-medium"><?= sanitizeInput($class['name']) ?> <?= sanitizeInput($class['section'] ?? '') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="<?= BASE_URL ?>/teacher/results/psychomotor.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-gold"><i class="fas fa-running me-1"></i>Psychomotor</a>
                            <a href="<?= BASE_URL ?>/teacher/results/affective.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-gold"><i class="fas fa-heart me-1"></i>Affective</a>
                            <a href="<?= BASE_URL ?>/teacher/results/comments.php?class_id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-gold"><i class="fas fa-comment me-1"></i>Comments</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
