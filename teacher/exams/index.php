<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'My Examinations';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$currentTerm = getCurrentTerm();
$termId = $currentTerm['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_publish'])) {
    $examId = (int)$_POST['exam_id'];
    $stmt = $db->prepare("UPDATE teacher_exams SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$examId, $userId]);
    $msg = 'Exam status updated.';
}

$search = sanitizeInput($_GET['search'] ?? '');
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterType = sanitizeInput($_GET['type'] ?? '');
$filterStatus = sanitizeInput($_GET['status'] ?? '');

$sql = "SELECT te.*, sub.name as subject_name, c.name as class_name, c.section,
        (SELECT COUNT(*) FROM teacher_exam_questions teq WHERE teq.exam_id = te.id) as question_count,
        (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = te.id) as attempt_count
        FROM teacher_exams te
        JOIN subjects sub ON te.subject_id = sub.id
        JOIN classes c ON te.class_id = c.id
        WHERE te.teacher_id = ?";
$params = [$userId];

if ($search) { $sql .= " AND te.title LIKE ?"; $params[] = "%$search%"; }
if ($filterSubject) { $sql .= " AND te.subject_id = ?"; $params[] = $filterSubject; }
if ($filterClass) { $sql .= " AND te.class_id = ?"; $params[] = $filterClass; }
if ($filterType) { $sql .= " AND te.exam_type = ?"; $params[] = $filterType; }
if ($filterStatus) { $sql .= " AND te.status = ?"; $params[] = $filterStatus; }
$sql .= " ORDER BY te.created_at DESC";

$exams = $db->prepare($sql);
$exams->execute($params);
$examList = $exams->fetchAll();

$subjects = $db->query("SELECT DISTINCT s.id, s.name FROM subjects s WHERE s.teacher_id = $userId")->fetchAll();
$classes = $db->query("SELECT DISTINCT c.id, c.name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = $userId")->fetchAll();

$totalExams = count($examList);
$publishedCount = $db->prepare("SELECT COUNT(*) FROM teacher_exams WHERE teacher_id = ? AND is_published = 1");
$publishedCount->execute([$userId]); $pub = (int)$publishedCount->fetchColumn();
$draftCount = $totalExams - $pub;

$totalStudents = $db->prepare("SELECT COUNT(DISTINCT student_id) FROM exam_attempts ea JOIN teacher_exams te ON ea.exam_id = te.id WHERE te.teacher_id = ?");
$totalStudents->execute([$userId]); $studCount = (int)$totalStudents->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>My Examinations</h4>
        <p class="text-muted small mb-0">Create and manage examinations for your classes</p>
    </div>
    <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Examination</a>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-value"><?= $totalExams ?></div>
            <div class="stat-label">Total Exams</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $pub ?></div>
            <div class="stat-label">Published</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value"><?= $draftCount ?></div>
            <div class="stat-label">Drafts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= $studCount ?></div>
            <div class="stat-label">Students Assessed</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Exam title..." value="<?= sanitizeInput($search) ?>">
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
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['CA','Test','Mid-Term','Examination','Mock Exam','CBT'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach (['draft','published','in_progress','completed','graded'] as $st): ?>
                    <option value="<?= $st ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
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
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Type</th>
                        <th>Questions</th>
                        <th>Marks</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($examList as $ex): ?>
                    <tr>
                        <td><a href="results.php?exam_id=<?= $ex['id'] ?>" class="fw-semibold"><?= sanitizeInput($ex['title']) ?></a></td>
                        <td><?= sanitizeInput($ex['subject_name']) ?></td>
                        <td><?= sanitizeInput($ex['class_name'] . ' ' . $ex['section']) ?></td>
                        <td><span class="badge bg-secondary"><?= $ex['exam_type'] ?></span></td>
                        <td><?= $ex['question_count'] ?></td>
                        <td><?= $ex['total_marks'] ?></td>
                        <td><?= $ex['duration_minutes'] ?> min</td>
                        <td>
                            <?php $b = ['draft'=>'secondary','published'=>'success','in_progress'=>'warning','completed'=>'info','graded'=>'primary']; ?>
                            <span class="badge bg-<?= $b[$ex['status']] ?? 'secondary' ?>"><?= ucfirst($ex['status']) ?></span>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="set-questions.php?exam_id=<?= $ex['id'] ?>"><i class="fas fa-list me-2"></i>Questions</a></li>
                                    <li><a class="dropdown-item" href="create.php?id=<?= $ex['id'] ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="results.php?exam_id=<?= $ex['id'] ?>"><i class="fas fa-chart-bar me-2"></i>Results</a></li>
                                    <li><a class="dropdown-item" href="security-settings.php?exam_id=<?= $ex['id'] ?>"><i class="fas fa-shield-alt me-2"></i>Security</a></li>
                                    <li><a class="dropdown-item" href="monitor.php?exam_id=<?= $ex['id'] ?>"><i class="fas fa-tv me-2"></i>Live Monitor</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="exam_id" value="<?= $ex['id'] ?>">
                                            <button type="submit" name="toggle_publish" class="dropdown-item">
                                                <i class="fas fa-<?= $ex['is_published'] ? 'eye-slash' : 'eye' ?> me-2"></i>
                                                <?= $ex['is_published'] ? 'Unpublish' : 'Publish' ?>
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($examList)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No examinations yet. <a href="create.php">Create one now</a>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
