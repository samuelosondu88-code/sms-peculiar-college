<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Classroom';
$db = getDB();
$teacherId = getTeacherId();
$classroomId = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'overview';
$msg = '';
$msgType = 'success';

$classroom = $db->prepare("
    SELECT vc.*, s.name as subject_name, s.code as subject_code,
           c.name as class_name, c.section
    FROM virtual_classes vc
    JOIN subjects s ON vc.subject_id = s.id
    JOIN classes c ON vc.class_id = c.id
    WHERE vc.id = ? AND vc.teacher_id = ?
");
$classroom->execute([$classroomId, $teacherId]);
$vc = $classroom->fetch();
if (!$vc) redirect('/teacher/classroom/index.php');

$students = $db->prepare("
    SELECT s.id, s.admission_no, u.first_name, u.last_name, ce.enrolled_at
    FROM class_enrollments ce
    JOIN students s ON ce.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE ce.virtual_class_id = ? AND ce.status = 'active'
    ORDER BY u.last_name, u.first_name
");
$students->execute([$classroomId]);
$enrolled = $students->fetchAll();

// Handle materials upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $desc = sanitizeInput($_POST['description'] ?? '');
    $type = $_POST['material_type'] ?? 'lesson_note';
    $filePath = '';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $name = 'mat_' . uniqid() . '.' . $ext;
        $dest = __DIR__ . '/../../uploads/classroom/materials/' . $name;
        move_uploaded_file($_FILES['file']['tmp_name'], $dest);
        $filePath = 'uploads/classroom/materials/' . $name;
    }

    if ($title) {
        $db->prepare("INSERT INTO class_materials (virtual_class_id, title, description, file_path, file_type, material_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$classroomId, $title, $desc, $filePath, $ext ?? '', $type, $_SESSION['user_id']]);
        $msg = 'Material uploaded.';
    }
}

// Handle delete material
if (isset($_GET['delete_material'])) {
    $mid = (int)$_GET['delete_material'];
    $mat = $db->prepare("SELECT file_path FROM class_materials WHERE id = ? AND virtual_class_id = ?");
    $mat->execute([$mid, $classroomId]);
    $m = $mat->fetch();
    if ($m && $m['file_path'] && file_exists(__DIR__ . '/../../' . $m['file_path'])) {
        unlink(__DIR__ . '/../../' . $m['file_path']);
    }
    $db->prepare("DELETE FROM class_materials WHERE id = ? AND virtual_class_id = ?")->execute([$mid, $classroomId]);
    redirect("/teacher/classroom/view.php?id=$classroomId&tab=materials");
}

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $desc = sanitizeInput($_POST['description'] ?? '');
    $maxScore = (float)($_POST['max_score'] ?? 100);
    $dueDate = $_POST['due_date'] ?? null;
    $filePath = '';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $name = 'asn_' . uniqid() . '.' . $ext;
        $dest = __DIR__ . '/../../uploads/classroom/assignments/' . $name;
        move_uploaded_file($_FILES['file']['tmp_name'], $dest);
        $filePath = 'uploads/classroom/assignments/' . $name;
    }

    if ($title) {
        $db->prepare("INSERT INTO class_assignments (virtual_class_id, title, description, file_path, max_score, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$classroomId, $title, $desc, $filePath, $maxScore, $dueDate ?: null, $_SESSION['user_id']]);
        $msg = 'Assignment created.';
    }
}

// Handle grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    $subId = (int)($_POST['submission_id'] ?? 0);
    $score = (float)($_POST['score'] ?? 0);
    $feedback = sanitizeInput($_POST['feedback'] ?? '');
    $db->prepare("UPDATE assignment_submissions SET score = ?, feedback = ?, graded_by = ?, status = 'graded', graded_at = NOW() WHERE id = ? AND assignment_id IN (SELECT id FROM class_assignments WHERE virtual_class_id = ?)")
        ->execute([$score, $feedback, $_SESSION['user_id'], $subId, $classroomId]);
    redirect("/teacher/classroom/view.php?id=$classroomId&tab=assignments");
}

// Handle announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    if ($title && $content) {
        $db->prepare("INSERT INTO class_announcements (virtual_class_id, title, content, created_by) VALUES (?, ?, ?, ?)")
            ->execute([$classroomId, $title, $content, $_SESSION['user_id']]);
        redirect("/teacher/classroom/view.php?id=$classroomId&tab=announcements");
    }
}

// Handle attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    foreach ($_POST['status'] ?? [] as $sid => $status) {
        $sid = (int)$sid;
        if (!in_array($status, ['present','absent','late'])) continue;
        $db->prepare("INSERT INTO class_attendance (virtual_class_id, student_id, date, status, marked_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?")
            ->execute([$classroomId, $sid, $date, $status, $_SESSION['user_id'], $status]);
    }
    redirect("/teacher/classroom/view.php?id=$classroomId&tab=attendance");
}

$materials = $db->prepare("SELECT * FROM class_materials WHERE virtual_class_id = ? ORDER BY created_at DESC");
$materials->execute([$classroomId]);

$assignments = $db->prepare("SELECT * FROM class_assignments WHERE virtual_class_id = ? ORDER BY created_at DESC");
$assignments->execute([$classroomId]);

$announcements = $db->prepare("SELECT ca.*, u.first_name, u.last_name FROM class_announcements ca JOIN users u ON ca.created_by = u.id WHERE ca.virtual_class_id = ? ORDER BY ca.created_at DESC");
$announcements->execute([$classroomId]);

$discussions = $db->prepare("SELECT cd.*, u.first_name, u.last_name FROM class_discussions cd JOIN users u ON cd.user_id = u.id WHERE cd.virtual_class_id = ? AND cd.parent_id IS NULL ORDER BY cd.created_at DESC LIMIT 50");
$discussions->execute([$classroomId]);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="index.php" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <h4 class="fw-bold mb-0 mt-1"><i class="fas fa-chalkboard me-2"></i><?= sanitizeInput($vc['name']) ?></h4>
        <small class="text-muted"><?= sanitizeInput($vc['subject_name']) ?> — <?= sanitizeInput($vc['class_name'] . ' ' . $vc['section']) ?> | Code: <strong><?= $vc['code'] ?></strong></small>
    </div>
    <div>
        <span class="badge bg-secondary"><?= count($enrolled) ?> Students</span>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'overview' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=overview"><i class="fas fa-home me-1"></i>Overview</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'materials' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=materials"><i class="fas fa-file me-1"></i>Materials</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'assignments' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=assignments"><i class="fas fa-tasks me-1"></i>Assignments</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'announcements' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=announcements"><i class="fas fa-bullhorn me-1"></i>Announcements</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'attendance' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=attendance"><i class="fas fa-calendar-check me-1"></i>Attendance</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'live' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=live"><i class="fas fa-video me-1"></i>Live Class</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'students' ? 'active' : '' ?>" href="?id=<?= $classroomId ?>&tab=students"><i class="fas fa-users me-1"></i>Students</a></li>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-3">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Class Info</div>
            <div class="card-body">
                <p><strong>Subject:</strong> <?= sanitizeInput($vc['subject_name']) ?> (<?= sanitizeInput($vc['subject_code']) ?>)</p>
                <p><strong>Class:</strong> <?= sanitizeInput($vc['class_name'] . ' ' . $vc['section']) ?></p>
                <p><strong>Enrollment Code:</strong> <span class="badge bg-dark fs-6"><?= $vc['code'] ?></span> <small class="text-muted">(Share with students to join)</small></p>
                <?php if ($vc['description']): ?>
                <p><strong>Description:</strong> <?= sanitizeInput($vc['description']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-comments me-2"></i>Recent Discussions</div>
            <div class="card-body">
                <?php $discs = $discussions->fetchAll(); if (empty($discs)): ?>
                <p class="text-muted mb-0">No discussions yet.</p>
                <?php else: ?>
                <?php foreach (array_slice($discs, 0, 5) as $d): ?>
                <div class="border-bottom pb-2 mb-2">
                    <strong><?= sanitizeInput($d['first_name'] . ' ' . $d['last_name']) ?></strong>
                    <small class="text-muted ms-2"><?= $d['created_at'] ?></small>
                    <p class="mb-0 small"><?= nl2br(sanitizeInput($d['content'])) ?></p>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-users me-2"></i>Students (<?= count($enrolled) ?>)</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach (array_slice($enrolled, 0, 10) as $s): ?>
                    <li class="list-group-item py-2 small"><?= sanitizeInput($s['last_name'] . ' ' . $s['first_name']) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($enrolled) > 10): ?>
                    <li class="list-group-item text-center text-muted small">+<?= count($enrolled) - 10 ?> more</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'materials'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-file me-2"></i>Lesson Materials</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#materialModal"><i class="fas fa-upload me-1"></i>Upload</button>
    </div>
    <div class="card-body p-0">
        <?php $mats = $materials->fetchAll(); if (empty($mats)): ?>
        <div class="text-center text-muted py-4">No materials uploaded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Title</th><th>Type</th><th>File</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($mats as $m): ?>
                    <tr>
                        <td><?= sanitizeInput($m['title']) ?></td>
                        <td><span class="badge bg-info"><?= $m['material_type'] ?></span></td>
                        <td><?= $m['file_path'] ? '<a href="' . BASE_URL . '/' . $m['file_path'] . '" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>' : '-' ?></td>
                        <td><small><?= $m['created_at'] ?></small></td>
                        <td><a href="?id=<?= $classroomId ?>&tab=materials&delete_material=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="materialModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title">Upload Material</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Type</label><select name="material_type" class="form-select"><option value="lesson_note">Lesson Note</option><option value="video">Video</option><option value="document">Document</option><option value="presentation">Presentation</option><option value="reference">Reference</option></select></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">File</label><input type="file" name="file" class="form-control"></div>
            </div>
            <div class="modal-footer"><input type="hidden" name="upload_material" value="1"><button type="submit" class="btn btn-primary">Upload</button></div>
        </form>
    </div></div>
</div>

<?php elseif ($tab === 'assignments'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tasks me-2"></i>Assignments</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignmentModal"><i class="fas fa-plus me-1"></i>Create</button>
    </div>
    <div class="card-body p-0">
        <?php $asns = $assignments->fetchAll(); if (empty($asns)): ?>
        <div class="text-center text-muted py-4">No assignments yet.</div>
        <?php else: foreach ($asns as $a):
            $subs = $db->prepare("SELECT asub.*, u.first_name, u.last_name, s.admission_no FROM assignment_submissions asub JOIN students s ON asub.student_id = s.id JOIN users u ON s.user_id = u.id WHERE asub.assignment_id = ? ORDER BY u.last_name");
            $subs->execute([$a['id']]);
            $allSubs = $subs->fetchAll();
        ?>
        <div class="border-bottom p-3">
            <div class="d-flex justify-content-between">
                <h6 class="fw-bold mb-1"><?= sanitizeInput($a['title']) ?></h6>
                <small class="text-muted">Max: <?= $a['max_score'] ?> | Due: <?= $a['due_date'] ?? 'No deadline' ?></small>
            </div>
            <?php if ($a['description']): ?><p class="small mb-1"><?= sanitizeInput($a['description']) ?></p><?php endif; ?>
            <?php if ($a['file_path']): ?><a href="<?= BASE_URL ?>/<?= $a['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-2"><i class="fas fa-paperclip me-1"></i>Attachment</a><?php endif; ?>

            <?php if (!empty($allSubs)): ?>
            <table class="table table-sm table-bordered mb-0 mt-2">
                <thead><tr><th>Student</th><th>Status</th><th>Score</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($allSubs as $sub): ?>
                    <tr>
                        <td><small><?= sanitizeInput($sub['last_name'] . ' ' . $sub['first_name']) ?></small></td>
                        <td><span class="badge bg-<?= $sub['status'] === 'graded' ? 'success' : 'warning' ?>"><?= $sub['status'] ?></span></td>
                        <td><?= $sub['score'] !== null ? $sub['score'] . '/' . $a['max_score'] : '-' ?></td>
                        <td>
                            <?php if ($sub['status'] !== 'graded'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="gradeSubmission(<?= $sub['id'] ?>, <?= $a['max_score'] ?>)"><i class="fas fa-check me-1"></i>Grade</button>
                            <?php else: ?>
                            <small class="text-muted"><?= sanitizeInput($sub['feedback']) ?></small>
                            <?php endif; ?>
                            <?php if ($sub['file_path']): ?>
                            <a href="<?= BASE_URL ?>/<?= $sub['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted small mb-0">No submissions yet.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="modal fade" id="assignmentModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-header"><h5 class="modal-title">Create Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
                <div class="mb-3"><label class="form-label">Max Score</label><input type="number" name="max_score" class="form-control" value="100" min="1"></div>
                <div class="mb-3"><label class="form-label">Due Date</label><input type="datetime-local" name="due_date" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Attachment</label><input type="file" name="file" class="form-control"></div>
            </div>
            <div class="modal-footer"><input type="hidden" name="create_assignment" value="1"><button type="submit" class="btn btn-primary">Create</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Grade Submission</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="submission_id" id="grade_submission_id">
                <div class="mb-3"><label class="form-label">Score</label><input type="number" name="score" id="grade_score" class="form-control" min="0" step="0.5" required></div>
                <div class="mb-3"><label class="form-label">Feedback</label><textarea name="feedback" class="form-control" rows="3"></textarea></div>
            </div>
            <div class="modal-footer"><input type="hidden" name="grade_submission" value="1"><button type="submit" class="btn btn-primary">Save Grade</button></div>
        </form>
    </div></div>
</div>

<script>
function gradeSubmission(subId, maxScore) {
    document.getElementById('grade_submission_id').value = subId;
    document.getElementById('grade_score').max = maxScore;
    new bootstrap.Modal(document.getElementById('gradeModal')).show();
}
</script>

<?php elseif ($tab === 'announcements'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-bullhorn me-2"></i>Announcements</span>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#announcementModal"><i class="fas fa-plus me-1"></i>Post</button>
    </div>
    <div class="card-body">
        <?php $anns = $announcements->fetchAll(); if (empty($anns)): ?>
        <p class="text-muted mb-0">No announcements yet.</p>
        <?php else: foreach ($anns as $an): ?>
        <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between">
                <h6 class="fw-bold mb-1"><?= sanitizeInput($an['title']) ?></h6>
                <small class="text-muted"><?= $an['created_at'] ?> by <?= sanitizeInput($an['first_name'] . ' ' . $an['last_name']) ?></small>
            </div>
            <p class="mb-0"><?= nl2br(sanitizeInput($an['content'])) ?></p>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <div class="modal-header"><h5 class="modal-title">Post Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Content</label><textarea name="content" class="form-control" rows="4" required></textarea></div>
            </div>
            <div class="modal-footer"><input type="hidden" name="post_announcement" value="1"><button type="submit" class="btn btn-primary">Post</button></div>
        </form>
    </div></div>
</div>

<?php elseif ($tab === 'attendance'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-calendar-check me-2"></i>Take Attendance</div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d') ?>" style="max-width:200px">
            </div>
            <?php if (empty($enrolled)): ?>
            <p class="text-muted">No enrolled students.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>Late</th></tr></thead>
                    <tbody>
                        <?php foreach ($enrolled as $s):
                            $today = $db->prepare("SELECT status FROM class_attendance WHERE virtual_class_id = ? AND student_id = ? AND date = ?");
                            $today->execute([$classroomId, $s['id'], date('Y-m-d')]);
                            $cur = $today->fetchColumn();
                        ?>
                        <tr>
                            <td><?= sanitizeInput($s['last_name'] . ' ' . $s['first_name']) ?></td>
                            <td><input type="radio" name="status[<?= $s['id'] ?>]" value="present" <?= (!$cur || $cur === 'present') ? 'checked' : '' ?>></td>
                            <td><input type="radio" name="status[<?= $s['id'] ?>]" value="absent" <?= $cur === 'absent' ? 'checked' : '' ?>></td>
                            <td><input type="radio" name="status[<?= $s['id'] ?>]" value="late" <?= $cur === 'late' ? 'checked' : '' ?>></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="save_attendance" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Attendance</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Attendance Summary</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>Late</th><th>%</th></tr></thead>
                <tbody>
                    <?php foreach ($enrolled as $s):
                        $stats = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late FROM class_attendance WHERE virtual_class_id = ? AND student_id = ?");
                        $stats->execute([$classroomId, $s['id']]);
                        $st = $stats->fetch();
                        $pct = $st['total'] > 0 ? round(($st['present'] / $st['total']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><?= sanitizeInput($s['last_name'] . ' ' . $s['first_name']) ?></td>
                        <td><?= $st['present'] ?></td>
                        <td><?= $st['absent'] ?></td>
                        <td><?= $st['late'] ?></td>
                        <td><span class="badge bg-<?= $pct >= 75 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') ?>"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'live'):
    $activeSession = $db->prepare("SELECT * FROM class_schedule WHERE virtual_class_id = ? AND scheduled_date = CURDATE() ORDER BY start_time DESC LIMIT 1");
    $activeSession->execute([$classroomId]);
    $active = $activeSession->fetch();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_live'])) {
        $title = 'Live Class - ' . date('Y-m-d H:i');
        $roomName = 'sms-pc-' . $classroomId . '-' . uniqid();
        $meetingLink = 'https://meet.jit.si/' . $roomName;
        $db->prepare("INSERT INTO class_schedule (virtual_class_id, title, scheduled_date, start_time, end_time, meeting_link, is_live, created_by) VALUES (?, ?, CURDATE(), CURTIME(), ADDTIME(CURTIME(), '01:00:00'), ?, 1, ?)")
            ->execute([$classroomId, $title, $meetingLink, $_SESSION['user_id']]);
        $active = ['meeting_link' => $meetingLink, 'title' => $title];
        $msg = 'Live class started!';
    }

    $pastSessions = $db->prepare("SELECT * FROM class_schedule WHERE virtual_class_id = ? AND scheduled_date < CURDATE() ORDER BY scheduled_date DESC, start_time DESC LIMIT 10");
    $pastSessions->execute([$classroomId]);
?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-video me-2"></i>Live Class</span>
        <?php if (!$active): ?>
        <form method="POST" style="display:inline">
            <button type="submit" name="start_live" class="btn btn-success btn-sm"><i class="fas fa-play me-1"></i>Start Live Class</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($active && $active['meeting_link']): ?>
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <span><i class="fas fa-circle text-danger me-2"></i>Live: <strong><?= sanitizeInput($active['title']) ?></strong></span>
            <a href="<?= $active['meeting_link'] ?>" target="_blank" class="btn btn-primary btn-sm"><i class="fas fa-external-link-alt me-1"></i>Open in New Tab</a>
        </div>
        <div style="height:500px;border:1px solid #ddd;border-radius:8px;overflow:hidden">
            <iframe src="<?= $active['meeting_link'] ?>#config.disableDeepLinking=true&userInfo.displayName=<?= urlencode(($_SESSION['first_name'] ?? 'Teacher') . ' ' . ($_SESSION['last_name'] ?? '')) ?>" style="width:100%;height:100%;border:none" allow="camera;microphone;fullscreen;display-capture"></iframe>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No active live session. Click <strong>"Start Live Class"</strong> to begin.</p>
        <p class="small text-muted mt-2">Jitsi Meet provides video, audio, screen sharing, chat, and whiteboard — no account needed.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($pastSessions->rowCount() > 0): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-history me-2"></i>Past Live Sessions</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Title</th><th>Date</th><th>Time</th></tr></thead>
                <tbody>
                    <?php foreach ($pastSessions as $ps): ?>
                    <tr>
                        <td><?= sanitizeInput($ps['title']) ?></td>
                        <td><?= $ps['scheduled_date'] ?></td>
                        <td><?= $ps['start_time'] ?> - <?= $ps['end_time'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'students'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-users me-2"></i>Enrolled Students (<?= count($enrolled) ?>)</div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Share this code with students to join: <strong class="fs-5"><?= $vc['code'] ?></strong>
        </div>
        <?php if (empty($enrolled)): ?>
        <p class="text-muted">No students have joined yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Admission No</th><th>Name</th><th>Enrolled</th></tr></thead>
                <tbody>
                    <?php foreach ($enrolled as $s): ?>
                    <tr>
                        <td><?= sanitizeInput($s['admission_no']) ?></td>
                        <td><?= sanitizeInput($s['last_name'] . ' ' . $s['first_name']) ?></td>
                        <td><small><?= $s['enrolled_at'] ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
