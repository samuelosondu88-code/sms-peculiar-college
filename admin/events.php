<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Manage Events';
$db = getDB();
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_event'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $desc = sanitizeInput($_POST['description'] ?? '');
    $date = $_POST['event_date'] ?? '';
    $time = $_POST['event_time'] ?? null;
    $location = sanitizeInput($_POST['location'] ?? '');
    $type = $_POST['type'] ?? 'other';
    $target = $_POST['target_role'] ?? 'all';

    if (!$title || !$date) { $msg = 'Title and date are required.'; $msgType = 'danger'; }
    else {
        if ($id) {
            $db->prepare("UPDATE events SET title=?, description=?, event_date=?, event_time=?, location=?, type=?, target_role=? WHERE id=?")->execute([$title, $desc, $date, $time, $location, $type, $target, $id]);
            $msg = 'Event updated.';
        } else {
            $db->prepare("INSERT INTO events (title, description, event_date, event_time, location, type, target_role, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$title, $desc, $date, $time, $location, $type, $target, $_SESSION['user_id']]);
            $msg = 'Event created.';
        }
        logActivity($_SESSION['user_id'], $id ? 'update_event' : 'create_event', 'events', $id ?: (int)$db->lastInsertId());
    }
}

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $db->prepare("DELETE FROM events WHERE id = ?")->execute([$did]);
    logActivity($_SESSION['user_id'], 'delete_event', 'events', $did);
    redirect('/admin/events.php');
}

$editEvent = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $es = $db->prepare("SELECT * FROM events WHERE id = ?");
    $es->execute([$eid]);
    $editEvent = $es->fetch();
}

$events = $db->query("SELECT e.*, u.first_name, u.last_name FROM events e LEFT JOIN users u ON e.created_by = u.id ORDER BY e.event_date DESC LIMIT 50")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>Events</h4>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="clearForm()"><i class="fas fa-plus me-1"></i>New Event</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-dark"><tr><th>Date</th><th>Time</th><th>Title</th><th>Type</th><th>Location</th><th>Target</th><th>Created By</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($events as $e): ?>
            <tr>
                <td><?= date('d M Y', strtotime($e['event_date'])) ?></td>
                <td><?= $e['event_time'] ? date('H:i', strtotime($e['event_time'])) : '-' ?></td>
                <td><?= sanitizeInput($e['title']) ?></td>
                <td><span class="badge bg-info"><?= $e['type'] ?></span></td>
                <td><?= sanitizeInput($e['location'] ?? '-') ?></td>
                <td><?= $e['target_role'] ?></td>
                <td class="small"><?= sanitizeInput($e['first_name'] ?? '') . ' ' . sanitizeInput($e['last_name'] ?? '') ?></td>
                <td class="text-nowrap">
                    <button class="btn btn-sm btn-outline-primary" onclick="editEvent(<?= $e['id'] ?>, '<?= sanitizeInput($e['title']) ?>', '<?= sanitizeInput($e['description'] ?? '') ?>', '<?= $e['event_date'] ?>', '<?= $e['event_time'] ?? '' ?>', '<?= sanitizeInput($e['location'] ?? '') ?>', '<?= $e['type'] ?>', '<?= $e['target_role'] ?>')"><i class="fas fa-edit"></i></button>
                    <a href="?delete=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this event?')"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">New Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="eventId" value="0">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="eTitle" class="form-control" required maxlength="200">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="eDesc" class="form-control" rows="3"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Date *</label>
                        <input type="date" name="event_date" id="eDate" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Time</label>
                        <input type="time" name="event_time" id="eTime" class="form-control">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" id="eLoc" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select name="type" id="eType" class="form-select">
                            <option value="academic">Academic</option>
                            <option value="sports">Sports</option>
                            <option value="cultural">Cultural</option>
                            <option value="meeting">Meeting</option>
                            <option value="holiday">Holiday</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Visible To</label>
                    <select name="target_role" id="eTarget" class="form-select">
                        <option value="all">All Users</option>
                        <option value="admin">Admin Only</option>
                        <option value="teacher">Teachers</option>
                        <option value="student">Students</option>
                        <option value="parent">Parents</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_event" class="btn btn-primary">Save Event</button>
            </div>
        </form>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('eventId').value = '0';
    document.getElementById('eTitle').value = '';
    document.getElementById('eDesc').value = '';
    document.getElementById('eDate').value = '';
    document.getElementById('eTime').value = '';
    document.getElementById('eLoc').value = '';
    document.getElementById('eType').value = 'academic';
    document.getElementById('eTarget').value = 'all';
    document.getElementById('modalTitle').textContent = 'New Event';
}
function editEvent(id, title, desc, date, time, loc, type, target) {
    document.getElementById('eventId').value = id;
    document.getElementById('eTitle').value = title;
    document.getElementById('eDesc').value = desc;
    document.getElementById('eDate').value = date;
    document.getElementById('eTime').value = time;
    document.getElementById('eLoc').value = loc;
    document.getElementById('eType').value = type;
    document.getElementById('eTarget').value = target;
    document.getElementById('modalTitle').textContent = 'Edit Event';
    new bootstrap.Modal(document.getElementById('eventModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
