<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';

$pageTitle = 'Manage Users';
$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);

    if ($action === 'add_user') {
        $validator = new Validator();
        $data = [
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'role' => sanitizeInput($_POST['role'] ?? ''),
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ];

        $validator->required('first_name', $data['first_name'])
                  ->required('last_name', $data['last_name'])
                  ->required('email', $data['email'])->email('email', $data['email'])
                  ->required('username', $data['username'])
                  ->required('password', $data['password'])->minLength('password', $data['password'], 8)
                  ->unique('email', $data['email'], 'users', 'email')
                  ->unique('username', $data['username'], 'users', 'username');

        if ($validator->passes()) {
            $hash = generatePasswordHash($data['password']);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['username'], $data['email'], $hash, $data['role'], $data['first_name'], $data['last_name'], $data['phone']]);
            $userId = $db->lastInsertId();

            if ($data['role'] === 'teacher') {
                $employeeId = 'TCH-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO teachers (user_id, employee_id) VALUES (?, ?)");
                $stmt->execute([$userId, $employeeId]);
            } elseif ($data['role'] === 'student') {
                $admissionNo = 'STU-' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("INSERT INTO students (user_id, admission_no, class_id) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $admissionNo, $_POST['class_id'] ?? 0]);
            } elseif ($data['role'] === 'parent') {
                $stmt = $db->prepare("INSERT INTO parents (user_id) VALUES (?)");
                $stmt->execute([$userId]);
            }

            logActivity($_SESSION['user_id'], 'add_user', 'users', $userId);
            $success = 'User added successfully.';
        } else {
            $error = $validator->getFirstError();
        }
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = sanitizeInput($_POST['new_status'] ?? '');
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        logActivity($_SESSION['user_id'], 'toggle_user_status', 'users', $userId, '', $newStatus);
        redirect('/admin/users.php?msg=Status updated');
    }
}

$search = sanitizeInput($_GET['search'] ?? '');
$roleFilter = sanitizeInput($_GET['role'] ?? '');
$params = [];
$where = [];

if ($search) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($roleFilter) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$users = $db->prepare("SELECT id, first_name, last_name, email, role, status, phone, created_at FROM users u $whereClause ORDER BY u.created_at DESC LIMIT 50");
$users->execute($params);
$usersList = $users->fetchAll();

$classes = $db->query("SELECT id, CONCAT(name, ' (', section, ')') as display_name FROM classes WHERE academic_session_id = (SELECT id FROM academic_sessions WHERE is_current = 1 LIMIT 1)")->fetchAll();

$msg = sanitizeInput($_GET['msg'] ?? '');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-users me-2"></i>Manage Users</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus me-1"></i>Add User
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="parent" <?= $roleFilter === 'parent' ? 'selected' : '' ?>>Parent</option>
                    <option value="accountant" <?= $roleFilter === 'accountant' ? 'selected' : '' ?>>Accountant</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover datatable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): ?>
                    <tr>
                        <td><?= sanitizeInput($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= sanitizeInput($u['email']) ?></td>
                        <td><?= sanitizeInput($u['phone'] ?? '-') ?></td>
                        <td><?= getRoleBadge($u['role']) ?></td>
                        <td><?= getStatusBadge($u['status']) ?></td>
                        <td><?= formatDate($u['created_at']) ?></td>
                        <td>
                            <?php if ($u['status'] === 'active'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this user?')">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="new_status" value="suspended">
                                <button type="submit" name="action" value="toggle_status" class="btn btn-sm btn-outline-warning" title="Suspend">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="new_status" value="active">
                                <button type="submit" name="action" value="toggle_status" class="btn btn-sm btn-outline-success" title="Activate">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select</option>
                                <option value="admin">Admin</option>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                                <option value="parent">Parent</option>
                                <option value="accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Password (min 8 chars)</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="add_user">
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
