<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Manage Results';
$db = getDB();

$sessions = $db->query("SELECT id, session_name FROM academic_sessions ORDER BY start_date DESC")->fetchAll();
$terms = $db->query("SELECT id, term_name, session_id FROM terms ORDER BY session_id, id")->fetchAll();
$classes = $db->query("SELECT id, name, section FROM classes ORDER BY name")->fetchAll();
$subjects = $db->query("SELECT id, name, code FROM subjects ORDER BY name")->fetchAll();

$selectedSession = (int)($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$selectedTerm = (int)($_GET['term_id'] ?? 0);
$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$viewAssessments = isset($_GET['view_assessments']) && $_GET['view_assessments'] == 1;
$viewStudentId = (int)($_GET['view_student'] ?? 0);

$students = [];
$classStats = [];
$assessmentData = [];

if ($selectedClass && $selectedSession && $selectedTerm) {
    $students = $db->prepare("
        SELECT s.id, u.first_name, u.last_name, s.admission_no
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.class_id = ? AND s.status = 'active'
        ORDER BY u.last_name, u.first_name
    ");
    $students->execute([$selectedClass]);
    $students = $students->fetchAll();

    if ($selectedSubject) {
        foreach ($students as &$student) {
            $stmt = $db->prepare("
                SELECT rs.*, sub.name as subject_name, sub.code as subject_code
                FROM result_scores rs
                JOIN subjects sub ON rs.subject_id = sub.id
                WHERE rs.student_id = ? AND rs.class_id = ? AND rs.subject_id = ? AND rs.session_id = ? AND rs.term_id = ?
                LIMIT 1
            ");
            $stmt->execute([$student['id'], $selectedClass, $selectedSubject, $selectedSession, $selectedTerm]);
            $student['result'] = $stmt->fetch();
        }
        unset($student);
    }

    $classStats = getClassStats($db, $selectedClass, $selectedSession, $selectedTerm);
}

if ($viewAssessments && $viewStudentId) {
    $stmt = $db->prepare("SELECT * FROM psychomotor_assessments WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
    $stmt->execute([$viewStudentId, $selectedClass, $selectedSession, $selectedTerm]);
    $psychomotor = $stmt->fetch();

    $stmt = $db->prepare("SELECT * FROM affective_assessments WHERE student_id = ? AND class_id = ? AND session_id = ? AND term_id = ?");
    $stmt->execute([$viewStudentId, $selectedClass, $selectedSession, $selectedTerm]);
    $affective = $stmt->fetch();

    $assessmentData = ['psychomotor' => $psychomotor, 'affective' => $affective];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-list-alt me-2"></i>Manage Results</h4>
    <a href="<?= BASE_URL ?>/admin/results/approve.php" class="btn btn-warning"><i class="fas fa-check-double me-1"></i>Approvals</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Session</label>
                <select name="session_id" class="form-select">
                    <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSession === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['session_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Term</label>
                <select name="term_id" class="form-select">
                    <option value="">Select Term</option>
                    <?php foreach ($terms as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTerm === $t['id'] ? 'selected' : '' ?>><?= sanitizeInput($t['term_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class_id" class="form-select">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>><?= sanitizeInput($c['name'] . ' ' . $c['section']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $sub): ?>
                    <option value="<?= $sub['id'] ?>" <?= $selectedSubject === $sub['id'] ? 'selected' : '' ?>><?= sanitizeInput($sub['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass && $selectedSession && $selectedTerm && !empty($classStats)): ?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-primary"><div class="stat-value"><?= $classStats['count'] ?></div><div class="stat-label">Students</div></div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success"><div class="stat-value"><?= $classStats['average'] ?></div><div class="stat-label">Class Average</div></div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info"><div class="stat-value"><?= $classStats['highest'] ?></div><div class="stat-label">Highest Avg</div></div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning"><div class="stat-value"><?= $classStats['pass_percent'] ?>%</div><div class="stat-label">Pass Rate</div></div>
    </div>
</div>
<?php endif; ?>

<?php if ($viewAssessments && !empty($assessmentData)): ?>
<div class="modal fade" id="assessmentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>Assessments for Student</h5>
                <a href="?session_id=<?= $selectedSession ?>&term_id=<?= $selectedTerm ?>&class_id=<?= $selectedClass ?>&subject_id=<?= $selectedSubject ?>" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Psychomotor</h6>
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <?php foreach (['creativity','sports','practical_skills','neatness','leadership'] as $field): ?>
                                <tr><td><?= ucfirst(str_replace('_', ' ', $field)) ?></td><td><?= $assessmentData['psychomotor'][$field] ?? '-' ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Affective</h6>
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <?php foreach (['honesty','punctuality','respect','cooperation','responsibility'] as $field): ?>
                                <tr><td><?= ucfirst($field) ?></td><td><?= $assessmentData['affective'][$field] ?? '-' ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="?session_id=<?= $selectedSession ?>&term_id=<?= $selectedTerm ?>&class_id=<?= $selectedClass ?>&subject_id=<?= $selectedSubject ?>" class="btn btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded', function() { new bootstrap.Modal(document.getElementById('assessmentModal')).show(); });</script>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-users me-2"></i>Student Results</span>
        <?php if (!empty($students)): ?>
        <span class="text-muted small"><?= count($students) ?> student(s)</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($selectedClass) || empty($selectedTerm)): ?>
        <div class="p-4 text-center text-muted">Select a class and term to view results.</div>
        <?php elseif (empty($students)): ?>
        <div class="p-4 text-center text-muted">No students found in this class.</div>
        <?php elseif ($selectedSubject): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Admission</th>
                        <th>Assignment</th>
                        <th>Test</th>
                        <th>Project</th>
                        <th>CA Total</th>
                        <th>Exam</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>Position</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($students as $s): $r = $s['result'] ?? null; ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?></td>
                        <td><?= sanitizeInput($s['admission_no']) ?></td>
                        <td><?= $r ? $r['assignment_score'] : '-' ?></td>
                        <td><?= $r ? $r['test_score'] : '-' ?></td>
                        <td><?= $r ? $r['project_score'] : '-' ?></td>
                        <td><?= $r ? $r['ca_total'] : '-' ?></td>
                        <td><?= $r ? $r['exam_score'] : '-' ?></td>
                        <td><strong><?= $r ? $r['total_score'] : '-' ?></strong></td>
                        <td><?= $r ? '<span class="badge bg-' . ($r['grade'] === 'A' ? 'success' : ($r['grade'] === 'B' ? 'primary' : ($r['grade'] === 'C' ? 'info' : ($r['grade'] === 'D' ? 'warning' : ($r['grade'] === 'E' ? 'secondary' : 'danger'))))) . '">' . $r['grade'] . '</span>' : '-' ?></td>
                        <td><?= $r ? $r['subject_position'] : '-' ?></td>
                        <td><?= $r ? getStatusBadge($r['status']) : '<span class="badge bg-secondary">No Score</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Admission</th>
                        <th>Avg Score</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>Pass</th>
                        <th>Class Pos</th>
                        <th>Assessments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($students as $s):
                        $summary = getStudentTermSummary($db, $s['id'], $selectedSession, $selectedTerm);
                        $position = getClassPosition($db, $s['id'], $selectedClass, $selectedSession, $selectedTerm);
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= sanitizeInput($s['last_name'] . ', ' . $s['first_name']) ?></td>
                        <td><?= sanitizeInput($s['admission_no']) ?></td>
                        <td><strong><?= $summary['average'] ?></strong></td>
                        <td><?= $summary['total_marks'] ?></td>
                        <td><?= $summary['overall_grade'] ? '<span class="badge bg-' . ($summary['overall_grade'] === 'A' ? 'success' : ($summary['overall_grade'] === 'B' ? 'primary' : ($summary['overall_grade'] === 'C' ? 'info' : ($summary['overall_grade'] === 'D' ? 'warning' : ($summary['overall_grade'] === 'E' ? 'secondary' : 'danger'))))) . '">' . $summary['overall_grade'] . '</span>' : '-' ?></td>
                        <td><?= $summary['pass_count'] ?>/<?= $summary['subject_count'] ?></td>
                        <td><?= $position ?: '-' ?></td>
                        <td>
                            <a href="?session_id=<?= $selectedSession ?>&term_id=<?= $selectedTerm ?>&class_id=<?= $selectedClass ?>&view_assessments=1&view_student=<?= $s['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-clipboard-list"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
