<?php
require_once __DIR__ . '/../config/session.php';
requireRole('accountant');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Financial Reports';
$db = getDB();

$year = (int)($_GET['year'] ?? date('Y'));

$stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE status = 'approved' AND YEAR(payment_date) = ?");
$stmt->execute([$year]);
$totalRevenue = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE status = 'approved' AND YEAR(payment_date) = ? AND MONTH(payment_date) = ?");
$monthlyRevenue = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt->execute([$year, $m]);
    $monthlyRevenue[] = (float)$stmt->fetchColumn();
}

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = ?");
$stmt->execute([$year]);
$totalExpenses = (float)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?");
$monthlyExpenses = [];
for ($m = 1; $m <= 12; $m++) {
    $stmt->execute([$year, $m]);
    $monthlyExpenses[] = (float)$stmt->fetchColumn();
}

$outstandingFees = $db->query("SELECT COALESCE(SUM(balance), 0) FROM fees WHERE status IN ('unpaid', 'partial')")->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(gross_salary), 0) FROM payroll WHERE YEAR(payment_date) = ?");
$stmt->execute([$year]);
$totalPayroll = (float)$stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Financial Reports</h4>
</div>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-3">
        <select name="year" class="form-select" onchange="this.form.submit()">
            <?php for ($y = date('Y'); $y >= 2025; $y--): ?>
            <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card stat-success"><i class="fas fa-arrow-up stat-icon"></i><div class="stat-value"><?= formatCurrency($totalRevenue) ?></div><div class="stat-label">Total Revenue</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-danger"><i class="fas fa-arrow-down stat-icon"></i><div class="stat-value"><?= formatCurrency($totalExpenses) ?></div><div class="stat-label">Total Expenses</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-primary"><i class="fas fa-wallet stat-icon"></i><div class="stat-value"><?= formatCurrency($totalPayroll) ?></div><div class="stat-label">Payroll Cost</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-warning"><i class="fas fa-exclamation-triangle stat-icon"></i><div class="stat-value"><?= formatCurrency($outstandingFees) ?></div><div class="stat-label">Outstanding Fees</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header">Monthly Revenue vs Expenses (<?= $year ?>)</div>
    <div class="card-body">
        <canvas id="financeChart" height="300"></canvas>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Revenue Breakdown</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Month</th><th>Revenue</th><th>Expenses</th><th>Net</th></tr></thead>
                    <tbody>
                        <?php $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; ?>
                        <?php foreach ($months as $i => $m):
                            $net = $monthlyRevenue[$i] - $monthlyExpenses[$i];
                        ?>
                        <tr>
                            <td><?= $m ?></td>
                            <td class="text-success"><?= formatCurrency($monthlyRevenue[$i]) ?></td>
                            <td class="text-danger"><?= formatCurrency($monthlyExpenses[$i]) ?></td>
                            <td class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><strong><?= formatCurrency($net) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong><?= formatCurrency($totalRevenue) ?></strong></td>
                            <td><strong><?= formatCurrency($totalExpenses) ?></strong></td>
                            <td><strong class="<?= ($totalRevenue - $totalExpenses) >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency($totalRevenue - $totalExpenses) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Report Actions</div>
            <div class="card-body text-center">
                <button class="btn btn-primary w-100 mb-2" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Report</button>
                <p class="text-muted small">Print a hard copy of the financial summary.</p>
            </div>
        </div>
    </div>
</div>

<script src="/assets/vendors/chart.js/chart.umd.min.js"></script>
<script>
var ctx = document.getElementById('financeChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [
            { label: 'Revenue', data: <?= json_encode($monthlyRevenue) ?>, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', fill: true },
            { label: 'Expenses', data: <?= json_encode($monthlyExpenses) ?>, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return '₦' + v.toLocaleString(); } } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
