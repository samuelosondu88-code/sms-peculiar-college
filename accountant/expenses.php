<?php
require_once __DIR__ . '/../config/session.php';
requireRole('accountant');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Expenses';
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    $category = sanitizeInput($_POST['category'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $expenseDate = sanitizeInput($_POST['expense_date'] ?? date('Y-m-d'));

    if ($category && $amount > 0) {
        $stmt = $db->prepare("INSERT INTO expenses (category, description, amount, expense_date, entered_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$category, $description, $amount, $expenseDate, $_SESSION['user_id']]);
        $msg = 'Expense recorded.';
    }
}

$month = sanitizeInput($_GET['month'] ?? date('Y-m'));
$expenses = $db->prepare("SELECT * FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? ORDER BY expense_date DESC");
$expenses->execute([$month]);
$expensesList = $expenses->fetchAll();

$total = array_sum(array_column($expensesList, 'amount'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-shopping-cart me-2"></i>Expenses</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="fas fa-plus me-1"></i>Add Expense</button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-3"><label class="form-label">Month</label><input type="month" name="month" class="form-control" value="<?= $month ?>" onchange="this.form.submit()"></div>
    <div class="col-md-3"><label class="form-label">Total</label><div class="form-control-plaintext fw-bold fs-5"><?= formatCurrency($total) ?></div></div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($expensesList as $e): ?>
                    <tr>
                        <td><?= formatDate($e['expense_date']) ?></td>
                        <td><span class="badge bg-primary"><?= sanitizeInput($e['category']) ?></span></td>
                        <td><?= sanitizeInput($e['description'] ?? '-') ?></td>
                        <td><strong class="text-danger"><?= formatCurrency($e['amount']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($expensesList)): ?><tr><td colspan="4" class="text-center text-muted py-3">No expenses.</td></tr><?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr><td colspan="3"><strong>Total</strong></td><td><strong><?= formatCurrency($total) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">Add Expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select</option>
                            <option value="Utilities">Utilities</option><option value="Salaries">Salaries</option>
                            <option value="Maintenance">Maintenance</option><option value="Supplies">Supplies</option>
                            <option value="Transport">Transport</option><option value="Food">Food</option>
                            <option value="Events">Events</option><option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Amount (₦)</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
                    <div class="mb-3"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><input type="hidden" name="save_expense" value="1"><button type="submit" class="btn btn-primary">Record</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
