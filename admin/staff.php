<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Staff Directory';
$db = getDB();

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $department_id = (int)($_POST['department_id'] ?? 0) ?: null;
    $qualification = sanitizeInput($_POST['qualification'] ?? '');
    $specialization = sanitizeInput($_POST['specialization'] ?? '');
    $employment_type = $_POST['employment_type'] ?? 'full-time';
    $salary = $_POST['salary'] ?? null;

    if (!$first_name || !$last_name || !$email) {
        $msg = 'Name and email are required.'; $msgType = 'danger';
    } else {
        $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, status=? WHERE id=?")->execute([$first_name, $last_name, $email, $phone, $status, $userId]);
        $tStmt = $db->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $tStmt->execute([$userId]);
        if ($tStmt->fetchColumn()) {
            $db->prepare("UPDATE teachers SET department_id=?, qualification=?, specialization=?, employment_type=?, salary=? WHERE user_id=?")->execute([$department_id, $qualification, $specialization, $employment_type, $salary, $userId]);
        }
        $msg = 'Staff record updated.';
        logActivity($_SESSION['user_id'], 'update_staff', 'users', $userId);
        logger('auth')->info('Staff record updated', ['by_user_id' => (int)$_SESSION['user_id'], 'user_id' => $userId, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}

$roleFilter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$deptFilter = (int)($_GET['department'] ?? 0);

$sql = "SELECT u.*, t.employee_id, t.qualification, t.department_id, t.specialization, t.employment_type, t.salary, t.date_hired, d.name AS dept_name
        FROM users u
        LEFT JOIN teachers t ON u.id = t.user_id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE u.role IN ('admin','teacher','accountant')";
$params = [];

if ($roleFilter) {
    $sql .= " AND u.role = ?"; $params[] = $roleFilter;
}
if ($search) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR t.employee_id LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($deptFilter) {
    $sql .= " AND t.department_id = ?"; $params[] = $deptFilter;
}
$sql .= " ORDER BY u.first_name ASC";

$staff = $db->prepare($sql);
$staff->execute($params);
$staff = $staff->fetchAll();

$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-users me-2"></i>Staff Directory</h4>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, email, employee ID..." value="<?= sanitizeInput($search) ?>">
    </div>
    <div class="col-md-3">
        <select name="role" class="form-select form-select-sm">
            <option value="">All Roles</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
            <option value="accountant" <?= $roleFilter === 'accountant' ? 'selected' : '' ?>>Accountant</option>
        </select>
    </div>
    <div class="col-md-3">
        <select name="department" class="form-select form-select-sm">
            <option value="0">All Departments</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptFilter === (int)$d['id'] ? 'selected' : '' ?>><?= sanitizeInput($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
    </div>
</form>

<div class="row">
    <?php foreach ($staff as $s): ?>
    <div class="col-md-6 col-lg-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="rounded-circle bg-<?= $s['role'] === 'admin' ? 'danger' : ($s['role'] === 'teacher' ? 'primary' : 'success') ?> text-white d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.2rem;font-weight:bold;">
                        <?= strtoupper(substr($s['first_name'], 0, 1)) . strtoupper(substr($s['last_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h6 class="mb-0"><?= sanitizeInput($s['first_name'] . ' ' . $s['last_name']) ?></h6>
                        <small class="text-muted">
                            <span class="badge bg-<?= $s['role'] === 'admin' ? 'danger' : ($s['role'] === 'teacher' ? 'primary' : 'success') ?> me-1"><?= $s['role'] ?></span>
                            <?= $s['employee_id'] ? '<span class="text-muted">' . sanitizeInput($s['employee_id']) . '</span>' : '' ?>
                        </small>
                    </div>
                </div>
                <div class="small">
                    <div><i class="fas fa-envelope me-1 text-muted"></i><?= sanitizeInput($s['email']) ?></div>
                    <div><i class="fas fa-phone me-1 text-muted"></i><?= sanitizeInput($s['phone'] ?? '-') ?></div>
                    <div><i class="fas fa-building me-1 text-muted"></i><?= sanitizeInput($s['dept_name'] ?? 'No Department') ?></div>
                    <?php if ($s['employment_type']): ?><div><i class="fas fa-clock me-1 text-muted"></i><?= ucfirst(str_replace('-', ' ', $s['employment_type'])) ?></div><?php endif; ?>
                    <div class="mt-1">
                        <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $s['status'] ?></span>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary mt-2 w-100" onclick="editStaff(<?= $s['id'] ?>, '<?= sanitizeInput($s['first_name']) ?>', '<?= sanitizeInput($s['last_name']) ?>', '<?= sanitizeInput($s['email']) ?>', '<?= sanitizeInput($s['phone'] ?? '') ?>', '<?= $s['status'] ?>', <?= (int)($s['department_id'] ?? 0) ?>, '<?= sanitizeInput($s['qualification'] ?? '') ?>', '<?= sanitizeInput($s['specialization'] ?? '') ?>', '<?= $s['employment_type'] ?? 'full-time' ?>', '<?= $s['salary'] ?? '' ?>')">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!count($staff)): ?><div class="col-12 text-center text-muted py-4">No staff found.</div><?php endif; ?>
</div>

<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit Staff Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="user_id" id="staffUserId" value="0">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="sFname" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="sLname" class="form-control" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="sEmail" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="sPhone" class="form-control">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" id="sStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="sDept" class="form-select">
                            <option value="0">None</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= sanitizeInput($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employment Type</label>
                        <select name="employment_type" id="sEmpType" class="form-select">
                            <option value="full-time">Full Time</option>
                            <option value="part-time">Part Time</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" id="sQual" class="form-control" placeholder="e.g. B.Ed, MSc">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" id="sSpec" class="form-control" placeholder="e.g. Mathematics">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Salary (₦)</label>
                    <input type="number" name="salary" id="sSalary" class="form-control" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_staff" class="btn btn-primary">Update Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStaff(id, fname, lname, email, phone, status, deptId, qual, spec, empType, salary) {
    document.getElementById('staffUserId').value = id;
    document.getElementById('sFname').value = fname;
    document.getElementById('sLname').value = lname;
    document.getElementById('sEmail').value = email;
    document.getElementById('sPhone').value = phone;
    document.getElementById('sStatus').value = status;
    document.getElementById('sDept').value = deptId;
    document.getElementById('sQual').value = qual;
    document.getElementById('sSpec').value = spec;
    document.getElementById('sEmpType').value = empType;
    document.getElementById('sSalary').value = salary;
    new bootstrap.Modal(document.getElementById('staffModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
