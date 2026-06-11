<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Timetable';
$db = getDB();
$userId = $_SESSION['user_id'];

$term = getCurrentTerm();
$days = ['monday','tuesday','wednesday','thursday','friday','saturday'];

$stmt = $db->prepare("
    SELECT t.*, sub.name as subject_name, sub.code, c.name as class_name, c.section
    FROM timetable t
    JOIN subjects sub ON t.subject_id = sub.id
    JOIN classes c ON t.class_id = c.id
    WHERE t.teacher_id = ? AND t.term_id = ?
    ORDER BY FIELD(t.day, 'monday','tuesday','wednesday','thursday','friday','saturday'), t.time_start
");
$stmt->execute([$userId, $term['id'] ?? 0]);
$entries = $stmt->fetchAll();

$grouped = [];
foreach ($entries as $e) {
    $grouped[$e['day']][] = $e;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>My Timetable</h4>
    <span class="text-muted"><?= sanitizeInput($term['term_name'] ?? '') ?> - <?= sanitizeInput($term['session_name'] ?? '') ?></span>
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
            <?php for ($h = 7; $h <= 17; $h++):
                $timeSlot = sprintf('%02d:00', $h);
                $hasEntries = false;
                foreach ($days as $day) {
                    foreach ($grouped[$day] ?? [] as $e) {
                        $startH = (int)substr($e['time_start'], 0, 2);
                        if ($startH === $h) { $hasEntries = true; break 2; }
                    }
                }
                if (!$hasEntries) continue;
            ?>
            <tr>
                <td><small><?= sprintf('%02d:00', $h) ?> - <?= sprintf('%02d:00', $h + 1) ?></small></td>
                <?php foreach ($days as $day): ?>
                <td>
                    <?php foreach ($grouped[$day] ?? [] as $e):
                        $startH = (int)substr($e['time_start'], 0, 2);
                        if ($startH === $h): ?>
                    <div class="p-2 mb-1 bg-light rounded border-start border-primary border-3">
                        <strong><?= sanitizeInput($e['subject_name']) ?></strong><br>
                        <small><?= sanitizeInput($e['class_name'] . ' ' . ($e['section'] ?? '')) ?></small><br>
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
