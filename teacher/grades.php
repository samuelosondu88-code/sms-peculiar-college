<?php
require_once __DIR__ . '/../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Enter Grades';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$error = '';

$subjects = $db->prepare("SELECT s.id, s.name, s.code, c.name as class_name, c.section FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.teacher_id = ?");
$subjects->execute([$userId]);
$mySubjects = $subjects->fetchAll();

$selectedSubject = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
$selectedExam = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $studentIds = $_POST['student_ids'] ?? [];
    $scores = $_POST['score'] ?? [];

    $db->beginTransaction();
    try {
        foreach ($studentIds as $sid) {
            $score = (float)($scores[$sid] ?? 0);
            $grade = getGPA($score);
            $stmt = $db->prepare("INSERT INTO results (student_id, exam_id, subject_id, score, grade, entered_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), grade = VALUES(grade), entered_by = VALUES(entered_by)");
            $stmt->execute([$sid, $selectedExam, $selectedSubject, $score, $grade, $userId]);
        }
        $db->commit();
        $msg = 'Grades saved successfully.';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error saving grades.';
    }
}

$exams = [];
$students = [];
$results = [];
if ($selectedSubject) {
    $stmt = $db->prepare("SELECT c.id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $stmt->execute([$selectedSubject]);
    $class = $stmt->fetch();

    $exams = $db->prepare("SELECT id, name FROM exams WHERE class_id = ? OR class_id IS NULL ORDER BY created_at DESC");
    $exams->execute([$class['id'] ?? 0]);
    $exams = $exams->fetchAll();

    if ($selectedExam) {
        $students = $db->prepare("SELECT s.id, u.first_name, u.last_name, s.admission_no FROM students s JOIN users u ON s.user_id = u.id WHERE s.class_id = ? AND s.status = 'active' ORDER BY u.first_name");
        $students->execute([$class['id']]);
        $students = $students->fetchAll();

        $res = $db->prepare("SELECT student_id, score, grade FROM results WHERE exam_id = ? AND subject_id = ?");
        $res->execute([$selectedExam, $selectedSubject]);
        foreach ($res->fetchAll() as $r) {
            $results[$r['student_id']] = $r;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-star me-2"></i>Enter Grades</h4>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Select Subject</option>
                    <?php foreach ($mySubjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSubject === $s['id'] ? 'selected' : '' ?>>
                        <?= sanitizeInput($s['name'] . ' (' . $s['code'] . ') - ' . $s['class_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($exams)): ?>
            <div class="col-md-4">
                <label class="form-label">Exam</label>
                <select name="exam_id" class="form-select" required onchange="this.form.submit()">
                    <option value="">Select Exam</option>
                    <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $selectedExam === $e['id'] ? 'selected' : '' ?>>
                        <?= sanitizeInput($e['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($students)): ?>
<div class="card">
    <div class="card-header">Grade Entry: <?= sanitizeInput($exams[0]['name'] ?? '') ?></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">
            <input type="hidden" name="exam_id" value="<?= $selectedExam ?>">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Admission</th>
                            <th>Student</th>
                            <th>Score (0-100)</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= sanitizeInput($s['admission_no']) ?></td>
                            <td><?= sanitizeInput($s['first_name'] . ' ' . $s['last_name']) ?></td>
                            <td style="width:200px">
                                <input type="number" name="score[<?= $s['id'] ?>]" class="form-control score-input" value="<?= $results[$s['id']]['score'] ?? '' ?>" min="0" max="100" step="0.01">
                            </td>
                            <td class="grade-display"><strong><?= $results[$s['id']]['grade'] ?? '-' ?></strong></td>
                            <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="save_grades" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Save All Grades
            </button>
        </form>
    </div>
</div>
<?php elseif ($selectedSubject): ?>
<div class="alert alert-info">Select an exam to enter grades.</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.score-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var score = parseFloat(this.value);
            var grade = '-';
            if (score >= 70) grade = 'A';
            else if (score >= 60) grade = 'B';
            else if (score >= 50) grade = 'C';
            else if (score >= 45) grade = 'D';
            else if (score >= 40) grade = 'E';
            else if (score >= 0) grade = 'F';
            this.closest('tr').querySelector('.grade-display').innerHTML = '<strong>' + grade + '</strong>';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
