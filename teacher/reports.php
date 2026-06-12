<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Student Performance Reports';
$db = getDB();
$userId = $_SESSION['user_id'];

$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedExam = (int)($_GET['exam_id'] ?? 0);

$classes = $db->prepare("SELECT c.id, c.name, c.section FROM classes c WHERE c.class_teacher_id = ?");
$classes->execute([$userId]);
$myClasses = $classes->fetchAll();

$exams = [];
$students = [];
if ($selectedClass) {
    $exams = $db->prepare("SELECT id, name FROM exams WHERE class_id = ? OR class_id IS NULL ORDER BY created_at DESC");
    $exams->execute([$selectedClass]);
    $exams = $exams->fetchAll();

    if ($selectedExam) {
        $students = $db->prepare("
            SELECT s.id, u.first_name, u.last_name, s.admission_no,
                   r.score, r.grade, sub.name as subject_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN subjects sub ON sub.class_id = s.class_id
            LEFT JOIN results r ON r.student_id = s.id AND r.exam_id = ? AND r.subject_id = sub.id
            WHERE s.class_id = ? AND s.status = 'active'
            ORDER BY u.first_name, sub.name
        ");
        $students->execute([$selectedExam, $selectedClass]);
        $students = $students->fetchAll();

        $grouped = [];
        foreach ($students as $s) {
            $grouped[$s['id']]['name'] = $s['first_name'] . ' ' . $s['last_name'];
            $grouped[$s['id']]['admission'] = $s['admission_no'];
            $grouped[$s['id']]['subjects'][$s['subject_name']] = [
                'score' => $s['score'] ?: '-',
                'grade' => $s['grade'] ?: '-'
            ];
        }
        $students = $grouped;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chart-bar me-2"></i>Performance Reports</h4>
</div>

<form method="GET" class="row g-3 mb-4">
    <div class="col-md-4">
        <label class="form-label">Class</label>
        <select name="class_id" class="form-select" onchange="this.form.submit()">
            <option value="">Select</option>
            <?php foreach ($myClasses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $selectedClass === $c['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($c['name'] . ' ' . ($c['section'] ?? '')) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (!empty($exams)): ?>
    <div class="col-md-4">
        <label class="form-label">Exam</label>
        <select name="exam_id" class="form-select" onchange="this.form.submit()">
            <option value="">Select</option>
            <?php foreach ($exams as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $selectedExam === $e['id'] ? 'selected' : '' ?>>
                <?= sanitizeInput($e['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</form>

<?php if (!empty($students)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>Performance Report</span>
        <button class="btn btn-sm btn-outline-primary no-print" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Admission</th>
                        <?php 
                        $subjectNames = [];
                        foreach ($students as $s) {
                            foreach ($s['subjects'] as $name => $data) {
                                $subjectNames[$name] = true;
                            }
                        }
                        $subjectNames = array_keys($subjectNames);
                        foreach ($subjectNames as $sn): 
                        ?>
                        <th><?= sanitizeInput($sn) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                        <th>Average</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach ($students as $sid => $s): $i++; $total = 0; $count = 0; ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><?= sanitizeInput($s['name']) ?></td>
                        <td><?= sanitizeInput($s['admission']) ?></td>
                        <?php foreach ($subjectNames as $sn):
                            $score = $s['subjects'][$sn]['score'] ?? '-';
                            if (is_numeric($score)) { $total += $score; $count++; }
                        ?>
                        <td class="text-center"><strong><?= is_numeric($score) ? $score : $score ?></strong></td>
                        <?php endforeach; ?>
                        <td class="text-center"><strong><?= $total ?></strong></td>
                        <td class="text-center"><strong><?= $count > 0 ? number_format($total / $count, 1) : '-' ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($selectedClass): ?>
<div class="alert alert-info">Select an exam to view the report.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
