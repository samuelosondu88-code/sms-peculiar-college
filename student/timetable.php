<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Timetable';
$db = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT s.id, s.class_id FROM students s WHERE s.user_id = ?");
$stmt->execute([$userId]);
$student = $stmt->fetch();

$term = getCurrentTerm();
$days = ['monday','tuesday','wednesday','thursday','friday'];

$entries = [];
if ($student) {
    $stmt = $db->prepare("
        SELECT t.*, sub.name as subject_name, sub.code, u.first_name, u.last_name
        FROM timetable t
        JOIN subjects sub ON t.subject_id = sub.id
        LEFT JOIN users u ON t.teacher_id = u.id
        WHERE t.class_id = ? AND t.term_id = ?
        ORDER BY FIELD(t.day, 'monday','tuesday','wednesday','thursday','friday'), t.time_start
    ");
    $stmt->execute([$student['class_id'], $term['id'] ?? 0]);
    $entries = $stmt->fetchAll();
}

$grouped = [];
foreach ($entries as $e) {
    $grouped[$e['day']][] = $e;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>My Timetable</h4>
</div>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-primary">
            <tr>
                <th style="width:100px">Time</th>
                <?php foreach ($days as $day): ?>
                <th><?= ucfirst($day) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php for ($h = 7; $h <= 17; $h++): ?>
            <tr>
                <td><small><?= sprintf('%02d:00', $h) ?> - <?= sprintf('%02d:00', $h + 1) ?></small></td>
                <?php foreach ($days as $day): ?>
                <td>
                    <?php foreach ($grouped[$day] ?? [] as $e):
                        $startH = (int)substr($e['time_start'], 0, 2);
                        if ($startH === $h): ?>
                    <div class="p-2 mb-1 bg-light rounded border-start border-primary border-3">
                        <strong><?= sanitizeInput($e['subject_name']) ?></strong><br>
                        <small class="text-muted"><?= sanitizeInput($e['first_name'] . ' ' . $e['last_name']) ?></small><br>
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
