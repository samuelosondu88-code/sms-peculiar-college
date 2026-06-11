<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Manage Admission Forms';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $formName = sanitizeInput($_POST['form_name'] ?? '');
    $price = (float)($_POST['price'] ?? ADMISSION_FORM_PRICE);
    $sessionId = (int)($_POST['academic_session_id'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 0);

    if (isset($_POST['form_id']) && $_POST['form_id']) {
        $stmt = $db->prepare("UPDATE admission_forms SET form_name = ?, price = ?, academic_session_id = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$formName, $price, $sessionId, $isActive, $_POST['form_id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO admission_forms (form_name, price, academic_session_id, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$formName, $price, $sessionId, $isActive]);
    }
    redirect('/admin/admission-forms.php?msg=Form saved');
}

$forms = $db->query("SELECT af.*, as_session.session_name FROM admission_forms af JOIN academic_sessions as_session ON af.academic_session_id = as_session.id ORDER BY af.created_at DESC")->fetchAll();
$sessions = $db->query("SELECT id, session_name FROM academic_sessions WHERE status = 'active'")->fetchAll();
$msg = sanitizeInput($_GET['msg'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-cog me-2"></i>Admission Form Settings</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        <i class="fas fa-plus me-1"></i>New Form
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Form Name</th>
                        <th>Price (₦)</th>
                        <th>Session</th>
                        <th>Active</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $f): ?>
                    <tr>
                        <td><?= sanitizeInput($f['form_name']) ?></td>
                        <td><strong><?= number_format($f['price'], 0) ?></strong></td>
                        <td><?= sanitizeInput($f['session_name']) ?></td>
                        <td><?= $f['is_active'] ? getStatusBadge('active') : getStatusBadge('inactive') ?></td>
                        <td><?= formatDate($f['created_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editForm(<?= $f['id'] ?>, '<?= addslashes($f['form_name']) ?>', <?= $f['price'] ?>, <?= $f['academic_session_id'] ?>, <?= $f['is_active'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="form_id" id="form_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Admission Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Form Name</label>
                        <input type="text" name="form_name" id="form_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (₦)</label>
                        <input type="number" name="price" id="form_price" class="form-control" value="<?= ADMISSION_FORM_PRICE ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Academic Session</label>
                        <select name="academic_session_id" id="form_session" class="form-select" required>
                            <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['session_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="form_active" class="form-check-input" value="1" checked>
                        <label class="form-check-label">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="save_form" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editForm(id, name, price, sessionId, active) {
    document.getElementById('form_id').value = id;
    document.getElementById('form_name').value = name;
    document.getElementById('form_price').value = price;
    document.getElementById('form_session').value = sessionId;
    document.getElementById('form_active').checked = active === 1;
    document.getElementById('modalTitle').textContent = 'Edit Admission Form';
    new bootstrap.Modal(document.getElementById('formModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
