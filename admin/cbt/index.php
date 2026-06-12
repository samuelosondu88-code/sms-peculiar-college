<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'CBT Dashboard';
$db = getDB();

$stats = [];
$stats['subjects'] = (int)$db->query("SELECT COUNT(*) FROM cbt_subjects")->fetchColumn();
$stats['questions'] = (int)$db->query("SELECT COUNT(*) FROM cbt_questions")->fetchColumn();
$stats['exams'] = (int)$db->query("SELECT COUNT(*) FROM cbt_exams")->fetchColumn();
$stats['published'] = (int)$db->query("SELECT COUNT(*) FROM cbt_exams WHERE is_published = 1")->fetchColumn();
$stats['attempts'] = (int)$db->query("SELECT COUNT(*) FROM cbt_attempts")->fetchColumn();
$stats['completed'] = (int)$db->query("SELECT COUNT(*) FROM cbt_attempts WHERE status = 'completed'")->fetchColumn();

$recentAttempts = $db->query("
    SELECT ca.id, ca.score, ca.total_questions, ca.correct_count, ca.status, ca.completed_at,
           ce.title as exam_title, u.first_name, u.last_name
    FROM cbt_attempts ca
    JOIN cbt_exams ce ON ca.exam_id = ce.id
    JOIN students s ON ca.student_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY ca.started_at DESC LIMIT 10
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">CBT Dashboard</h4>
        <p class="text-muted small mb-0">Computer-Based Testing Management</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>/admin/cbt/exams.php" class="btn btn-primary me-2">
            <i class="fas fa-plus me-1"></i>Manage Exams
        </a>
        <a href="<?= BASE_URL ?>/admin/cbt/questions.php" class="btn btn-gold">
            <i class="fas fa-database me-1"></i>Question Bank
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="stat-card stat-navy">
            <i class="fas fa-book stat-icon"></i>
            <div class="stat-value"><?= $stats['subjects'] ?></div>
            <div class="stat-label">Subjects</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #6366f1, #818cf8);">
            <i class="fas fa-question-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['questions'] ?></div>
            <div class="stat-label">Questions</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card stat-gold">
            <i class="fas fa-file-alt stat-icon"></i>
            <div class="stat-value"><?= $stats['exams'] ?></div>
            <div class="stat-label">Total Exams</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card stat-primary">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-value"><?= $stats['published'] ?></div>
            <div class="stat-label">Published</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #ea580c, #f97316);">
            <i class="fas fa-pencil-alt stat-icon"></i>
            <div class="stat-value"><?= $stats['attempts'] ?></div>
            <div class="stat-label">Total Attempts</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card" style="background: linear-gradient(135deg, #16a34a, #22c55e);">
            <i class="fas fa-check-double stat-icon"></i>
            <div class="stat-value"><?= $stats['completed'] ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2"></i>Recent Exam Attempts</span>
        <a href="<?= BASE_URL ?>/admin/cbt/results.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAttempts as $a): ?>
                    <tr>
                        <td><?= sanitizeInput($a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td><?= sanitizeInput($a['exam_title']) ?></td>
                        <td>
                            <strong><?= $a['score'] ?>%</strong>
                            <small class="text-muted">(<?= $a['correct_count'] ?>/<?= $a['total_questions'] ?>)</small>
                        </td>
                        <td><?= getStatusBadge($a['status']) ?></td>
                        <td><?= $a['completed_at'] ? formatDate($a['completed_at']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentAttempts)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No attempts yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
