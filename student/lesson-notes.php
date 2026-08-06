<?php
require_once __DIR__ . '/../config/session.php';
requireRole('student');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Lesson Notes';
$db = getDB();
$userId = (int)$_SESSION['user_id'];

$studentStmt = $db->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch();
if (!$student) { redirect('/student/index.php'); }

$currentTerm = getCurrentTerm();
$termId = (int)($currentTerm['id'] ?? 0);

/* Only published notes for the student's class in the current term. */
$sql = "SELECT ln.id, ln.topic, ln.summary, ln.file_path, ln.week, ln.created_at,
               sub.name AS subject_name, c.name AS class_name, c.section,
               u.first_name, u.last_name
        FROM lesson_notes ln
        JOIN subjects sub ON ln.subject_id = sub.id
        JOIN classes c ON ln.class_id = c.id
        JOIN users u ON ln.teacher_id = u.id
        WHERE ln.status = 'published' AND ln.class_id = ? AND ln.term_id = ?
        ORDER BY sub.name, ln.week, ln.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute([(int)$student['class_id'], $termId]);
$notes = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-sticky-note me-2"></i>Lesson Notes</h4>
        <p class="text-muted small mb-0">Notes published by your teachers for the current term.</p>
    </div>
</div>

<?php if (empty($notes)): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>No lesson notes have been published for your class in the current term yet.</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($notes as $n): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-primary"><?= sanitizeInput($n['subject_name']) ?></span>
                    <?php if ($n['week']): ?><span class="badge bg-light text-dark">Week <?= (int)$n['week'] ?></span><?php endif; ?>
                </div>
                <h5 class="card-title mb-1"><?= sanitizeInput($n['topic']) ?></h5>
                <p class="small text-muted mb-2">
                    <i class="fas fa-user me-1"></i><?= sanitizeInput($n['first_name'] . ' ' . $n['last_name']) ?>
                    &middot; <i class="fas fa-calendar me-1"></i><?= timeAgo($n['created_at']) ?>
                </p>
                <?php if ($n['summary']): ?>
                <p class="card-text small flex-grow-1"><?= sanitizeInput(mb_substr($n['summary'], 0, 160)) ?><?= mb_strlen($n['summary']) > 160 ? '…' : '' ?></p>
                <?php else: ?>
                <p class="card-text small text-muted flex-grow-1">Click to read the full note.</p>
                <?php endif; ?>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-primary view-note" data-id="<?= (int)$n['id'] ?>" data-topic="<?= sanitizeInput($n['topic']) ?>"><i class="fas fa-book-open me-1"></i>Read</button>
                    <?php if ($n['file_path']): ?>
                    <a href="/<?= $n['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>File</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
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
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="viewMeta"></span>
                <a href="#" class="btn btn-primary d-none" id="viewDownload" target="_blank"><i class="fas fa-download me-1"></i>Download File</a>
            </div>
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
                var meta = [];
                if (data.subject) meta.push(data.subject);
                if (data.week) meta.push('Week ' + data.week);
                if (data.term) meta.push(data.term);
                if (data.session) meta.push(data.session);
                document.getElementById('viewMeta').textContent = meta.join(' \u00b7 ');
                var dl = document.getElementById('viewDownload');
                if (data.file_path) {
                    dl.href = '/' + data.file_path;
                    dl.classList.remove('d-none');
                } else {
                    dl.classList.add('d-none');
                }
                new bootstrap.Modal(document.getElementById('viewModal')).show();
            })
            .catch(function () { alert('Unable to load the note. Please try again.'); });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
