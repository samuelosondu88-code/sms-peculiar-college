<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Exam Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_exam'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $termId = (int)($_POST['term_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $startDate = sanitizeInput($_POST['start_date'] ?? '');
    $endDate = sanitizeInput($_POST['end_date'] ?? '');
    $maxScore = (float)($_POST['max_score'] ?? 100);

    if ($name && $termId) {
        $stmt = $db->prepare("INSERT INTO exams (name, term_id, class_id, start_date, end_date, max_score, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $termId, $classId ?: null, $startDate, $endDate, $maxScore, $_SESSION['user_id']]);
        $msg = 'Exam created.';
    }
}

$exams = $db->query("SELECT e.*, t.term_name, c.name as class_name FROM exams e LEFT JOIN terms t ON e.term_id = t.id LEFT JOIN classes c ON e.class_id = c.id ORDER BY e.created_at DESC")->fetchAll();
$terms = $db->query("SELECT t.id, t.term_name, ac.session_name FROM terms t JOIN academic_sessions ac ON t.session_id = ac.id WHERE ac.status = 'active'")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>Exam Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#examModal"><i class="fas fa-plus me-1"></i>Create Exam</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Exam Name</th><th>Term</th><th>Class</th><th>Period</th><th>Max Score</th><th>Published</th></tr></thead>
                <tbody>
                    <?php foreach ($exams as $e): ?>
                    <tr>
                        <td><strong><?= sanitizeInput($e['name']) ?></strong></td>
                        <td><?= sanitizeInput($e['term_name'] ?? '-') ?></td>
                        <td><?= sanitizeInput($e['class_name'] ?? 'All') ?></td>
                        <td><small><?= $e['start_date'] ? formatDate($e['start_date']) : '' ?> - <?= $e['end_date'] ? formatDate($e['end_date']) : '' ?></small></td>
                        <td><?= $e['max_score'] ?></td>
                        <td><?= $e['is_published'] ? getStatusBadge('active') : getStatusBadge('inactive') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($exams)): ?><tr><td colspan="6" class="text-center text-muted py-3">No exams created.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Create Exam</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Exam Name *</label><input type="text" name="name" class="form-control" required placeholder="e.g., First Term Examination"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Term</label><select name="term_id" class="form-select" required><?php foreach ($terms as $t): ?><option value="<?= $t['id'] ?>"><?= sanitizeInput($t['term_name'] . ' ' . $t['session_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Class (optional)</label><select name="class_id" class="form-select"><option value="">All Classes</option><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Max Score</label><input type="number" name="max_score" class="form-control" value="100" step="0.01"></div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_exam" value="1"><button type="submit" class="btn btn-primary">Create</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
