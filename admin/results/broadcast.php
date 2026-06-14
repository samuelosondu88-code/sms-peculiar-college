<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Result Broadcast';
$db = getDB();

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_GET['term_id'] ?? 0);
$selectedClass = (int)($_GET['class_id'] ?? 0);
$broadcastType = $_POST['broadcast_type'] ?? '';
$message = $_POST['message'] ?? '';
$success = '';
$error = '';

if ($selectedSession) {
    $stmt = $db->prepare("SELECT id, term_name FROM terms WHERE session_id = ? ORDER BY id");
    $stmt->execute([$selectedSession]);
    $terms = $stmt->fetchAll();
}

$students = [];
$studentContacts = [];

if ($selectedClass && $selectedSession && $selectedTerm) {
    $stmt = $db->prepare("
        SELECT s.id, u.first_name, u.last_name, u.phone, u.email, s.admission_no,
               AVG(rs.total_score) as avg_score
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN result_scores rs ON rs.student_id = s.id AND rs.session_id = ? AND rs.term_id = ?
        WHERE s.class_id = ? AND s.status = 'active'
        GROUP BY s.id
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$selectedSession, $selectedTerm, $selectedClass]);
    $students = $stmt->fetchAll();
    $studentContacts = array_filter($students, fn($s) => $s['phone'] || $s['email']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $broadcastType = $_POST['broadcast_type'] ?? '';
    $message = sanitizeInput($_POST['message'] ?? '');
    $studentIds = $_POST['student_ids'] ?? [];

    if (empty($broadcastType)) { $error = 'Please select a broadcast type.'; }
    elseif (empty($message)) { $error = 'Please enter a message.'; }
    elseif (empty($studentIds)) { $error = 'Please select at least one recipient.'; }
    else {
        $sentCount = 0;
        $stmt = $db->prepare("SELECT u.id, u.phone, u.email, u.first_name, u.last_name FROM users u JOIN students s ON s.user_id = u.id WHERE s.id = ?");
        $logStmt = $db->prepare("INSERT INTO broadcast_log (recipient_id, recipient_type, channel, message, status, sent_at) VALUES (?, 'student', ?, ?, 'sent', NOW())");

        foreach ($studentIds as $sid) {
            $stmt->execute([(int)$sid]);
            $user = $stmt->fetch();
            if (!$user) continue;

            $personalizedMsg = str_replace(
                ['{student_name}', '{parent_name}'],
                [$user['first_name'] . ' ' . $user['last_name'], 'Parent'],
                $message
            );

            if ($broadcastType === 'sms' && $user['phone']) {
                $logStmt->execute([$user['id'], 'sms', $personalizedMsg]);
                $sentCount++;
            } elseif ($broadcastType === 'email' && $user['email']) {
                $headers = "From: " . SCHOOL_NAME . " <" . SCHOOL_EMAIL . ">\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                @mail($user['email'], 'Result Notification - ' . SCHOOL_NAME, $personalizedMsg, $headers);
                $logStmt->execute([$user['id'], 'email', $personalizedMsg]);
                $sentCount++;
            } elseif ($broadcastType === 'both') {
                if ($user['phone']) {
                    $logStmt->execute([$user['id'], 'sms', $personalizedMsg]);
                    $sentCount++;
                }
                if ($user['email']) {
                    $headers = "From: " . SCHOOL_NAME . " <" . SCHOOL_EMAIL . ">\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    @mail($user['email'], 'Result Notification - ' . SCHOOL_NAME, $personalizedMsg, $headers);
                    $logStmt->execute([$user['id'], 'email', $personalizedMsg]);
                    $sentCount++;
                }
            }
        }
        $success = "Broadcast sent to $sentCount recipient(s).";
    }
}

$broadcastLog = [];
if ($selectedClass) {
    $stmt = $db->prepare("
        SELECT bl.*, u.first_name, u.last_name FROM broadcast_log bl
        JOIN users u ON bl.recipient_id = u.id
        WHERE bl.recipient_type = 'student'
        ORDER BY bl.sent_at DESC LIMIT 50
    ");
    $stmt->execute();
    $broadcastLog = $stmt->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-bullhorn me-2"></i>Result Broadcast</h4>
    <a href="<?= BASE_URL ?>/admin/results/index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($success): ?><div class="alert alert-success py-2"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= $error ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="fas fa-filter me-2"></i>Select Target</div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($students)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2"></i>Recipients (<?= count($studentContacts) ?> with contacts)</span>
        <div>
            <button class="btn btn-sm btn-outline-primary" onclick="selectAll(true)"><i class="fas fa-check-double me-1"></i>Select All</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="selectAll(false)"><i class="fas fa-times me-1"></i>Deselect All</button>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" id="broadcastForm">
            <input type="hidden" name="session_id" value="<?= $selectedSession ?>">
            <input type="hidden" name="term_id" value="<?= $selectedTerm ?>">
            <input type="hidden" name="class_id" value="<?= $selectedClass ?>">
            <input type="hidden" name="send_broadcast" value="1">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Broadcast Type</label>
                    <select name="broadcast_type" class="form-select" required>
                        <option value="">Select</option>
                        <option value="sms" <?= $broadcastType === 'sms' ? 'selected' : '' ?>>SMS Only</option>
                        <option value="email" <?= $broadcastType === 'email' ? 'selected' : '' ?>>Email Only</option>
                        <option value="both" <?= $broadcastType === 'both' ? 'selected' : '' ?>>SMS & Email</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Message <small class="text-muted">(Use {student_name} as placeholder)</small></label>
                <textarea name="message" class="form-control" rows="4" required placeholder="Dear {student_name}, your results for the term have been published. Please check the school portal."><?= sanitizeInput($message) ?></textarea>
            </div>

            <div class="table-responsive mb-3" style="max-height: 300px; overflow-y: auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAllCB" onchange="selectAll(this.checked)"></th>
                            <th>Student</th>
                            <th>Admission</th>
                            <th>Avg Score</th>
                            <th>Phone</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                        <tr>
                            <td><input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-cb" <?= $s['phone'] || $s['email'] ? '' : 'disabled' ?>></td>
                            <td><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?></td>
                            <td><?= sanitizeInput($s['admission_no']) ?></td>
                            <td><?= $s['avg_score'] ? number_format($s['avg_score'], 1) : '-' ?></td>
                            <td><?= sanitizeInput($s['phone'] ?? '-') ?></td>
                            <td><?= sanitizeInput($s['email'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send Broadcast</button>
        </form>
    </div>
</div>

<script>
function selectAll(check) {
    document.querySelectorAll('.student-cb:not(:disabled)').forEach(cb => cb.checked = check);
    document.getElementById('selectAllCB').checked = check;
}
</script>
<?php endif; ?>

<?php if (!empty($broadcastLog)): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-history me-2"></i>Broadcast History</div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Recipient</th>
                    <th>Channel</th>
                    <th>Message</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($broadcastLog as $log): ?>
                <tr>
                    <td><?= date('d M Y H:i', strtotime($log['sent_at'])) ?></td>
                    <td><?= sanitizeInput($log['first_name'] . ' ' . $log['last_name']) ?></td>
                    <td><span class="badge bg-<?= $log['channel'] === 'sms' ? 'info' : 'primary' ?>"><?= strtoupper($log['channel']) ?></span></td>
                    <td><small><?= sanitizeInput(substr($log['message'], 0, 80)) ?>...</small></td>
                    <td><span class="badge bg-success">Sent</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
