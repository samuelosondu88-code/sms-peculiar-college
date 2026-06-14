<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Admin Dashboard';
$db = getDB();

$stats = [
    'students' => getTotalStudents(),
    'teachers' => getTotalTeachers(),
    'classes' => getTotalClasses(),
    'users' => getTotalUsers(),
];

$stmt = $db->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$stats['pending_payments'] = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM applications WHERE status = 'submitted' OR status = 'reviewing'");
$stats['pending_applications'] = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(DISTINCT student_id) FROM result_scores");
$stats['students_with_results'] = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM result_scores WHERE status = 'submitted'");
$stats['pending_approvals'] = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM result_scores WHERE status = 'published'");
$stats['published_results'] = (int)$stmt->fetchColumn();

$recentUsers = $db->query("SELECT id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

$recentPayments = $db->query("
    SELECT p.amount_paid, p.payment_method, p.status, p.created_at, u.first_name, u.last_name
    FROM payments p
    JOIN fees f ON p.fee_id = f.id
    JOIN students s ON f.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();

$recentApplications = $db->query("SELECT id, first_name, last_name, email, class_applying, status, submitted_at FROM applications WHERE status != 'draft' ORDER BY submitted_at DESC LIMIT 5")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Admin Dashboard</h4>
        <p class="text-muted small mb-0">Welcome back, <?= $_SESSION['user_name'] ?>!</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/applications.php" class="btn btn-warning me-2">
            <i class="fas fa-file-signature me-1"></i>Pending: <?= $stats['pending_applications'] ?>
        </a>
        <a href="<?= BASE_URL ?>/admin/users.php?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Add User
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= $stats['students'] ?></div>
            <div class="stat-label">Active Students</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <i class="fas fa-chalkboard-teacher stat-icon"></i>
            <div class="stat-value"><?= $stats['teachers'] ?></div>
            <div class="stat-label">Teachers</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <i class="fas fa-school stat-icon"></i>
            <div class="stat-value"><?= $stats['classes'] ?></div>
            <div class="stat-label">Classes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-credit-card stat-icon"></i>
            <div class="stat-value"><?= $stats['pending_payments'] ?></div>
            <div class="stat-label">Pending Payments</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-primary">
            <i class="fas fa-user-graduate stat-icon"></i>
            <div class="stat-value"><?= $stats['students_with_results'] ?></div>
            <div class="stat-label">Students with Results</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-warning">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-value"><?= $stats['pending_approvals'] ?></div>
            <div class="stat-label">Pending Approvals</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['published_results'] ?></div>
            <div class="stat-label">Published Results</div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Results Management</div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/manage.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-table me-1"></i> Manage Results
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/approve.php" class="btn btn-outline-warning w-100 <?= $stats['pending_approvals'] > 0 ? 'position-relative' : '' ?>">
                    <i class="fas fa-check-double me-1"></i> Approvals
                    <?php if ($stats['pending_approvals'] > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $stats['pending_approvals'] ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/pins.php" class="btn btn-outline-info w-100">
                    <i class="fas fa-key me-1"></i> Result PINs
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/promotion.php" class="btn btn-outline-success w-100">
                    <i class="fas fa-arrow-up me-1"></i> Promotion
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/pdf.php" class="btn btn-outline-danger w-100">
                    <i class="fas fa-file-pdf me-1"></i> PDF Report Cards
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/annual.php" class="btn btn-outline-success w-100">
                    <i class="fas fa-calendar-alt me-1"></i> Annual Reports
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/import.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-file-import me-1"></i> Import Scores
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/broadcast.php" class="btn btn-outline-warning w-100">
                    <i class="fas fa-bullhorn me-1"></i> Broadcast
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/settings.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-cog me-1"></i> Settings
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/psychomotor.php" class="btn btn-outline-info w-100">
                    <i class="fas fa-running me-1"></i> Psychomotor
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/affective.php" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-heart me-1"></i> Affective
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= BASE_URL ?>/admin/results/comments.php" class="btn btn-outline-dark w-100">
                    <i class="fas fa-comment me-1"></i> Remarks
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users me-2"></i>Recent Users</span>
                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td><?= sanitizeInput($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                <td><?= sanitizeInput($u['email']) ?></td>
                                <td><?= getRoleBadge($u['role']) ?></td>
                                <td><?= timeAgo($u['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentUsers)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No users yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-signature me-2"></i>Recent Applications</span>
                <a href="<?= BASE_URL ?>/admin/applications.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th>Applied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentApplications as $a): ?>
                            <tr>
                                <td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td>
                                <td><?= sanitizeInput($a['class_applying']) ?></td>
                                <td><?= getStatusBadge($a['status']) ?></td>
                                <td><?= timeAgo($a['submitted_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentApplications)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No applications yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
