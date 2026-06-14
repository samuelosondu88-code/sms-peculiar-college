<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Classroom';
$db = getDB();
$studentId = getStudentId();
$classroomId = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'overview';
$msg = '';
$msgType = 'success';

$classroom = $db->prepare("
    SELECT vc.*, s.name as subject_name, s.code as subject_code,
           c.name as class_name, c.section, u.first_name as t_first, u.last_name as t_last
    FROM class_enrollments ce
    JOIN virtual_classes vc ON ce.virtual_class_id = vc.id
    JOIN subjects s ON vc.subject_id = s.id
    JOIN classes c ON vc.class_id = c.id
    JOIN teachers t ON vc.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE vc.id = ? AND ce.student_id = ? AND ce.status = 'active'
");
$classroom->execute([$classroomId, $studentId]);
$vc = $classroom->fetch();
if (!$vc) redirect('/student/classroom/index.php');

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignId = (int)($_POST['assignment_id'] ?? 0);
    $text = sanitizeInput($_POST['submission_text'] ?? '');
    $filePath = '';

    $check = $db->prepare("SELECT id FROM class_assignments WHERE id = ? AND virtual_class_id = ?");
    $check->execute([$assignId, $classroomId]);
    if (!$check->fetch()) redirect("/student/classroom/view.php?id=$classroomId&tab=assignments");

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $name = 'sub_' . uniqid() . '.' . $ext;
        $dest = __DIR__ . '/../../uploads/classroom/assignments/' . $name;
        move_uploaded_file($_FILES['file']['tmp_name'], $dest);
        $filePath = 'uploads/classroom/assignments/' . $name;
    }

    $db->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, submission_text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), submission_text = VALUES(submission_text), status = 'submitted', score = NULL, feedback = NULL, graded_at = NULL")
        ->execute([$assignId, $studentId, $filePath, $text]);
    redirect("/student/classroom/view.php?id=$classroomId&tab=assignments&msg=Submitted");
}

// Handle discussion post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_discussion'])) {
    $content = sanitizeInput($_POST['content'] ?? '');
    $parentId = (int)($_POST['parent_id'] ?? 0);
    if ($content) {
        $db->prepare("INSERT INTO class_discussions (virtual_class_id, parent_id, user_id, content) VALUES (?, ?, ?, ?)")
            ->execute([$classroomId, $parentId ?: null, $_SESSION['user_id'], $content]);
        redirect("/student/classroom/view.php?id=$classroomId&tab=discussions");
    }
}

$materials = $db->prepare("SELECT * FROM class_materials WHERE virtual_class_id = ? ORDER BY created_at DESC");
$materials->execute([$classroomId]);

$assignments = $db->prepare("
    SELECT ca.*, asub.status as sub_status, asub.score, asub.feedback, asub.file_path as sub_file, asub.submitted_at
    FROM class_assignments ca
    LEFT JOIN assignment_submissions asub ON ca.id = asub.assignment_id AND asub.student_id = ?
    WHERE ca.virtual_class_id = ?
    ORDER BY ca.created_at DESC
");
$assignments->execute([$studentId, $classroomId]);

$announcements = $db->prepare("SELECT ca.*, u.first_name, u.last_name FROM class_announcements ca JOIN users u ON ca.created_by = u.id WHERE ca.virtual_class_id = ? ORDER BY ca.created_at DESC");
$announcements->execute([$classroomId]);

$discussions = $db->prepare("SELECT cd.*, u.first_name, u.last_name FROM class_discussions cd JOIN users u ON cd.user_id = u.id WHERE cd.virtual_class_id = ? AND cd.parent_id IS NULL ORDER BY cd.created_at DESC");
$discussions->execute([$classroomId]);

// Attendance stats
$attStats = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late FROM class_attendance WHERE virtual_class_id = ? AND student_id = ?");
$attStats->execute([$classroomId, $studentId]);
$att = $attStats->fetch();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="index.php" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <h4 class="fw-bold mb-0 mt-1"><i class="fas fa-chalkboard me-2"></i><?= sanitizeInput($vc['name']) ?></h4>
        <small class="text-muted"><?= sanitizeInput($vc['subject_name']) ?> — <?= sanitizeInput($vc['class_name'] . ' ' . $vc['section']) ?> | Teacher: <?= sanitizeInput($vc['t_first'] . ' ' . $vc['t_last']) ?></small>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=overview"><i class="fas fa-home me-1"></i>Overview</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'materials' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=materials"><i class="fas fa-file me-1"></i>Materials</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'assignments' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=assignments"><i class="fas fa-tasks me-1"></i>Assignments</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'announcements' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=announcements"><i class="fas fa-bullhorn me-1"></i>Announcements</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'discussions' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=discussions"><i class="fas fa-comments me-1"></i>Discussions</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'live' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=live"><i class="fas fa-video me-1"></i>Live Class</a></li>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Class Info</div>
            <div class="card-body">
                <p><strong>Subject:</strong> <?= sanitizeInput($vc['subject_name']) ?> (<?= sanitizeInput($vc['subject_code']) ?>)</p>
                <p><strong>Teacher:</strong> <?= sanitizeInput($vc['t_first'] . ' ' . $vc['t_last']) ?></p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-bullhorn me-2"></i>Latest Announcements</div>
            <div class="card-body">
                <?php $anns = $announcements->fetchAll(); if (empty($anns)): ?>
                <p class="text-muted mb-0">No announcements.</p>
                <?php else: foreach (array_slice($anns, 0, 3) as $an): ?>
                <div class="border-bottom pb-2 mb-2">
                    <strong><?= sanitizeInput($an['title']) ?></strong>
                    <small class="text-muted ms-2"><?= $an['created_at'] ?></small>
                    <p class="mb-0 small"><?= nl2br(sanitizeInput($an['content'])) ?></p>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-calendar-check me-2"></i>Attendance</div>
            <div class="card-body text-center">
                <div class="display-4 fw-bold text-<?= ($att['total'] > 0 && ($att['present'] / $att['total']) >= 0.75) ? 'success' : 'warning' ?>">
                    <?= $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100) : 0 ?>%
                </div>
                <p class="text-muted small">Present: <?= $att['present'] ?> / <?= $att['total'] ?> days</p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-tasks me-2"></i>Assignments</div>
            <div class="card-body">
                <?php $asns = $assignments->fetchAll(); $pending = 0; $graded = 0;
                foreach ($asns as $a) { if ($a['sub_status'] === 'graded') $graded++; elseif ($a['sub_status'] === 'submitted') $pending++; } ?>
                <p class="mb-1">Graded: <strong><?= $graded ?></strong></p>
                <p class="mb-0">Pending: <strong><?= $pending ?></strong></p>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'materials'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-file me-2"></i>Lesson Materials</div>
    <div class="card-body p-0">
        <?php $mats = $materials->fetchAll(); if (empty($mats)): ?>
        <div class="text-center text-muted py-4">No materials uploaded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Title</th><th>Type</th><th>Description</th><th>Download</th></tr></thead>
                <tbody>
                    <?php foreach ($mats as $m): ?>
                    <tr>
                        <td><?= sanitizeInput($m['title']) ?></td>
                        <td><span class="badge bg-info"><?= $m['material_type'] ?></span></td>
                        <td><small><?= sanitizeInput($m['description']) ?></small></td>
                        <td><?= $m['file_path'] ? '<a href="' . BASE_URL . '/' . $m['file_path'] . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Download</a>' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'assignments'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-tasks me-2"></i>Assignments</div>
    <div class="card-body p-0">
        <?php $asns = $assignments->fetchAll(); if (empty($asns)): ?>
        <div class="text-center text-muted py-4">No assignments yet.</div>
        <?php else: foreach ($asns as $a): ?>
        <div class="border-bottom p-3">
            <div class="d-flex justify-content-between">
                <h6 class="fw-bold mb-1"><?= sanitizeInput($a['title']) ?></h6>
                <span class="badge bg-<?= !$a['sub_status'] ? 'secondary' : ($a['sub_status'] === 'graded' ? 'success' : 'warning') ?>">
                    <?= !$a['sub_status'] ? 'Not Submitted' : ($a['sub_status'] === 'graded' ? 'Graded: ' . $a['score'] . '/' . $a['max_score'] : 'Submitted') ?>
                </span>
            </div>
            <?php if ($a['description']): ?><p class="small mb-1"><?= sanitizeInput($a['description']) ?></p><?php endif; ?>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($a['file_path']): ?><a href="<?= BASE_URL ?>/<?= $a['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-paperclip"></i> Attachment</a><?php endif; ?>
                <small class="text-muted">Due: <?= $a['due_date'] ?? 'No deadline' ?></small>
                <?php if ($a['sub_status'] === 'graded' && $a['feedback']): ?>
                <small class="text-muted">Feedback: <?= sanitizeInput($a['feedback']) ?></small>
                <?php endif; ?>
            </div>
            <?php if (!$a['sub_status'] || $a['sub_status'] === 'submitted'): ?>
            <button class="btn btn-sm btn-outline-primary mt-2" onclick="submitAssignment(<?= $a['id'] ?>)"><i class="fas fa-upload me-1"></i><?= $a['sub_status'] ? 'Resubmit' : 'Submit' ?></button>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title">Submit Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="assignment_id" id="submit_assignment_id">
                <div class="mb-3"><label class="form-label">Text Response</label><textarea name="submission_text" class="form-control" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label">File Upload</label><input type="file" name="file" class="form-control"></div>
            </div>
            <div class="modal-footer"><input type="hidden" name="submit_assignment" value="1"><button type="submit" class="btn btn-primary">Submit</button></div>
        </form>
    </div></div>
</div>

<script>
function submitAssignment(id) {
    document.getElementById('submit_assignment_id').value = id;
    new bootstrap.Modal(document.getElementById('submitModal')).show();
}
</script>

<?php elseif ($tab === 'announcements'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-bullhorn me-2"></i>Announcements</div>
    <div class="card-body">
        <?php $anns = $announcements->fetchAll(); if (empty($anns)): ?>
        <p class="text-muted mb-0">No announcements.</p>
        <?php else: foreach ($anns as $an): ?>
        <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between">
                <h6 class="fw-bold mb-1"><?= sanitizeInput($an['title']) ?></h6>
                <small class="text-muted"><?= $an['created_at'] ?></small>
            </div>
            <p class="mb-0"><?= nl2br(sanitizeInput($an['content'])) ?></p>
            <small class="text-muted">— <?= sanitizeInput($an['first_name'] . ' ' . $an['last_name']) ?></small>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php elseif ($tab === 'discussions'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-comments me-2"></i>Discussions</div>
    <div class="card-body">
        <form method="POST" class="mb-4">
            <div class="mb-2"><label class="form-label">Start a Discussion</label>
                <textarea name="content" class="form-control" rows="2" required placeholder="Type your question or comment..."></textarea>
            </div>
            <button type="submit" name="post_discussion" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>Post</button>
        </form>

        <?php $discs = $discussions->fetchAll(); if (empty($discs)): ?>
        <p class="text-muted">No discussions yet. Start one above!</p>
        <?php else: foreach ($discs as $d):
            $replies = $db->prepare("SELECT cd.*, u.first_name, u.last_name FROM class_discussions cd JOIN users u ON cd.user_id = u.id WHERE cd.parent_id = ? ORDER BY cd.created_at");
            $replies->execute([$d['id']]);
        ?>
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between">
                <strong><?= sanitizeInput($d['first_name'] . ' ' . $d['last_name']) ?></strong>
                <small class="text-muted"><?= $d['created_at'] ?></small>
            </div>
            <p class="mb-2"><?= nl2br(sanitizeInput($d['content'])) ?></p>
            <?php foreach ($replies as $r): ?>
            <div class="ms-4 border-start ps-3 mt-2">
                <strong><small><?= sanitizeInput($r['first_name'] . ' ' . $r['last_name']) ?></small></strong>
                <small class="text-muted"><?= $r['created_at'] ?></small>
                <p class="mb-0 small"><?= nl2br(sanitizeInput($r['content'])) ?></p>
            </div>
            <?php endforeach; ?>
            <button class="btn btn-sm btn-outline-secondary mt-1" onclick="replyTo(<?= $d['id'] ?>)"><i class="fas fa-reply me-1"></i>Reply</button>
            <form method="POST" class="mt-2 d-none" id="replyForm_<?= $d['id'] ?>">
                <input type="hidden" name="parent_id" value="<?= $d['id'] ?>">
                <div class="input-group">
                    <input type="text" name="content" class="form-control form-control-sm" placeholder="Write a reply..." required>
                    <button type="submit" name="post_discussion" class="btn btn-sm btn-primary"><i class="fas fa-reply"></i></button>
                </div>
            </form>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
function replyTo(id) {
    var form = document.getElementById('replyForm_' + id);
    form.classList.toggle('d-none');
}
</script>
<?php elseif ($tab === 'live'):
    $activeSession = $db->prepare("SELECT * FROM class_schedule WHERE virtual_class_id = ? AND scheduled_date = CURDATE() AND is_live = 1 ORDER BY start_time DESC LIMIT 1");
    $activeSession->execute([$classroomId]);
    $active = $activeSession->fetch();
?>
<div class="card">
    <div class="card-header"><i class="fas fa-video me-2"></i>Live Class</div>
    <div class="card-body">
        <?php if ($active && $active['meeting_link']): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle text-danger me-2"></i>Live session active: <strong><?= sanitizeInput($active['title']) ?></strong>
        </div>
        <div style="height:500px;border:1px solid #ddd;border-radius:8px;overflow:hidden">
            <iframe src="<?= $active['meeting_link'] ?>#config.disableDeepLinking=true&userInfo.displayName=<?= urlencode(($_SESSION['first_name'] ?? 'Student') . ' ' . ($_SESSION['last_name'] ?? '')) ?>" style="width:100%;height:100%;border:none" allow="camera;microphone;fullscreen;display-capture"></iframe>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-video-slash fa-4x text-muted mb-3"></i>
            <p class="text-muted mb-0">No live session active right now.</p>
            <p class="small text-muted">Check back when your teacher starts a live class.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
