<?php
$currentUser = [];
if (isset($_SESSION['user_id'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch() ?: [];
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-3">
    <button class="btn btn-outline-primary d-lg-none me-2" id="menu-toggle" type="button">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand fw-bold text-primary d-lg-none" href="<?= BASE_URL ?>/">
        <i class="fas fa-school me-2"></i><?= APP_SHORT_NAME ?>
    </a>
    <div class="ms-auto d-flex align-items-center">
        <div class="dropdown me-3">
            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                <h6 class="dropdown-header">Notifications</h6>
                <?php if ($notifCount > 0): ?>
                <a class="dropdown-item small" href="<?= BASE_URL ?>/messages.php"><?= $notifCount ?> unread message(s)</a>
                <?php else: ?>
                <span class="dropdown-item small text-muted">No new notifications</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="dropdown">
            <button class="btn dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                <div class="rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 14px; background: var(--gradient-gold); color: var(--primary); font-weight: 700;">
                    <?= strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="d-none d-md-block"><?= ($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text small text-muted"><?= ucfirst($currentUser['role'] ?? '') ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/auth/change-password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
