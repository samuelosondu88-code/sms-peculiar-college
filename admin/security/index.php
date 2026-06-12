<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Security Dashboard';
$db = getDB();

$scan = scanSecurityStatus();

$auditStmt = $db->query("SELECT al.*, u.first_name, u.last_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 50");
$auditLogs = $auditStmt->fetchAll();

$loginStmt = $db->query("SELECT DATE(attempted_at) as day, COUNT(*) as total, SUM(success) as successful FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(attempted_at) ORDER BY day DESC");
$loginStats = $loginStmt->fetchAll();

$recentThreats = $db->query("SELECT * FROM audit_logs WHERE action LIKE 'security_%' ORDER BY created_at DESC LIMIT 20")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-shield-alt me-2"></i>Security Dashboard</h4>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card <?= $scan['score'] >= 80 ? 'stat-success' : ($scan['score'] >= 60 ? 'stat-warning' : 'bg-danger') ?>">
            <i class="fas fa-chart-line stat-icon"></i>
            <div class="stat-value"><?= $scan['score'] ?>%</div>
            <div class="stat-label">Security Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card <?= $scan['rating'] === 'A+' || $scan['rating'] === 'A' ? 'stat-success' : ($scan['rating'] === 'B' ? 'stat-warning' : 'bg-danger') ?>">
            <i class="fas fa-award stat-icon"></i>
            <div class="stat-value"><?= $scan['rating'] ?></div>
            <div class="stat-label">Security Rating</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $scan['passed_checks'] ?>/<?= $scan['total_checks'] ?></div>
            <div class="stat-label">Checks Passed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#dc2626,#ef4444)">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-value"><?= count($scan['issues']) ?></div>
            <div class="stat-label">Open Issues</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Security Checks</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <tbody>
                            <?php foreach ($scan['checks'] as $check => $passed): ?>
                            <tr>
                                <td><i class="fas fa-<?= $passed ? 'check-circle text-success' : 'times-circle text-danger' ?> me-2"></i><?= ucfirst(str_replace('_', ' ', $check)) ?></td>
                                <td class="text-end"><?= $passed ? '<span class="badge bg-success">Pass</span>' : '<span class="badge bg-danger">Fail</span>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-exclamation-circle me-2"></i>Issues & Recommendations</div>
            <div class="card-body">
                <?php if (empty($scan['issues'])): ?>
                <div class="text-center text-success py-4"><i class="fas fa-check-circle fa-3x mb-2"></i><br>No security issues detected.</div>
                <?php else: ?>
                <ol class="mb-0">
                    <?php foreach ($scan['issues'] as $issue): ?>
                    <li class="mb-2 small text-danger"><i class="fas fa-exclamation-triangle me-1"></i><?= sanitizeInput($issue) ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-history me-2"></i>Recent Login Activity (7 Days)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Date</th><th>Total</th><th>Successful</th><th>Failed</th></tr></thead>
                        <tbody>
                            <?php foreach ($loginStats as $ls): ?>
                            <tr>
                                <td><?= formatDate($ls['day']) ?></td>
                                <td><?= $ls['total'] ?></td>
                                <td class="text-success"><?= (int)$ls['successful'] ?></td>
                                <td class="text-danger"><?= (int)$ls['total'] - (int)$ls['successful'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($loginStats)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No login data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="fas fa-bug me-2"></i>Recent Security Events</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Event</th><th>User</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentThreats as $t): ?>
                            <tr>
                                <td><span class="badge bg-warning text-dark"><?= sanitizeInput($t['action']) ?></span></td>
                                <td><?= sanitizeInput($t['old_value'] ?: '-') ?></td>
                                <td><?= timeAgo($t['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentThreats)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No security events recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fas fa-list me-2"></i>Audit Trail (Last 50)</span>
        <div>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i></button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:400px;overflow-y:auto">
            <table class="table table-sm mb-0">
                <thead><tr><th>User</th><th>Action</th><th>Table</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                    <?php foreach ($auditLogs as $log): ?>
                    <tr>
                        <td><?= sanitizeInput(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? 'System')) ?></td>
                        <td><span class="badge bg-secondary"><?= sanitizeInput($log['action']) ?></span></td>
                        <td><?= sanitizeInput($log['table_name'] ?: '-') ?></td>
                        <td><code><?= sanitizeInput($log['ip_address'] ?: '-') ?></code></td>
                        <td><small><?= timeAgo($log['created_at']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($auditLogs)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No audit logs recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
