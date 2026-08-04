<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Report Discipline';
$db = getDB();
$msg = '';
$msgType = 'success';

$teacherId = $_SESSION['user_id'];
$teacher = $db->prepare("SELECT id FROM teachers WHERE user_id = ?");
$teacher->execute([$teacherId]);
$tRow = $teacher->fetch();
$teacherDbId = $tRow ? $tRow['id'] : 0;

$assignedClasses = $db->prepare("SELECT DISTINCT c.id, c.name FROM classes c JOIN subject_allocations sa ON c.id = sa.class_id WHERE sa.teacher_id = ?");
$assignedClasses->execute([$teacherDbId]);
$classes = $assignedClasses->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_incident'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $incident_date = $_POST['incident_date'] ?? date('Y-m-d');
    $incident_type = sanitizeInput($_POST['incident_type'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');

    if (!$student_id || !$description) {
        $msg = 'Student and description are required.'; $msgType = 'danger';
    } else {
        $db->prepare("INSERT INTO behavior_records (student_id, incident_date, incident_type, description, reported_by, status) VALUES (?, ?, ?, ?, ?, 'open')")->execute([$student_id, $incident_date, $incident_type, $description, $_SESSION['user_id']]);
        $msg = 'Incident reported successfully.';
        logActivity($_SESSION['user_id'], 'report_discipline', 'behavior_records', (int)$db->lastInsertId());
    }
}

$myReports = $db->prepare("SELECT b.*, u.first_name AS sfn, u.last_name AS sln, s.admission_no, c.name AS class_name
    FROM behavior_records b
    JOIN students s ON b.student_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE b.reported_by = ?
    ORDER BY b.incident_date DESC LIMIT 50");
$myReports->execute([$_SESSION['user_id']]);
$myReports = $myReports->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Discipline Reporting</h4>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal"><i class="fas fa-plus me-1"></i>Report Incident</button>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <small class="text-muted">Click a class to load students, then report misconduct.</small>
        <?php if (count($classes)): ?>
        <div class="d-flex gap-2 flex-wrap mb-2">
            <?php foreach ($classes as $c): ?>
            <button class="btn btn-sm btn-outline-secondary load-students" data-class="<?= $c['id'] ?>"><?= sanitizeInput($c['name']) ?></button>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mb-0 py-2 small">You have no assigned classes.</div>
        <?php endif; ?>
    </div>
</div>

<h5>My Reports (<?= count($myReports) ?>)</h5>
<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead class="table-dark"><tr><th>Date</th><th>Student</th><th>Admission No</th><th>Type</th><th>Description</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($myReports as $r): ?>
            <tr>
                <td class="small"><?= date('d M Y', strtotime($r['incident_date'])) ?></td>
                <td><?= sanitizeInput($r['sfn'] . ' ' . $r['sln']) ?></td>
                <td class="small"><?= sanitizeInput($r['admission_no']) ?></td>
                <td><span class="badge bg-warning text-dark"><?= sanitizeInput($r['incident_type']) ?></span></td>
                <td class="small"><?= sanitizeInput($r['description']) ?></td>
                <td><span class="badge bg-<?= $r['status'] === 'open' ? 'danger' : ($r['status'] === 'resolved' ? 'warning' : 'secondary') ?>"><?= $r['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!count($myReports)): ?><tr><td colspan="6" class="text-center text-muted py-3">No reports yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Report Incident</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Student *</label>
                    <select name="student_id" id="studentSelect" class="form-select" required>
                        <option value="">-- Select a class first --</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Incident Date *</label>
                        <input type="date" name="incident_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Incident Type</label>
                        <select name="incident_type" class="form-select">
                            <option value="Bullying">Bullying</option>
                            <option value="Truancy">Truancy</option>
                            <option value="Disrespect">Disrespect</option>
                            <option value="Cheating">Cheating</option>
                            <option value="Fighting">Fighting</option>
                            <option value="Vandalism">Vandalism</option>
                            <option value="Late Coming">Late Coming</option>
                            <option value="Uniform Violation">Uniform Violation</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="report_incident" class="btn btn-danger"><i class="fas fa-exclamation-triangle me-1"></i>Report Incident</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.load-students').forEach(btn => {
    btn.addEventListener('click', function() {
        const classId = this.dataset.class;
        fetch('<?= BASE_URL ?>/api/students-by-class.php?class_id=' + classId)
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('studentSelect');
                sel.innerHTML = '<option value="">-- Select Student --</option>';
                data.forEach(s => {
                    sel.innerHTML += `<option value="${s.id}">${s.first_name} ${s.last_name} (${s.admission_no})</option>`;
                });
            });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
