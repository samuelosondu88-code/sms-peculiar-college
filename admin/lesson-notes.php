<?php
require_once __DIR__ . '/../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Lesson Notes';
$db = getDB();

$filterStatus = sanitizeInput($_GET['status'] ?? '');
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterClass = (int)($_GET['class_id'] ?? 0);
$search = sanitizeInput($_GET['search'] ?? '');

$sql = "SELECT ln.*, sub.name AS subject_name, c.name AS class_name, c.section,
               u.first_name, u.last_name, sess.session_name, t.term_name,
               lp.topic AS plan_topic
        FROM lesson_notes ln
        JOIN subjects sub ON ln.subject_id = sub.id
        JOIN classes c ON ln.class_id = c.id
        JOIN users u ON ln.teacher_id = u.id
        LEFT JOIN academic_sessions sess ON ln.academic_session_id = sess.id
        LEFT JOIN terms t ON ln.term_id = t.id
        LEFT JOIN lesson_plans lp ON ln.lesson_plan_id = lp.id
        WHERE 1=1";
$params = [];
if (in_array($filterStatus, ['draft', 'published', 'archived'], true)) { $sql .= " AND ln.status = ?"; $params[] = $filterStatus; }
if ($filterSubject) { $sql .= " AND ln.subject_id = ?"; $params[] = $filterSubject; }
if ($filterClass) { $sql .= " AND ln.class_id = ?"; $params[] = $filterClass; }
if ($search) { $sql .= " AND (ln.topic LIKE ? OR ln.summary LIKE ?)"; $like = "%$search%"; $params[] = $like; $params[] = $like; }
$sql .= " ORDER BY ln.created_at DESC LIMIT 500";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name, section")->fetchAll();
$subjects = $db->query("SELECT s.id, s.name, c.name AS class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id ORDER BY c.name, s.name")->fetchAll();

$counts = ['total' => count($notes), 'draft' => 0, 'published' => 0, 'archived' => 0];
foreach ($notes as $n) { $counts[$n['status']] = ($counts[$n['status']] ?? 0) + 1; }

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-sticky-note me-2"></i>Lesson Notes</h4>
        <p class="text-muted small mb-0">All lesson notes across classes, subjects and teachers.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card stat-primary"><i class="fas fa-sticky-note stat-icon"></i><div class="stat-value"><?= $counts['total'] ?></div><div class="stat-label">Total</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-warning"><i class="fas fa-pen stat-icon"></i><div class="stat-value"><?= $counts['draft'] ?></div><div class="stat-label">Drafts</div></div></div>
    <div class="col-md-3"><div class="stat-card stat-success"><i class="fas fa-check-circle stat-icon"></i><div class="stat-value"><?= $counts['published'] ?></div><div class="stat-label">Published</div></div></div>
    <div class="col-md-3"><div class="stat-card" style="background: linear-gradient(135deg,#64748b,#475569);"><i class="fas fa-archive stat-icon"></i><div class="stat-value"><?= $counts['archived'] ?></div><div class="stat-label">Archived</div></div></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Topic or summary..." value="<?= sanitizeInput($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass === (int)$c['id'] ? 'selected' : '' ?>><?= sanitizeInput(className($c['name'], $c['section'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSubject === (int)$s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name'] . ' - ' . className($s['class_name'], $s['section'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Topic</th>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Teacher</th>
                        <th>Week</th>
                        <th>Term / Session</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $n): ?>
                    <tr>
                        <td class="fw-semibold"><?= sanitizeInput($n['topic']) ?>
                            <?php if (!empty($n['plan_topic'])): ?><span class="badge bg-light text-dark ms-1" title="Linked lesson plan"><i class="fas fa-link"></i></span><?php endif; ?>
                            <?php if ($n['is_ai_generated']): ?><span class="badge bg-info ms-1">AI</span><?php endif; ?>
                        </td>
                        <td><?= sanitizeInput($n['subject_name']) ?></td>
                        <td><?= sanitizeInput(className($n['class_name'], $n['section'])) ?></td>
                        <td><?= sanitizeInput($n['first_name'] . ' ' . $n['last_name']) ?></td>
                        <td>Week <?= (int)$n['week'] ?: '-' ?></td>
                        <td><small><?= sanitizeInput($n['term_name'] ?? '') ?><?= $n['term_name'] && $n['session_name'] ? ' / ' : '' ?><?= sanitizeInput($n['session_name'] ?? '') ?></small></td>
                        <td>
                            <?php $b = ['draft' => 'warning', 'published' => 'success', 'archived' => 'secondary']; ?>
                            <span class="badge bg-<?= $b[$n['status']] ?? 'secondary' ?>"><?= ucfirst($n['status']) ?></span>
                        </td>
                        <td><small class="text-muted"><?= timeAgo($n['created_at']) ?></small></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item view-note" data-id="<?= (int)$n['id'] ?>" data-topic="<?= sanitizeInput($n['topic']) ?>"><i class="fas fa-eye me-1"></i>View</button></li>
                                    <?php if ($n['file_path']): ?>
                                    <li><a class="dropdown-item" href="/<?= $n['file_path'] ?>" target="_blank"><i class="fas fa-download me-1"></i>Download File</a></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($notes)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No lesson notes found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewBody"></div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.view-note').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        var topic = btn.getAttribute('data-topic');
        fetch('<?= BASE_URL ?>/api/lesson-note-detail.php?id=' + id)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { alert(data.error); return; }
                document.getElementById('viewTitle').textContent = topic;
                document.getElementById('viewBody').innerHTML = data.content || '<p class="text-muted">No content.</p>';
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            })
            .catch(function () { alert('Unable to load the note.'); });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
