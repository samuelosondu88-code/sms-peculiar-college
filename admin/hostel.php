<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Hostel Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hostel'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $fee = (float)($_POST['fee'] ?? 0);

    if ($name && $type && $capacity) {
        $stmt = $db->prepare("INSERT INTO hostels (name, type, capacity, fee) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $type, $capacity, $fee]);
        $msg = 'Hostel added.';
    }
}

$hostels = $db->query("SELECT h.*, u.first_name, u.last_name FROM hostels h LEFT JOIN users u ON h.warden_id = u.id ORDER BY h.name")->fetchAll();
$allocations = $db->query("SELECT ha.*, h.name as hostel_name, hr.room_no, u.first_name, u.last_name FROM hostel_allocations ha JOIN hostel_rooms hr ON ha.room_id = hr.id JOIN hostels h ON hr.hostel_id = h.id JOIN students s ON ha.student_id = s.id JOIN users u ON s.user_id = u.id WHERE ha.status = 'active'")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-bed me-2"></i>Hostel Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hostelModal"><i class="fas fa-plus me-1"></i>Add Hostel</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($hostels as $h): ?>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h5 class="fw-bold"><?= sanitizeInput($h['name']) ?></h5>
                    <span class="badge bg-<?= $h['type'] === 'boys' ? 'primary' : ($h['type'] === 'girls' ? 'danger' : 'info') ?>"><?= ucfirst($h['type']) ?></span>
                </div>
                <p class="mb-1"><small>Capacity: <?= $h['capacity'] ?> | Occupied: <?= $h['occupied'] ?></small></p>
                <p><small>Fee: <?= formatCurrency($h['fee']) ?></small></p>
                <div class="progress" style="height:6px">
                    <div class="progress-bar bg-primary" style="width: <?= $h['capacity'] > 0 ? ($h['occupied'] / $h['capacity']) * 100 : 0 ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">Active Allocations</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Student</th><th>Hostel</th><th>Room</th><th>Since</th></tr></thead>
            <tbody>
                <?php foreach ($allocations as $a): ?>
                <tr><td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td><td><?= sanitizeInput($a['hostel_name']) ?></td><td><?= sanitizeInput($a['room_no']) ?></td><td><?= formatDate($a['start_date']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($allocations)): ?><tr><td colspan="4" class="text-center text-muted py-3">No allocations.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="hostelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Add Hostel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Hostel Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Type</label><select name="type" class="form-select" required><option value="boys">Boys</option><option value="girls">Girls</option><option value="mixed">Mixed</option></select></div>
                        <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" name="capacity" class="form-control" min="1" required></div>
                        <div class="col-md-6"><label class="form-label">Fee (₦)</label><input type="number" name="fee" class="form-control" step="0.01"></div>
                    </div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_hostel" value="1"><button type="submit" class="btn btn-primary">Add</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
