<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Mark Attendance';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$error = '';

$classes = $db->prepare("SELECT c.id, c.name, c.section FROM classes c WHERE c.class_teacher_id = ?");
$classes->execute([$userId]);
$myClasses = $classes->fetchAll();

$selectedClass = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$selectedDate = sanitizeInput($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    $statuses = $_POST['status'] ?? [];
    
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO attendance (student_id, class_id, date, status, marked_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)");
        foreach ($studentIds as $sid) {
            $status = $statuses[$sid] ?? 'present';
            $stmt->execute([$sid, $selectedClass, $selectedDate, $status, $userId]);
        }
        $db->commit();
        $msg = 'Attendance saved successfully.';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error saving attendance.';
    }
}

$students = [];
$attendanceData = [];
if ($selectedClass) {
    $students = $db->prepare("SELECT s.id, u.first_name, u.last_name, s.admission_no FROM students s JOIN users u ON s.user_id = u.id WHERE s.class_id = ? AND s.status = 'active' ORDER BY u.first_name");
    $students->execute([$selectedClass]);
    $students = $students->fetchAll();

    $att = $db->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?");
    $att->execute([$selectedClass, $selectedDate]);
    foreach ($att->fetchAll() as $a) {
        $attendanceData[$a['student_id']] = $a['status'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-check-circle me-2"></i>Mark Attendance</h4>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Select Class</option>
                    <?php foreach ($myClasses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>>
                        <?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass && !empty($students)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fas fa-users me-2"></i>Students</span>
        <span class="text-muted small"><?= count($students) ?> students | <?= formatDate($selectedDate) ?></span>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
            <input type="hidden" name="date" value="<?= $selectedDate ?>">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Excused</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= sanitizeInput($s['admission_no']) ?></td>
                            <td><?= sanitizeInput($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <?php
                            $current = $attendanceData[$s['id']] ?? 'present';
                            $statuses = ['present', 'absent', 'late', 'excused'];
                            ?>
                            <?php foreach ($statuses as $st): ?>
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status[<?= $s['id'] ?>]" value="<?= $st ?>" id="s_<?= $s['id'] ?>_<?= $st ?>" <?= $current === $st ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <?php endforeach; ?>
                            <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="save_attendance" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Save Attendance
            </button>
        </form>
    </div>
</div>
<?php elseif ($selectedClass): ?>
<div class="alert alert-info">No active students found in this class.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
