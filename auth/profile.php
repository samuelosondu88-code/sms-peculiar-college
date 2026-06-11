<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'My Profile';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('/auth/logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');

    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$firstName, $lastName, $phone, $address, $userId]);
    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
    $msg = 'Profile updated successfully.';

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-user me-2"></i>My Profile</h4>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <div class="avatar-circle bg-primary mx-auto mb-3" style="width:80px;height:80px;font-size:32px;">
                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold"><?= sanitizeInput($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                <p><?= getRoleBadge($user['role']) ?></p>
                <p class="text-muted small"><?= sanitizeInput($user['email']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Edit Profile</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= sanitizeInput($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= sanitizeInput($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= sanitizeInput($user['email']) ?>" disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= sanitizeInput($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= sanitizeInput($user['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-1"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Account Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4"><strong>Username:</strong> <?= sanitizeInput($user['username']) ?></div>
                    <div class="col-md-4"><strong>Role:</strong> <?= ucfirst($user['role']) ?></div>
                    <div class="col-md-4"><strong>Status:</strong> <?= getStatusBadge($user['status']) ?></div>
                    <div class="col-md-4 mt-2"><strong>Last Login:</strong> <?= $user['last_login'] ? formatDate($user['last_login']) : 'Never' ?></div>
                    <div class="col-md-4 mt-2"><strong>Joined:</strong> <?= formatDate($user['created_at']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
