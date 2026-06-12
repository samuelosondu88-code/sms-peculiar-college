<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Question Bank';
$db = getDB();

$action = $_GET['action'] ?? 'list';
$subject_id = (int)($_GET['subject_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$message = '';
$error = '';

// Handle delete
if ($action === 'delete' && $id) {
    $db->prepare("DELETE FROM cbt_questions WHERE id = ?")->execute([$id]);
    logActivity($_SESSION['user_id'], 'delete_cbt_question', 'cbt_questions', $id);
    $message = 'Question deleted successfully.';
    $action = 'list';
}

// Handle add/edit
if ($action === 'save') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');
    $correct_answer = strtoupper(trim($_POST['correct_answer'] ?? ''));
    $difficulty = $_POST['difficulty'] ?? 'medium';
    $explanation = trim($_POST['explanation'] ?? '');

    if (!$subject_id || !$question_text || !$option_a || !$option_b || !$correct_answer || !in_array($correct_answer, ['A','B','C','D'])) {
        $error = 'Please fill all required fields and select a valid correct answer.';
    } else {
        $edit_id = (int)($_POST['id'] ?? 0);
        if ($edit_id) {
            $stmt = $db->prepare("UPDATE cbt_questions SET subject_id=?, question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, difficulty=?, explanation=? WHERE id=?");
            $stmt->execute([$subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $difficulty, $explanation, $edit_id]);
            logActivity($_SESSION['user_id'], 'update_cbt_question', 'cbt_questions', $edit_id);
            $message = 'Question updated successfully.';
        } else {
            $stmt = $db->prepare("INSERT INTO cbt_questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$subject_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $difficulty, $explanation]);
            logActivity($_SESSION['user_id'], 'create_cbt_question', 'cbt_questions', (int)$db->lastInsertId());
            $message = 'Question created successfully.';
        }
        $action = 'list';
    }
}

// Bulk import
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $lines = explode("\n", trim($_POST['bulk_questions'] ?? ''));
    $imported = 0;
    $stmt = $db->prepare("INSERT INTO cbt_questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty) VALUES (?,?,?,?,?,?,?,'medium')");
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $parts = explode('||', $line);
        if (count($parts) >= 6) {
            $stmt->execute([$subject_id, trim($parts[0]), trim($parts[1]), trim($parts[2]), trim($parts[3]), trim($parts[4]), strtoupper(trim($parts[5]))]);
            $imported++;
        }
    }
    $message = "$imported questions imported successfully.";
    $action = 'list';
}

$subjects = $db->query("SELECT id, name, code FROM cbt_subjects ORDER BY name")->fetchAll();

// Build query
$where = '';
$params = [];
if ($subject_id) {
    $where = 'WHERE q.subject_id = ?';
    $params[] = $subject_id;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$totalStmt = $db->prepare("SELECT COUNT(*) FROM cbt_questions q $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$paginate = paginate($total, $page, $limit);

$sql = "SELECT q.*, s.name as subject_name, s.code as subject_code FROM cbt_questions q JOIN cbt_subjects s ON q.subject_id = s.id $where ORDER BY q.id DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$allParams = array_merge($params, [$limit, $offset]);
$stmt->execute($allParams);
$questions = $stmt->fetchAll();

$editQuestion = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM cbt_questions WHERE id = ?");
    $stmt->execute([$id]);
    $editQuestion = $stmt->fetch();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Question Bank</h4>
        <p class="text-muted small mb-0">Manage CBT questions across all subjects</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/cbt/questions.php" class="btn btn-outline-primary me-2 <?= $action === 'list' ? 'd-none' : '' ?>">
            <i class="fas fa-list me-1"></i>All Questions
        </a>
        <a href="<?= BASE_URL ?>/admin/cbt/questions.php?action=add" class="btn btn-primary me-2 <?= $action !== 'list' || ($action === 'list' && $editQuestion) ? 'd-none' : '' ?>">
            <i class="fas fa-plus me-1"></i>Add Question
        </a>
        <a href="<?= BASE_URL ?>/admin/cbt/questions.php?action=import" class="btn btn-gold <?= $action !== 'list' ? 'd-none' : '' ?>">
            <i class="fas fa-upload me-1"></i>Bulk Import
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show"><?= $message ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $editQuestion)): ?>
<div class="card">
    <div class="card-header">
        <i class="fas fa-<?= $editQuestion ? 'edit' : 'plus' ?> me-2"></i>
        <?= $editQuestion ? 'Edit Question' : 'Add New Question' ?>
    </div>
    <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>/admin/cbt/questions.php?action=save">
            <?php if ($editQuestion): ?>
            <input type="hidden" name="id" value="<?= $editQuestion['id'] ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Select Subject</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($editQuestion ? $editQuestion['subject_id'] : $subject_id) == $s['id'] ? 'selected' : '' ?>>
                            <?= sanitizeInput($s['name']) ?> (<?= $s['code'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Difficulty</label>
                    <select name="difficulty" class="form-select">
                        <option value="easy" <?= $editQuestion && $editQuestion['difficulty'] === 'easy' ? 'selected' : '' ?>>Easy</option>
                        <option value="medium" <?= !$editQuestion || $editQuestion['difficulty'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="hard" <?= $editQuestion && $editQuestion['difficulty'] === 'hard' ? 'selected' : '' ?>>Hard</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Correct Answer</label>
                    <select name="correct_answer" class="form-select" required>
                        <option value="">Select</option>
                        <option value="A" <?= $editQuestion && $editQuestion['correct_answer'] === 'A' ? 'selected' : '' ?>>Option A</option>
                        <option value="B" <?= $editQuestion && $editQuestion['correct_answer'] === 'B' ? 'selected' : '' ?>>Option B</option>
                        <option value="C" <?= $editQuestion && $editQuestion['correct_answer'] === 'C' ? 'selected' : '' ?>>Option C</option>
                        <option value="D" <?= $editQuestion && $editQuestion['correct_answer'] === 'D' ? 'selected' : '' ?>>Option D</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Question Text</label>
                    <textarea name="question_text" class="form-control" rows="3" required><?= sanitizeInput($editQuestion['question_text'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Option A</label>
                    <input type="text" name="option_a" class="form-control" value="<?= sanitizeInput($editQuestion['option_a'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Option B</label>
                    <input type="text" name="option_b" class="form-control" value="<?= sanitizeInput($editQuestion['option_b'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Option C</label>
                    <input type="text" name="option_c" class="form-control" value="<?= sanitizeInput($editQuestion['option_c'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Option D</label>
                    <input type="text" name="option_d" class="form-control" value="<?= sanitizeInput($editQuestion['option_d'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Explanation (optional)</label>
                    <textarea name="explanation" class="form-control" rows="2"><?= sanitizeInput($editQuestion['explanation'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Question</button>
                <a href="<?= BASE_URL ?>/admin/cbt/questions.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'import'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-upload me-2"></i>Bulk Import Questions</div>
    <div class="card-body">
        <form method="post" action="<?= BASE_URL ?>/admin/cbt/questions.php?action=import">
            <div class="mb-3">
                <label class="form-label">Subject</label>
                <select name="subject_id" class="form-select" required>
                    <option value="">Select Subject</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= sanitizeInput($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Questions (one per line)</label>
                <p class="text-muted small">Format: <code>Question||OptionA||OptionB||OptionC||OptionD||CorrectLetter</code></p>
                <textarea name="bulk_questions" class="form-control" rows="12" required placeholder="What is the capital of Nigeria?||Lagos||Abuja||Kano||Ibadan||B"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Import</button>
            <a href="<?= BASE_URL ?>/admin/cbt/questions.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>All Questions</span>
        <form class="d-flex gap-2" method="get">
            <input type="hidden" name="action" value="list">
            <select name="subject_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <option value="">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $subject_id === $s['id'] ? 'selected' : '' ?>><?= sanitizeInput($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Question</th>
                    <th style="width: 100px;">Subject</th>
                    <th style="width: 70px;">Answer</th>
                    <th style="width: 70px;">Difficulty</th>
                    <th style="width: 90px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $i => $q): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td>
                        <div class="text-truncate" style="max-width: 400px;"><?= sanitizeInput($q['question_text']) ?></div>
                        <small class="text-muted">A: <?= sanitizeInput($q['option_a']) ?> | B: <?= sanitizeInput($q['option_b']) ?> | C: <?= sanitizeInput($q['option_c']) ?> | D: <?= sanitizeInput($q['option_d']) ?></small>
                    </td>
                    <td><span class="badge bg-primary"><?= sanitizeInput($q['subject_code']) ?></span></td>
                    <td><span class="badge bg-success"><?= $q['correct_answer'] ?></span></td>
                    <td>
                        <?php $diffColors = ['easy'=>'bg-success','medium'=>'bg-warning text-dark','hard'=>'bg-danger']; ?>
                        <span class="badge <?= $diffColors[$q['difficulty']] ?? 'bg-secondary' ?>"><?= ucfirst($q['difficulty']) ?></span>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/cbt/questions.php?action=edit&id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="<?= BASE_URL ?>/admin/cbt/questions.php?action=delete&id=<?= $q['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this question?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($questions)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No questions found. <?= $subject_id ? 'Try a different subject filter.' : 'Add your first question!' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginate['totalPages'] > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php for ($p = 1; $p <= $paginate['totalPages']; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&subject_id=<?= $subject_id ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
