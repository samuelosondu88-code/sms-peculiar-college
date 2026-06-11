<?php
require_once __DIR__ . '/../config/session.php';
requireRole('accountant');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Fee Management';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_structure'])) {
    $classId = (int)($_POST['class_id'] ?? 0);
    $feeName = sanitizeInput($_POST['fee_name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $termId = (int)($_POST['term_id'] ?? 0);
    $dueDate = sanitizeInput($_POST['due_date'] ?? '');

    if ($classId && $feeName && $amount && $termId) {
        $stmt = $db->prepare("INSERT INTO fee_structure (class_id, fee_name, amount, term_id, due_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$classId, $feeName, $amount, $termId, $dueDate]);
        $msg = 'Fee structure added.';
    }
}

$structures = $db->query("SELECT fs.*, c.name as class_name, t.term_name, ac.session_name FROM fee_structure fs JOIN classes c ON fs.class_id = c.id JOIN terms t ON fs.term_id = t.id JOIN academic_sessions ac ON t.session_id = ac.id ORDER BY fs.created_at DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$terms = $db->query("SELECT t.id, t.term_name, ac.session_name FROM terms t JOIN academic_sessions ac ON t.session_id = ac.id WHERE ac.status = 'active'")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-money-bill me-2"></i>Fee Structure</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#feeModal"><i class="fas fa-plus me-1"></i>Add Fee</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Fee Name</th><th>Class</th><th>Amount</th><th>Term</th><th>Due Date</th></tr></thead>
                <tbody>
                    <?php foreach ($structures as $fs): ?>
                    <tr>
                        <td><?= sanitizeInput($fs['fee_name']) ?></td>
                        <td><?= sanitizeInput($fs['class_name']) ?></td>
                        <td><strong><?= formatCurrency($fs['amount']) ?></strong></td>
                        <td><?= sanitizeInput($fs['term_name'] . ' ' . $fs['session_name']) ?></td>
                        <td><?= $fs['due_date'] ? formatDate($fs['due_date']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($structures)): ?><tr><td colspan="5" class="text-center text-muted py-3">No fee structures.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="feeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Add Fee Structure</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Fee Name</label><input type="text" name="fee_name" class="form-control" required placeholder="e.g., Tuition Fee"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Class</label><select name="class_id" class="form-select" required><?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Amount (₦)</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><label class="form-label">Term</label><select name="term_id" class="form-select" required><?php foreach ($terms as $t): ?><option value="<?= $t['id'] ?>"><?= sanitizeInput($t['term_name'] . ' ' . $t['session_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_fee_structure" value="1"><button type="submit" class="btn btn-primary">Save</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
