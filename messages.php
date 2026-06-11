<?php
require_once __DIR__ . '/config/session.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Messages';
$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = (int)($_POST['receiver_id'] ?? 0);
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $body = sanitizeInput($_POST['body'] ?? '');

    if ($receiverId && $subject && $body) {
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $receiverId, $subject, $body]);
        logActivity($userId, 'send_message', 'messages', $db->lastInsertId());
        redirect('/messages.php?msg=Message sent');
    }
}

if (isset($_GET['mark_read'])) {
    $msgId = (int)$_GET['mark_read'];
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$msgId, $userId]);
    redirect('/messages.php');
}

$inbox = $db->prepare("SELECT m.*, u.first_name, u.last_name, u.role FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = ? ORDER BY m.sent_at DESC LIMIT 50");
$inbox->execute([$userId]);
$inboxMessages = $inbox->fetchAll();

$sent = $db->prepare("SELECT m.*, u.first_name, u.last_name, u.role FROM messages m JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = ? ORDER BY m.sent_at DESC LIMIT 50");
$sent->execute([$userId]);
$sentMessages = $sent->fetchAll();

$usersStmt = $db->prepare("SELECT id, first_name, last_name, role FROM users WHERE id != ? AND status = 'active' ORDER BY first_name");
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

$msg = sanitizeInput($_GET['msg'] ?? '');

$tab = $_GET['tab'] ?? 'inbox';

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-envelope me-2"></i>Messages</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
        <i class="fas fa-plus me-1"></i>Compose
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'inbox' ? 'active' : '' ?>" href="?tab=inbox">
            <i class="fas fa-inbox me-1"></i>Inbox
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'sent' ? 'active' : '' ?>" href="?tab=sent">
            <i class="fas fa-paper-plane me-1"></i>Sent
        </a>
    </li>
</ul>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><?= $tab === 'inbox' ? 'From' : 'To' ?></th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $messages = $tab === 'inbox' ? $inboxMessages : $sentMessages; ?>
                    <?php foreach ($messages as $m): ?>
                    <tr class="<?= $tab === 'inbox' && !$m['is_read'] ? 'fw-bold' : '' ?>">
                        <td>
                            <?= sanitizeInput($m['first_name'] . ' ' . $m['last_name']) ?>
                            <small class="text-muted">(<?= ucfirst($m['role']) ?>)</small>
                        </td>
                        <td><?= sanitizeInput($m['subject']) ?></td>
                        <td><small><?= timeAgo($m['sent_at']) ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewMessage(<?= $m['id'] ?>, '<?= addslashes($m['subject']) ?>', '<?= addslashes($m['body']) ?>', '<?= addslashes($m['first_name'] . ' ' . $m['last_name']) ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($tab === 'inbox' && !$m['is_read']): ?>
                            <a href="?mark_read=<?= $m['id'] ?>" class="btn btn-sm btn-outline-success" title="Mark read">
                                <i class="fas fa-check"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($messages)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No messages</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Compose Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="receiver_id" class="form-select" required>
                            <option value="">Select recipient</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= sanitizeInput($u['first_name'] . ' ' . $u['last_name']) ?> (<?= ucfirst($u['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="body" class="form-control" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="send_message" value="1">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSubject"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="viewFrom"></p>
                <hr>
                <div id="viewBody"></div>
            </div>
        </div>
    </div>
</div>

<script>
function viewMessage(id, subject, body, from) {
    document.getElementById('viewSubject').textContent = subject;
    document.getElementById('viewFrom').textContent = 'From: ' + from;
    document.getElementById('viewBody').textContent = body;
    new bootstrap.Modal(document.getElementById('viewMessageModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
