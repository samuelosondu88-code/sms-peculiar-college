<?php
require_once __DIR__ . '/../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Class Timetable';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT p.id FROM parents p WHERE p.user_id = ?");
$stmt->execute([$userId]);
$parent = $stmt->fetch();

$children = [];
if ($parent) {
    $children = $db->prepare("SELECT s.id, u.first_name, u.last_name, c.name as class_name, c.section FROM student_parents sp JOIN students s ON sp.student_id = s.id JOIN users u ON s.user_id = u.id JOIN classes c ON s.class_id = c.id WHERE sp.parent_id = ?");
    $children->execute([$parent['id']]);
    $children = $children->fetchAll();
}

$studentId = (int)($_GET['student_id'] ?? (!empty($children) ? ($children[0]['id'] ?? 0) : 0));
$term = getCurrentTerm();
$days = ['monday','tuesday','wednesday','thursday','friday'];

$entries = [];
if ($studentId) {
    $stmt = $db->prepare("SELECT s.class_id FROM students s WHERE s.id = ?");
    $stmt->execute([$studentId]);
    $stu = $stmt->fetch();
    if ($stu) {
        $entries = $db->prepare("SELECT t.*, sub.name as subject_name, u.first_name, u.last_name FROM timetable t JOIN subjects sub ON t.subject_id = sub.id LEFT JOIN users u ON t.teacher_id = u.id WHERE t.class_id = ? AND t.term_id = ? ORDER BY FIELD(t.day, 'monday','tuesday','wednesday','thursday','friday'), t.time_start");
        $entries->execute([$stu['class_id'], $term['id'] ?? 0]);
        $entries = $entries->fetchAll();
    }
}

$grouped = [];
foreach ($entries as $e) { $grouped[$e['day']][] = $e; }

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>Class Timetable</h4>
</div>

<form method="GET" class="row g-3 mb-4">
    <?php if (!empty($children)): ?>
    <div class="col-md-4">
        <label class="form-label">Child</label>
        <select name="student_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($children as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $studentId === $c['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($c['first_name'] . ' ' . $c['last_name']) ?> (<?= sanitizeInput($c['class_name']) ?>)
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</form>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-primary">
            <tr><th style="width:100px">Time</th>
            <?php foreach ($days as $day): ?><th><?= ucfirst($day) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
            <?php for ($h = 7; $h <= 17; $h++): ?>
            <tr>
                <td><small><?= sprintf('%02d:00', $h) ?> - <?= sprintf('%02d:00', $h + 1) ?></small></td>
                <?php foreach ($days as $day): ?>
                <td>
                    <?php foreach ($grouped[$day] ?? [] as $e):
                        if ((int)substr($e['time_start'], 0, 2) === $h): ?>
                    <div class="p-2 mb-1 bg-light rounded border-start border-primary border-3">
                        <strong><?= sanitizeInput($e['subject_name']) ?></strong><br>
                        <small><?= sanitizeInput($e['first_name'] . ' ' . $e['last_name']) ?></small><br>
                        <small class="text-muted">Rm: <?= sanitizeInput($e['room'] ?? '-') ?></small>
                    </div>
                    <?php endif; endforeach; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
