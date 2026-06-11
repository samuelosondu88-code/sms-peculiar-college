<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Transport Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_route'])) {
    $routeName = sanitizeInput($_POST['route_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $fee = (float)($_POST['fee'] ?? 0);
    $driverName = sanitizeInput($_POST['driver_name'] ?? '');
    $driverPhone = sanitizeInput($_POST['driver_phone'] ?? '');
    $vehicleNo = sanitizeInput($_POST['vehicle_no'] ?? '');

    if ($routeName) {
        $stmt = $db->prepare("INSERT INTO transport_routes (route_name, description, fee, driver_name, driver_phone, vehicle_no) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$routeName, $description, $fee, $driverName, $driverPhone, $vehicleNo]);
        $msg = 'Route added.';
    }
}

$routes = $db->query("SELECT * FROM transport_routes ORDER BY route_name")->fetchAll();
$assignments = $db->query("SELECT st.*, tr.route_name, u.first_name, u.last_name FROM student_transport st JOIN transport_routes tr ON st.route_id = tr.id JOIN students s ON st.student_id = s.id JOIN users u ON s.user_id = u.id WHERE st.status = 'active'")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-bus me-2"></i>Transport Management</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#routeModal"><i class="fas fa-plus me-1"></i>Add Route</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Transport Routes</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Route</th><th>Fee</th><th>Driver</th><th>Vehicle</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($routes as $r): ?>
                        <tr>
                            <td><strong><?= sanitizeInput($r['route_name']) ?></strong></td>
                            <td><?= formatCurrency($r['fee']) ?></td>
                            <td><?= sanitizeInput($r['driver_name'] ?: '-') ?></td>
                            <td><?= sanitizeInput($r['vehicle_no'] ?? '-') ?></td>
                            <td><?= getStatusBadge($r['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($routes)): ?><tr><td colspan="5" class="text-center text-muted py-3">No routes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Active Assignments</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Student</th><th>Route</th></tr></thead>
                    <tbody>
                        <?php foreach ($assignments as $a): ?>
                        <tr><td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td><td><?= sanitizeInput($a['route_name']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($assignments)): ?><tr><td colspan="2" class="text-center text-muted py-3">No assignments.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Add Transport Route</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Route Name *</label><input type="text" name="route_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Fee (₦)</label><input type="number" name="fee" class="form-control" step="0.01"></div>
                        <div class="col-md-6"><label class="form-label">Vehicle No.</label><input type="text" name="vehicle_no" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Driver Name</label><input type="text" name="driver_name" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Driver Phone</label><input type="tel" name="driver_phone" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_route" value="1"><button type="submit" class="btn btn-primary">Add Route</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
