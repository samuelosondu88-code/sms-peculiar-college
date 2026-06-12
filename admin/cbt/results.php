<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'CBT Results';
$db = getDB();

$exam_id = (int)($_GET['exam_id'] ?? 0);
$student_search = trim($_GET['student'] ?? '');
$export = $_GET['export'] ?? '';

$exams = $db->query("SELECT e.id, e.title, s.name as subject_name FROM cbt_exams e JOIN cbt_subjects s ON e.subject_id = s.id ORDER BY e.title")->fetchAll();

// Build query
$where = '';
$params = [];
if ($exam_id) {
    $where .= ' AND ca.exam_id = ?';
    $params[] = $exam_id;
}
if ($student_search) {
    $where .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $like = "%$student_search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT ca.*, ce.title as exam_title, ce.pass_score, s.name as subject_name,
               u.first_name, u.last_name, u.email, u.avatar
        FROM cbt_attempts ca
        JOIN cbt_exams ce ON ca.exam_id = ce.id
        JOIN cbt_subjects s ON ce.subject_id = s.id
        JOIN students st ON ca.student_id = st.id
        JOIN users u ON st.user_id = u.id
        WHERE 1=1 $where
        ORDER BY ca.completed_at DESC";

$results = $db->prepare($sql);
$results->execute($params);
$attempts = $results->fetchAll();

// Export CSV
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cbt-results.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['Student Name', 'Email', 'Exam', 'Subject', 'Score', 'Correct', 'Wrong', 'Unanswered', 'Total Questions', 'Pass Score', 'Status', 'Time Spent (s)', 'Completed At']);
    foreach ($attempts as $a) {
        fputcsv($fh, [
            $a['first_name'] . ' ' . $a['last_name'],
            $a['email'],
            $a['exam_title'],
            $a['subject_name'],
            $a['score'],
            $a['correct_count'],
            $a['wrong_count'],
            $a['unanswer_count'],
            $a['total_questions'],
            $a['pass_score'],
            $a['status'],
            $a['time_spent_seconds'],
            $a['completed_at'] ?? '-',
        ]);
    }
    fclose($fh);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">CBT Results</h4>
        <p class="text-muted small mb-0">View and export student exam results</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/cbt/results.php?<?= ($exam_id ? "exam_id=$exam_id&" : '') . ($student_search ? "student=$student_search&" : '') ?>export=csv" class="btn btn-success">
            <i class="fas fa-download me-1"></i>Export CSV
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Exam</label>
                <select name="exam_id" class="form-select form-select-sm">
                    <option value="">All Exams</option>
                    <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $exam_id === $e['id'] ? 'selected' : '' ?>><?= sanitizeInput($e['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label">Search Student</label>
                <input type="text" name="student" class="form-control form-control-sm" value="<?= sanitizeInput($student_search) ?>" placeholder="Name or email...">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/admin/cbt/results.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-chart-bar me-2"></i><?= count($attempts) ?> Result(s) Found</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Correct</th>
                    <th>Wrong</th>
                    <th>Status</th>
                    <th>Time</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $a): ?>
                <?php $passed = $a['score'] >= $a['pass_score']; ?>
                <tr>
                    <td>
                        <strong><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></strong>
                        <br><small class="text-muted"><?= sanitizeInput($a['email']) ?></small>
                    </td>
                    <td><?= sanitizeInput($a['exam_title']) ?></td>
                    <td>
                        <span class="badge <?= $passed ? 'bg-success' : 'bg-danger' ?>" style="font-size: 14px;"><?= $a['score'] ?>%</span>
                    </td>
                    <td><span class="text-success"><?= $a['correct_count'] ?>/<?= $a['total_questions'] ?></span></td>
                    <td><span class="text-danger"><?= $a['wrong_count'] ?></span></td>
                    <td><?= getStatusBadge($a['status']) ?></td>
                    <td><?= gmdate('i:s', $a['time_spent_seconds']) ?></td>
                    <td><?= $a['completed_at'] ? formatDate($a['completed_at']) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($attempts)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No results found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
