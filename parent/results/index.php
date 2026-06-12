<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../../includes/functions.php';
if (!defined('RESULT_FUNCTIONS_LOADED')) require_once __DIR__ . '/../../includes/result_functions.php';

$pageTitle = 'Children Results';
$db = getDB();
$parentId = getParentId();

$stmt = $db->prepare("
    SELECT s.id as student_id, s.admission_no, s.class_id, u.first_name, u.last_name,
           c.name as class_name, c.section
    FROM student_parents sp
    JOIN students s ON sp.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    WHERE sp.parent_id = ?
    ORDER BY u.first_name
");
$stmt->execute([$parentId]);
$children = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>My Children's Results</h4>
</div>

<?php if (empty($children)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>No children linked to your account. Please contact the school administration.
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($children as $child):
        $stmt = $db->prepare("
            SELECT DISTINCT rs.session_id, rs.term_id, s.session_name, t.term_name
            FROM result_scores rs
            JOIN academic_sessions s ON rs.session_id = s.id
            JOIN terms t ON rs.term_id = t.id
            WHERE rs.student_id = ? AND rs.status = 'published'
            ORDER BY s.start_date DESC, t.id ASC
        ");
        $stmt->execute([$child['student_id']]);
        $publishedTerms = $stmt->fetchAll();

        $summary = [];
        $position = 0;
        if (!empty($publishedTerms)) {
            $lt = $publishedTerms[0];
            $summary = getStudentTermSummary($db, $child['student_id'], $lt['session_id'], $lt['term_id']);
            $position = getClassPosition($db, $child['student_id'], $child['class_id'], $lt['session_id'], $lt['term_id']);
        }
    ?>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: var(--gradient-gold); color: var(--primary); font-weight: 700; font-size: 18px;">
                        <?= strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= sanitizeInput($child['first_name'] . ' ' . $child['last_name']) ?></h5>
                        <small class="text-muted">
                            <?= sanitizeInput($child['class_name'] . ' ' . $child['section']) ?> &middot;
                            <?= sanitizeInput($child['admission_no']) ?>
                        </small>
                    </div>
                </div>

                <?php if (!empty($summary)): ?>
                <div class="row text-center g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="fw-bold text-primary"><?= number_format($summary['average'], 1) ?>%</div>
                            <small class="text-muted">Average</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="fw-bold text-<?= $summary['overall_grade'] === 'A' || $summary['overall_grade'] === 'B' ? 'success' : 'warning' ?>">
                                <?= $summary['overall_grade'] ?>
                            </div>
                            <small class="text-muted">Grade</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded bg-light">
                            <div class="fw-bold text-primary"><?= $position ? $position . niceOrdinal($position) : '-' ?></div>
                            <small class="text-muted">Position</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/parent/results/view.php?student_id=<?= $child['student_id'] ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>View Full Results
                    </a>
                </div>

                <?php if (!empty($publishedTerms)): ?>
                <div class="mt-3">
                    <small class="text-muted fw-bold">Published Terms:</small>
                    <div class="mt-1">
                        <?php foreach ($publishedTerms as $pt): ?>
                        <a href="<?= BASE_URL ?>/parent/results/view.php?student_id=<?= $child['student_id'] ?>&session_id=<?= $pt['session_id'] ?>&term_id=<?= $pt['term_id'] ?>" class="badge bg-gold text-dark me-1 mb-1 text-decoration-none">
                            <?= sanitizeInput($pt['term_name'] . ' (' . $pt['session_name'] . ')') ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
function niceOrdinal(int $num): string {
    if ($num >= 11 && $num <= 13) return 'th';
    return match ($num % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
}
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
