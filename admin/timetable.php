<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Timetable Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_entry'])) {
    $classId = (int)($_POST['class_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $day = sanitizeInput($_POST['day'] ?? '');
    $timeStart = sanitizeInput($_POST['time_start'] ?? '');
    $timeEnd = sanitizeInput($_POST['time_end'] ?? '');
    $room = sanitizeInput($_POST['room'] ?? '');
    $termId = (int)($_POST['term_id'] ?? 0);

    if ($classId && $subjectId && $teacherId && $day && $timeStart && $timeEnd) {
        if (isset($_POST['entry_id']) && $_POST['entry_id']) {
            $stmt = $db->prepare("UPDATE timetable SET class_id=?, subject_id=?, teacher_id=?, day=?, time_start=?, time_end=?, room=? WHERE id=?");
            $stmt->execute([$classId, $subjectId, $teacherId, $day, $timeStart, $timeEnd, $room, $_POST['entry_id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO timetable (class_id, subject_id, teacher_id, day, time_start, time_end, room, term_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$classId, $subjectId, $teacherId, $day, $timeStart, $timeEnd, $room, $termId]);
        }
        $msg = 'Timetable entry saved.';
    }
}

$entries = $db->query("SELECT t.*, c.name as class_name, c.section, sub.name as subject_name, u.first_name, u.last_name, ter.term_name FROM timetable t JOIN classes c ON t.class_id = c.id JOIN subjects sub ON t.subject_id = sub.id JOIN users u ON t.teacher_id = u.id JOIN terms ter ON t.term_id = ter.id ORDER BY FIELD(t.day,'monday','tuesday','wednesday','thursday','friday','saturday'), t.time_start")->fetchAll();

$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$subjects = $db->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();
$teachers = $db->query("SELECT u.id, u.first_name, u.last_name FROM users u JOIN teachers t ON u.id = t.user_id WHERE u.status = 'active'")->fetchAll();
$terms = $db->query("SELECT t.id, t.term_name, ac.session_name FROM terms t JOIN academic_sessions ac ON t.session_id = ac.id WHERE ac.status = 'active'")->fetchAll();
$days = ['monday','tuesday','wednesday','thursday','friday','saturday'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>Timetable Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#entryModal"><i class="fas fa-plus me-1"></i>Add Entry</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 datatable">
                <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Day</th><th>Time</th><th>Room</th><th>Term</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= sanitizeInput($e['class_name'] . ' ' . ($e['section'] ?? '')) ?></td>
                        <td><?= sanitizeInput($e['subject_name']) ?></td>
                        <td><?= sanitizeInput($e['first_name'] . ' ' . $e['last_name']) ?></td>
                        <td><span class="badge bg-primary"><?= ucfirst($e['day']) ?></span></td>
                        <td><?= date('h:i A', strtotime($e['time_start'])) ?> - <?= date('h:i A', strtotime($e['time_end'])) ?></td>
                        <td><?= sanitizeInput($e['room'] ?? '-') ?></td>
                        <td><small><?= sanitizeInput($e['term_name']) ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editEntry(<?= $e['id'] ?>, <?= $e['class_id'] ?>, <?= $e['subject_id'] ?>, <?= $e['teacher_id'] ?>, '<?= $e['day'] ?>', '<?= $e['time_start'] ?>', '<?= $e['time_end'] ?>', '<?= addslashes($e['room'] ?? '') ?>', <?= $e['term_id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="entryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="entry_id" id="entry_id">
                <div class="modal-header"><h5 class="modal-title" id="entryModalTitle">Add Timetable Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Class</label><select name="class_id" id="entry_class" class="form-select" required><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Subject</label><select name="subject_id" id="entry_subject" class="form-select" required><?php foreach ($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= sanitizeInput($s['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Teacher</label><select name="teacher_id" id="entry_teacher" class="form-select" required><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= sanitizeInput($t['first_name'] . ' ' . $t['last_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Day</label><select name="day" id="entry_day" class="form-select" required><?php foreach ($days as $d): ?><option value="<?= $d ?>"><?= ucfirst($d) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="time_start" id="entry_start" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">End Time</label><input type="time" name="time_end" id="entry_end" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Room</label><input type="text" name="room" id="entry_room" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Term</label><select name="term_id" id="entry_term" class="form-select" required><?php foreach ($terms as $t): ?><option value="<?= $t['id'] ?>"><?= sanitizeInput($t['term_name'] . ' ' . $t['session_name']) ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_entry" value="1"><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function editEntry(id, classId, subjectId, teacherId, day, start, end, room, termId) {
    document.getElementById('entry_id').value = id;
    document.getElementById('entry_class').value = classId;
    document.getElementById('entry_subject').value = subjectId;
    document.getElementById('entry_teacher').value = teacherId;
    document.getElementById('entry_day').value = day;
    document.getElementById('entry_start').value = start.substring(0,5);
    document.getElementById('entry_end').value = end.substring(0,5);
    document.getElementById('entry_room').value = room;
    document.getElementById('entry_term').value = termId;
    document.getElementById('entryModalTitle').textContent = 'Edit Timetable Entry';
    new bootstrap.Modal(document.getElementById('entryModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
