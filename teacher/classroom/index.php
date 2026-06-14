<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'My Classrooms';
$db = getDB();
$teacherId = getTeacherId();
$currentTerm = getCurrentTerm();
$sessionId = (int)($currentTerm['session_id'] ?? 0);
$termId = (int)($currentTerm['id'] ?? 0);
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);

    if ($name && $subjectId && $classId) {
        $code = strtoupper(substr(md5(uniqid()), 0, 8));
        $db->prepare("INSERT INTO virtual_classes (teacher_id, subject_id, class_id, session_id, term_id, name, description, code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$teacherId, $subjectId, $classId, $sessionId, $termId, $name, $description, $code]);
        $msg = 'Classroom created. Code: ' . $code;
    } else {
        $msg = 'All fields required.';
        $msgType = 'danger';
    }
}

if (isset($_GET['archive'])) {
    $db->prepare("UPDATE virtual_classes SET status = 'archived' WHERE id = ? AND teacher_id = ?")
        ->execute([(int)$_GET['archive'], $teacherId]);
    redirect('/teacher/classroom/index.php');
}

$classes = $db->prepare("
    SELECT vc.*, s.name as subject_name, s.code as subject_code, c.name as class_name, c.section,
        (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.virtual_class_id = vc.id AND ce.status = 'active') as student_count
    FROM virtual_classes vc
    JOIN subjects s ON vc.subject_id = s.id
    JOIN classes c ON vc.class_id = c.id
    WHERE vc.teacher_id = ? AND vc.status = 'active'
    ORDER BY vc.created_at DESC
");
$classes->execute([$teacherId]);
$myClasses = $classes->fetchAll();

$mySubjects = $db->prepare("
    SELECT sa.subject_id, s.name, s.code, sa.class_id, c.name as class_name, c.section
    FROM subject_allocations sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN classes c ON sa.class_id = c.id
    WHERE sa.teacher_id = ? AND sa.academic_session_id = ?
    ORDER BY c.name, s.name
");
$mySubjects->execute([$teacherId, $sessionId]);
$assignable = $mySubjects->fetchAll();

$uniqueClasses = [];
foreach ($assignable as $a) {
    $uniqueClasses[$a['class_id']] = ['id' => $a['class_id'], 'name' => $a['class_name'], 'section' => $a['section']];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>My Classrooms</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus me-1"></i>New Classroom</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php if (empty($myClasses)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-school fa-4x text-muted mb-3"></i>
        <p class="text-muted mb-2">You have no active classrooms.</p>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal"><i class="fas fa-plus me-1"></i>Create One</button>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($myClasses as $vc): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5 class="fw-bold mb-1"><?= sanitizeInput($vc['name']) ?></h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="view.php?id=<?= $vc['id'] ?>"><i class="fas fa-eye me-2"></i>Open</a></li>
                            <li><a class="dropdown-item" href="?archive=<?= $vc['id'] ?>" onclick="return confirm('Archive this classroom?')"><i class="fas fa-archive me-2"></i>Archive</a></li>
                        </ul>
                    </div>
                </div>
                <p class="text-muted small mb-2"><?= sanitizeInput($vc['subject_name']) ?> | <?= sanitizeInput($vc['class_name'] . ' ' . $vc['section']) ?></p>
                <span class="badge bg-info me-1"><?= $vc['student_count'] ?> Students</span>
                <span class="badge bg-secondary">Code: <?= $vc['code'] ?></span>
                <?php if ($vc['description']): ?>
                <p class="mt-2 small text-muted"><?= sanitizeInput($vc['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="view.php?id=<?= $vc['id'] ?>" class="btn btn-primary btn-sm w-100"><i class="fas fa-door-open me-1"></i>Enter Classroom</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">New Classroom</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Classroom Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., JSS1A Mathematics">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Optional description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" required>
                            <option value="">Select Class</option>
                            <?php foreach ($uniqueClasses as $uc): ?>
                            <option value="<?= $uc['id'] ?>"><?= sanitizeInput($uc['name'] . ' ' . $uc['section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($assignable as $a): ?>
                            <option value="<?= $a['subject_id'] ?>"><?= sanitizeInput($a['name']) ?> (<?= sanitizeInput($a['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="create_class" value="1">
                    <button type="submit" class="btn btn-primary">Create Classroom</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
