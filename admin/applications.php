<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Admission Applications';
$db = getDB();

$status = sanitizeInput($_GET['status'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ["a.status != 'draft'"];
$params = [];

if ($status) {
    $where[] = "a.status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "(a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ? OR a.application_ref LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}

$whereClause = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM applications a WHERE $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pagination = paginate($total, $page);
$stmt = $db->prepare("SELECT a.*, af.form_name FROM applications a JOIN admission_forms af ON a.form_id = af.id WHERE $whereClause ORDER BY a.submitted_at DESC LIMIT ? OFFSET ?");
$params[] = $pagination['limit'];
$params[] = $pagination['offset'];
$stmt->execute($params);
$applications = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appId = (int)($_POST['application_id'] ?? 0);
    $newStatus = sanitizeInput($_POST['action'] ?? '');
    $notes = sanitizeInput($_POST['admin_notes'] ?? '');

    if (in_array($newStatus, ['reviewing', 'accepted', 'rejected', 'waitlisted']) && $appId) {
        $stmt = $db->prepare("UPDATE applications SET status = ?, admin_notes = ?, reviewed_by = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $notes, $_SESSION['user_id'], $appId]);

        $app = $db->prepare("SELECT email, first_name, application_ref FROM applications WHERE id = ?");
        $app->execute([$appId]);
        $appData = $app->fetch();

        $subject = "Application Update - " . SCHOOL_NAME;
        $body = "<p>Dear {$appData['first_name']},</p>
                <p>Your application ({$appData['application_ref']}) status has been updated to: <strong>{$newStatus}</strong>.</p>
                <p>Track your status: <a href='" . APP_URL . "/public/application-status.php?ref={$appData['application_ref']}'>Click here</a></p>";
        sendEmail($appData['email'], $subject, $body);
        logActivity($_SESSION['user_id'], 'update_application_status', 'applications', $appId, '', $newStatus);

        if ($newStatus === 'accepted') {
            $stmt = $db->prepare("SELECT ap.id, ap.status FROM application_payments ap WHERE ap.application_id = ? AND ap.status = 'pending' ORDER BY ap.id DESC LIMIT 1");
            $stmt->execute([$appId]);
            $pendingPay = $stmt->fetch();
            if ($pendingPay) {
                $stmt = $db->prepare("UPDATE application_payments SET status = 'approved', verified_by = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $pendingPay['id']]);
            }
        }

        redirect("/admin/applications.php?msg=Application updated to {$newStatus}");
    }
}

$msg = sanitizeInput($_GET['msg'] ?? '');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-signature me-2"></i>Admission Applications</h4>
    <a href="<?= BASE_URL ?>/admin/admission-forms.php" class="btn btn-outline-primary">
        <i class="fas fa-cog me-1"></i>Manage Forms
    </a>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or reference..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="reviewing" <?= $status === 'reviewing' ? 'selected' : '' ?>>Reviewing</option>
                    <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="waitlisted" <?= $status === 'waitlisted' ? 'selected' : '' ?>>Waitlisted</option>
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
                        <th>Ref #</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $a): ?>
                    <tr>
                        <td><strong><?= sanitizeInput($a['application_ref']) ?></strong></td>
                        <td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td><?= sanitizeInput($a['class_applying']) ?></td>
                        <td><?= sanitizeInput($a['email']) ?></td>
                        <td><?= sanitizeInput($a['phone']) ?></td>
                        <td><?= getStatusBadge($a['status']) ?></td>
                        <td><?= $a['submitted_at'] ? timeAgo($a['submitted_at']) : '-' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $a['id'] ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>

                    <div class="modal fade" id="viewModal<?= $a['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="POST">
                                    <input type="hidden" name="application_id" value="<?= $a['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Application: <?= sanitizeInput($a['application_ref']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Applicant</h6>
                                                <p class="mb-1"><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></p>
                                                <p class="mb-1 small">Email: <?= sanitizeInput($a['email']) ?></p>
                                                <p class="mb-1 small">Phone: <?= sanitizeInput($a['phone']) ?></p>
                                                <p class="mb-1 small">DOB: <?= $a['date_of_birth'] ?? 'N/A' ?></p>
                                                <p class="mb-1 small">Gender: <?= $a['gender'] ?? 'N/A' ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Application</h6>
                                                <p class="mb-1 small">Class: <?= sanitizeInput($a['class_applying']) ?></p>
                                                <p class="mb-1 small">Previous School: <?= sanitizeInput($a['previous_school'] ?: 'N/A') ?></p>
                                                <p class="mb-1 small">Form: <?= sanitizeInput($a['form_name']) ?></p>
                                                <p class="mb-1 small">Status: <?= getStatusBadge($a['status']) ?></p>
                                            </div>
                                            <div class="col-12">
                                                <h6 class="fw-bold">Parent/Guardian</h6>
                                                <p class="mb-1 small">Name: <?= sanitizeInput($a['parent_name'] ?: 'N/A') ?></p>
                                                <p class="mb-1 small">Phone: <?= sanitizeInput($a['parent_phone'] ?: 'N/A') ?></p>
                                                <p class="mb-1 small">Email: <?= sanitizeInput($a['parent_email'] ?: 'N/A') ?></p>
                                            </div>
                                            <?php if ($a['documents']): $docs = json_decode($a['documents'], true); if (!empty($docs)): ?>
                                            <div class="col-12">
                                                <h6 class="fw-bold">Documents</h6>
                                                <?php foreach ($docs as $key => $path): ?>
                                                <a href="/<?= $path ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-1"><?= ucfirst(str_replace('_', ' ', $key)) ?></a>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; endif; ?>
                                            <div class="col-12">
                                                <label class="form-label fw-bold">Admin Notes</label>
                                                <textarea name="admin_notes" class="form-control" rows="2"><?= sanitizeInput($a['admin_notes'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="action" value="reviewing" class="btn btn-info text-white" <?= $a['status'] === 'reviewing' ? 'disabled' : '' ?>>
                                            <i class="fas fa-search me-1"></i>Mark Reviewing
                                        </button>
                                        <button type="submit" name="action" value="accepted" class="btn btn-success" <?= $a['status'] === 'accepted' ? 'disabled' : '' ?>>
                                            <i class="fas fa-check me-1"></i>Accept
                                        </button>
                                        <button type="submit" name="action" value="waitlisted" class="btn btn-secondary" <?= $a['status'] === 'waitlisted' ? 'disabled' : '' ?>>
                                            <i class="fas fa-clock me-1"></i>Waitlist
                                        </button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger" <?= $a['status'] === 'rejected' ? 'disabled' : '' ?>>
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($applications)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No applications found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pagination['totalPages'] > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $pagination['totalPages']; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
