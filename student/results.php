<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Results';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT s.id FROM students s WHERE s.user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

$selectedExam = (int)($_GET['exam_id'] ?? 0);

$exams = [];
$results = [];
$totalScore = 0;
$totalSubjects = 0;

if ($student) {
    $exams = $db->prepare("SELECT DISTINCT e.id, e.name, e.term_id, t.term_name FROM exams e JOIN terms t ON e.term_id = t.id JOIN results r ON r.exam_id = e.id WHERE r.student_id = ? ORDER BY e.created_at DESC");
    $exams->execute([$student['id']]);
    $exams = $exams->fetchAll();

    if (!$selectedExam && !empty($exams)) {
        $selectedExam = $exams[0]['id'];
    }

    if ($selectedExam) {
        $results = $db->prepare("
            SELECT r.score, r.grade, sub.name as subject_name, sub.code, e.name as exam_name
            FROM results r
            JOIN subjects sub ON r.subject_id = sub.id
            JOIN exams e ON r.exam_id = e.id
            WHERE r.student_id = ? AND r.exam_id = ?
            ORDER BY sub.name
        ");
        $results->execute([$student['id'], $selectedExam]);
        $results = $results->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>My Results</h4>
</div>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-4">
        <label class="form-label">Select Examination</label>
        <select name="exam_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $selectedExam === $e['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($e['name'] . ' (' . $e['term_name'] . ')') ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if (!empty($results)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><?= sanitizeInput($results[0]['exam_name'] ?? 'Results') ?></span>
        <button class="btn btn-sm btn-outline-primary no-print" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Code</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r):
                        $totalScore += $r['score'];
                        $totalSubjects++;
                    ?>
                    <tr>
                        <td><?= sanitizeInput($r['subject_name']) ?></td>
                        <td><?= sanitizeInput($r['code']) ?></td>
                        <td><strong><?= $r['score'] ?? '-' ?></strong></td>
                        <td><span class="badge bg-<?= $r['grade'] === 'A' ? 'success' : ($r['grade'] === 'F' ? 'danger' : 'primary') ?>"><?= $r['grade'] ?? '-' ?></span></td>
                        <td><?= $r['score'] >= 70 ? 'Excellent' : ($r['score'] >= 60 ? 'Very Good' : ($r['score'] >= 50 ? 'Good' : ($r['score'] >= 40 ? 'Pass' : 'Fail'))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td><strong>Total</strong></td>
                        <td></td>
                        <td><strong><?= $totalScore ?></strong></td>
                        <td colspan="2"><strong>Average: <?= $totalSubjects > 0 ? number_format($totalScore / $totalSubjects, 1) : '-' ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">No results available yet.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
