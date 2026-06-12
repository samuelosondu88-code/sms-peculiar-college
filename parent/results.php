<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'View Results';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT p.id FROM parents p WHERE p.user_id = ?");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

$children = [];
if ($parent) {
    $children = $db->prepare("SELECT s.id, u.first_name, u.last_name FROM student_parents sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id WHERE sp.parent_id = ?");
    $children->execute([$parent['id']]);
    $children = $children->fetchAll();
}

$studentId = (int)($_GET['student_id'] ?? (!empty($children) ? ($children[0]['id'] ?? 0) : 0));
$selectedExam = (int)($_GET['exam_id'] ?? 0);

$exams = [];
$results = [];
if ($studentId) {
    $exams = $db->prepare("SELECT DISTINCT e.id, e.name, t.term_name FROM exams e JOIN terms t ON e.term_id = t.id JOIN results r ON r.exam_id = e.id WHERE r.student_id = ? ORDER BY e.created_at DESC");
    $exams->execute([$studentId]);
    $exams = $exams->fetchAll();

    if (!$selectedExam && !empty($exams)) $selectedExam = $exams[0]['id'];

    if ($selectedExam) {
        $results = $db->prepare("SELECT r.score, r.grade, sub.name as subject_name FROM results r JOIN subjects sub ON r.subject_id = sub.id WHERE r.student_id = ? AND r.exam_id = ? ORDER BY sub.name");
        $results->execute([$studentId, $selectedExam]);
        $results = $results->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>View Results</h4>
</div>

<form method="GET" class="row g-3 mb-4">
    <?php if (!empty($children)): ?>
    <div class="col-md-4">
        <label class="form-label">Child</label>
        <select name="student_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $studentId === $c['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($c['first_name'] . ' ' . $c['last_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php if (!empty($exams)): ?>
    <div class="col-md-4">
        <label class="form-label">Exam</label>
        <select name="exam_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $selectedExam === $e['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($e['name'] . ' (' . $e['term_name'] . ')') ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</form>

<?php if (!empty($results)): ?>
<div class="card">
    <div class="card-header"><?= sanitizeInput($exams[0]['name'] ?? 'Results') ?></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Subject</th><th>Score</th><th>Grade</th></tr></thead>
            <tbody>
                <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= sanitizeInput($r['subject_name']) ?></td>
                    <td><strong><?= $r['score'] ?? '-' ?></strong></td>
                    <td><span class="badge bg-<?= $r['grade'] === 'A' ? 'success' : ($r['grade'] === 'F' ? 'danger' : 'primary') ?>"><?= $r['grade'] ?? '-' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($studentId): ?>
<div class="alert alert-info">No results available for this exam.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
