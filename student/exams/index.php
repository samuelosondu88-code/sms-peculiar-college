<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('student');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'My Exams';
$db = getDB();
$userId = $_SESSION['user_id'];

$studentStmt = $db->prepare("SELECT id, class_id FROM students WHERE user_id = ?");
$studentStmt->execute([$userId]);
$student = $studentStmt->fetch();
$classId = $student['class_id'] ?? 0;

$sql = "SELECT te.*, sub.name as subject_name, c.name as class_name, c.section,
        (SELECT status FROM exam_attempts WHERE exam_id = te.id AND student_id = ? ORDER BY created_at DESC LIMIT 1) as attempt_status,
        (SELECT id FROM exam_attempts WHERE exam_id = te.id AND student_id = ? ORDER BY created_at DESC LIMIT 1) as attempt_id
        FROM teacher_exams te
        JOIN subjects sub ON te.subject_id = sub.id
        JOIN classes c ON te.class_id = c.id
        WHERE te.is_published = 1 AND te.class_id = ?
        ORDER BY te.exam_date DESC, te.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute([$userId, $userId, $classId]);
$exams = $stmt->fetchAll();

$examTypes = ['CA'=>'Continuous Assessment','Test'=>'Test','Mid-Term'=>'Mid-Term','Examination'=>'Examination','Mock Exam'=>'Mock Exam','CBT'=>'CBT'];

require_once __DIR__ . '/../../includes/header.php';
?>

<h4 class="fw-bold mb-4"><i class="fas fa-file-alt me-2"></i>My Exams</h4>

<?php if (empty($exams)): ?>
<div class="alert alert-info">No exams available for your class at this time.</div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($exams as $exam): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><?= sanitizeInput($exam['title']) ?></h5>
                <p class="card-text small text-muted">
                    <i class="fas fa-book me-1"></i><?= sanitizeInput($exam['subject_name']) ?><br>
                    <i class="fas fa-users me-1"></i><?= sanitizeInput($exam['class_name'] . ' ' . $exam['section']) ?><br>
                    <i class="fas fa-clock me-1"></i><?= $exam['duration_minutes'] ?> mins<br>
                    <i class="fas fa-star me-1"></i><?= $exam['total_marks'] ?> marks<br>
                    <span class="badge bg-secondary"><?= $examTypes[$exam['exam_type']] ?? $exam['exam_type'] ?></span>
                </p>
                <?php if ($exam['attempt_status'] === 'in_progress'): ?>
                <a href="take-exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-warning w-100"><i class="fas fa-play me-1"></i>Continue</a>
                <?php elseif ($exam['attempt_status'] === 'submitted' || $exam['attempt_status'] === 'graded'): ?>
                <a href="results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline-primary w-100"><i class="fas fa-eye me-1"></i>View Results</a>
                <?php else: ?>
                <a href="take-exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary w-100"><i class="fas fa-play me-1"></i>Start Exam</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
