<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'System Settings';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = sanitizeInput($_POST['setting_key'] ?? '');
    $value = sanitizeInput($_POST['setting_value'] ?? '');
    $msg = 'Settings saved. Note: Update config/app.php for permanent changes.';
}

$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT t.*, ac.session_name FROM terms t JOIN academic_sessions ac ON t.session_id = ac.id ORDER BY ac.start_date DESC, t.id")->fetchAll();
$departments = $db->query("SELECT d.*, u.first_name, u.last_name FROM departments d LEFT JOIN users u ON d.head_teacher_id = u.id")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-cog me-2"></i>System Settings</h4>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" href="#school" data-bs-toggle="tab">School Info</a></li>
    <li class="nav-item"><a class="nav-link" href="#sessions" data-bs-toggle="tab">Academic Sessions</a></li>
    <li class="nav-item"><a class="nav-link" href="#departments" data-bs-toggle="tab">Departments</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="school">
        <div class="card">
            <div class="card-header">School Information</div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">School Name</label><input type="text" class="form-control" value="<?= SCHOOL_NAME ?>" disabled><small class="text-muted">Edit in config/app.php</small></div>
                    <div class="col-md-6"><label class="form-label">School Phone</label><input type="text" class="form-control" value="<?= SCHOOL_PHONE ?>" disabled></div>
                    <div class="col-12"><label class="form-label">School Address</label><textarea class="form-control" disabled><?= SCHOOL_ADDRESS ?></textarea></div>
                    <div class="col-md-6"><label class="form-label">School Email</label><input type="email" class="form-control" value="<?= SCHOOL_EMAIL ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label">Admission Form Price</label><input type="text" class="form-control" value="<?= formatCurrency(ADMISSION_FORM_PRICE) ?>" disabled></div>
                </div>
                <p class="text-muted small">To change these values, edit <code>config/app.php</code> in the project root.</p>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="sessions">
        <div class="card">
            <div class="card-header">Academic Sessions & Terms</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Session</th><th>Term</th><th>Start</th><th>End</th><th>Current</th></tr></thead>
                        <tbody>
                            <?php foreach ($terms as $t): ?>
                            <tr>
                                <td><?= sanitizeInput($t['session_name']) ?></td>
                                <td><?= sanitizeInput($t['term_name']) ?></td>
                                <td><?= formatDate($t['start_date']) ?></td>
                                <td><?= formatDate($t['end_date']) ?></td>
                                <td><?= $t['is_current'] ? getStatusBadge('active') : getStatusBadge('inactive') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="departments">
        <div class="card">
            <div class="card-header">Departments</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Code</th><th>Head</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                        <tr><td><?= sanitizeInput($d['name']) ?></td><td><?= sanitizeInput($d['code']) ?></td><td><?= sanitizeInput($d['first_name'] . ' ' . $d['last_name'] ?: '-') ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
