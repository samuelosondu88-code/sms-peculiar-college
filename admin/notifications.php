<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Send Notification';
$db = getDB();
$msg = '';
$msgType = 'success';

$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $channel = $_POST['channel'] ?? '';
    $recipientType = $_POST['recipient_type'] ?? '';
    $classId = (int)($_POST['class_id'] ?? 0);
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = $_POST['message'] ?? '';

    if (!in_array($channel, ['email', 'sms', 'both'])) { $msg = 'Select a channel.'; $msgType = 'danger'; }
    elseif (!in_array($recipientType, ['all_users','all_students','all_parents','all_teachers','all_admins','class'])) { $msg = 'Select recipients.'; $msgType = 'danger'; }
    elseif (empty($subject)) { $msg = 'Enter a subject.'; $msgType = 'danger'; }
    elseif (empty($message)) { $msg = 'Enter a message.'; $msgType = 'danger'; }
    else {
        $recipients = [];
        if ($recipientType === 'class' && $classId > 0) {
            $stmt = $db->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.phone FROM users u JOIN students s ON u.id = s.user_id WHERE s.class_id = ?");
            $stmt->execute([$classId]);
            $recipients = $stmt->fetchAll();
        } elseif ($recipientType === 'all_students') {
            $recipients = $db->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone FROM users u JOIN students s ON u.id = s.user_id")->fetchAll();
        } elseif ($recipientType === 'all_parents') {
            $recipients = $db->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone FROM users u WHERE u.role = 'parent'")->fetchAll();
        } elseif ($recipientType === 'all_teachers') {
            $recipients = $db->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone FROM users u WHERE u.role = 'teacher'")->fetchAll();
        } elseif ($recipientType === 'all_admins') {
            $recipients = $db->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone FROM users u WHERE u.role = 'admin'")->fetchAll();
        } elseif ($recipientType === 'all_users') {
            $recipients = $db->query("SELECT u.id, u.first_name, u.last_name, u.email, u.phone FROM users u")->fetchAll();
        }

        if (empty($recipients)) { $msg = 'No recipients found.'; $msgType = 'danger'; }
        else {
            $sent = 0; $failed = 0;
            foreach ($recipients as $r) {
                $fullName = $r['first_name'] . ' ' . $r['last_name'];
                if ($channel === 'email' || $channel === 'both') {
                    $emailSent = sendEmail($r['email'], $subject, $message, $fullName);
                    $emailSent ? $sent++ : $failed++;
                }
                if ($channel === 'sms' || $channel === 'both') {
                    $smsSent = sendSMS($r['phone'], $message);
                    $smsSent ? $sent++ : $failed++;
                }
            }
            try { $db->exec("CREATE TABLE IF NOT EXISTS notification_log (id INT AUTO_INCREMENT PRIMARY KEY, subject VARCHAR(255), message TEXT, channel VARCHAR(20), recipient_type VARCHAR(50), class_id INT, sent_count INT, failed_count INT, sent_by INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                $logStmt = $db->prepare("INSERT INTO notification_log (subject, message, channel, recipient_type, class_id, sent_count, failed_count, sent_by) VALUES (?,?,?,?,?,?,?,?)");
                $logStmt->execute([$subject, $message, $channel, $recipientType, $classId ?: null, $sent, $failed, $_SESSION['user_id']]);
            } catch (Exception $e) { /* log silently */ }
            $msg = "Notification sent. Successful: $sent, Failed: $failed"; $msgType = 'success';
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header"><i class="fas fa-bell me-2"></i>Send Notification</div>
                <div class="card-body">
                    <?php if ($msg): ?>
                    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Channel</label>
                            <select name="channel" class="form-select" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recipients</label>
                            <select name="recipient_type" class="form-select" id="recipientType" required>
                                <option value="all_users">All Users</option>
                                <option value="all_students">All Students</option>
                                <option value="all_parents">All Parents</option>
                                <option value="all_teachers">All Teachers</option>
                                <option value="all_admins">All Admins</option>
                                <option value="class">Specific Class</option>
                            </select>
                        </div>
                        <div class="mb-3" id="classDiv" style="display:none">
                            <label class="form-label">Class</label>
                            <select name="class_id" class="form-select">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitizeInput(className($c['name'], $c['section'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <button type="submit" name="send_notification" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('recipientType').addEventListener('change', function() {
    document.getElementById('classDiv').style.display = this.value === 'class' ? 'block' : 'none';
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
