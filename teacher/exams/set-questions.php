<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('teacher');
require_once __DIR__ . '/../../includes/functions.php';
$pageTitle = 'Manage Exam Questions';
$db = getDB();
$userId = $_SESSION['user_id'];
$msg = '';
$msgType = 'success';

$examId = (int)($_GET['exam_id'] ?? 0);
$stmt = $db->prepare("SELECT te.*, sub.name as subject_name, c.name as class_name, c.section FROM teacher_exams te JOIN subjects sub ON te.subject_id = sub.id JOIN classes c ON te.class_id = c.id WHERE te.id = ? AND te.teacher_id = ?");
$stmt->execute([$examId, $userId]);
$exam = $stmt->fetch();
if (!$exam) redirect('/teacher/exams/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_questions'])) {
        $qIds = $_POST['question_ids'] ?? [];
        $order = (int)$exam['question_count'] + 1;
        $added = 0;
        foreach ($qIds as $qid) {
            $qid = (int)$qid;
            if ($qid) {
                try {
                    $stmt = $db->prepare("INSERT IGNORE INTO teacher_exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");
                    $stmt->execute([$examId, $qid, $order++]);
                    $added++;
                } catch (Exception $e) {}
            }
        }
        $stmt = $db->prepare("UPDATE teacher_exams SET total_marks = (SELECT COALESCE(SUM(eq.marks),0) FROM teacher_exam_questions teq JOIN exam_questions eq ON teq.question_id = eq.id WHERE teq.exam_id = ?) WHERE id = ?");
        $stmt->execute([$examId, $examId]);
        $msg = "$added question(s) added to exam.";
    }
    if (isset($_POST['remove_q'])) {
        $rqid = (int)$_POST['teq_id'];
        $db->prepare("DELETE FROM teacher_exam_questions WHERE id = ? AND exam_id = ?")->execute([$rqid, $examId]);
        $stmt = $db->prepare("UPDATE teacher_exams SET total_marks = (SELECT COALESCE(SUM(eq.marks),0) FROM teacher_exam_questions teq JOIN exam_questions eq ON teq.question_id = eq.id WHERE teq.exam_id = ?) WHERE id = ?");
        $stmt->execute([$examId, $examId]);
        $msg = 'Question removed from exam.';
    }
    if (isset($_POST['reorder'])) {
        $orders = $_POST['order'] ?? [];
        foreach ($orders as $teqId => $ord) {
            $db->prepare("UPDATE teacher_exam_questions SET question_order = ? WHERE id = ? AND exam_id = ?")->execute([(int)$ord, (int)$teqId, $examId]);
        }
        $msg = 'Question order updated.';
    }
}

$examQuestions = $db->prepare("SELECT teq.id as teq_id, teq.question_order, eq.* FROM teacher_exam_questions teq JOIN exam_questions eq ON teq.question_id = eq.id WHERE teq.exam_id = ? ORDER BY teq.question_order");
$examQuestions->execute([$examId]);
$examQList = $examQuestions->fetchAll();

$search = sanitizeInput($_GET['search'] ?? '');
$sql = "SELECT eq.*, sub.name as subject_name, c.name as class_name FROM exam_questions eq JOIN subjects sub ON eq.subject_id = sub.id JOIN classes c ON eq.class_id = c.id WHERE eq.teacher_id = ? AND eq.id NOT IN (SELECT question_id FROM teacher_exam_questions WHERE exam_id = ?)";
$params = [$userId, $examId];
if ($search) { $sql .= " AND eq.question_text LIKE ?"; $params[] = "%$search%"; }
$sql .= " ORDER BY eq.created_at DESC";
$bankQuestions = $db->prepare($sql);
$bankQuestions->execute($params);
$bankList = $bankQuestions->fetchAll();

$qTypes = ['mcq'=>'Multiple Choice','true_false'=>'True/False','fill_blank'=>'Fill in the Blank','short_answer'=>'Short Answer','essay'=>'Essay'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="fas fa-list me-2"></i>Manage Questions</h4>
        <p class="text-muted small mb-0"><?= sanitizeInput($exam['title']) ?> — <?= sanitizeInput($exam['subject_name'] . ' - ' . $exam['class_name'] . ' ' . $exam['section']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <span class="badge bg-primary fs-6 mt-2"><?= count($examQList) ?> questions | <?= $exam['total_marks'] ?> marks</span>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= $msg ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><i class="fas fa-check-circle me-2"></i>Exam Questions (<?= count($examQList) ?>)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($examQList)): ?>
                <div class="p-4 text-center text-muted">No questions added yet. Select questions from the bank on the right.</div>
                <?php endif; ?>
                <?php foreach ($examQList as $i => $eq): ?>
                <div class="border-bottom p-3">
                    <div class="d-flex justify-content-between">
                        <strong class="me-2">Q<?= $eq['question_order'] ?: ($i+1) ?>.</strong>
                        <div class="text-nowrap">
                            <span class="badge bg-info"><?= $qTypes[$eq['question_type']] ?? $eq['question_type'] ?></span>
                            <span class="badge bg-secondary"><?= $eq['marks'] ?> mk</span>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this question?')">
                                <input type="hidden" name="teq_id" value="<?= $eq['teq_id'] ?>">
                                <button type="submit" name="remove_q" class="btn btn-sm btn-outline-danger py-0"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </div>
                    <p class="mb-0 mt-1"><?= sanitizeInput(mb_substr($eq['question_text'], 0, 200)) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="fas fa-database me-2"></i>Question Bank</div>
            <div class="card-body">
                <form method="GET" class="mb-3">
                    <input type="hidden" name="exam_id" value="<?= $examId ?>">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search questions..." value="<?= sanitizeInput($search) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <form method="POST">
                    <?php if (empty($bankList)): ?>
                    <div class="text-muted small text-center py-3"><?= $search ? 'No matching questions.' : 'All questions already added.' ?> <a href="questions.php">Create new questions</a>.</div>
                    <?php endif; ?>
                    <?php foreach ($bankList as $bq): ?>
                    <div class="form-check border-bottom py-2">
                        <input type="checkbox" name="question_ids[]" value="<?= $bq['id'] ?>" class="form-check-input" id="q<?= $bq['id'] ?>">
                        <label class="form-check-label" for="q<?= $bq['id'] ?>">
                            <small class="text-muted">[<?= $qTypes[$bq['question_type']] ?? $bq['question_type'] ?>] [<?= $bq['marks'] ?>mk]</small>
                            <br><?= sanitizeInput(mb_substr($bq['question_text'], 0, 120)) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!empty($bankList)): ?>
                    <div class="mt-3">
                        <button type="submit" name="add_questions" class="btn btn-primary w-100"><i class="fas fa-plus me-1"></i>Add Selected Questions</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
