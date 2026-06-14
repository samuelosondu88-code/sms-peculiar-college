<?php
require_once __DIR__ . '/../../config/session.php';
requireRole('parent');
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Classroom Monitoring';
$db = getDB();
$parentId = $_SESSION['user_id'];

$children = $db->prepare("
    SELECT s.id, s.admission_no, u.first_name, u.last_name, c.name as class_name, c.section
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classes c ON s.class_id = c.id
    JOIN student_parents sp ON s.id = sp.student_id
    JOIN parents p ON sp.parent_id = p.id
    WHERE p.user_id = ? AND s.status = 'active'
");
$children->execute([$parentId]);
$kids = $children->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Classroom Monitoring</h4>
</div>

<?php if (empty($kids)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-child fa-4x text-muted mb-3"></i>
        <p class="text-muted mb-0">No children linked to your account.</p>
    </div>
</div>
<?php else: foreach ($kids as $kid):
    $classes = $db->prepare("
        SELECT vc.*, s.name as subject_name, s.code, u2.first_name as t_first, u2.last_name as t_last,
            (SELECT COUNT(*) FROM class_enrollments ce2 WHERE ce2.virtual_class_id = vc.id AND ce2.status = 'active') as student_count,
            (SELECT COUNT(*) FROM class_materials cm WHERE cm.virtual_class_id = vc.id) as material_count
        FROM class_enrollments ce
        JOIN virtual_classes vc ON ce.virtual_class_id = vc.id
        JOIN subjects s ON vc.subject_id = s.id
        JOIN teachers t ON vc.teacher_id = t.id
        JOIN users u2 ON t.user_id = u2.id
        WHERE ce.student_id = ? AND ce.status = 'active' AND vc.status = 'active'
        ORDER BY vc.created_at DESC
    ");
    $classes->execute([$kid['id']]);
    $kidClasses = $classes->fetchAll();
?>
<div class="card mb-4">
    <div class="card-header">
        <strong><i class="fas fa-user-graduate me-2"></i><?= sanitizeInput($kid['first_name'] . ' ' . $kid['last_name']) ?></strong>
        <span class="text-muted ms-2"><?= sanitizeInput($kid['admission_no']) ?> | <?= sanitizeInput($kid['class_name'] . ' ' . $kid['section']) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($kidClasses)): ?>
        <div class="text-center text-muted py-3">Not enrolled in any classroom.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Classroom</th><th>Subject</th><th>Teacher</th><th>Materials</th><th>Attendance</th><th>Assignments</th></tr></thead>
                <tbody>
                    <?php foreach ($kidClasses as $kvc):
                        $att = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present FROM class_attendance WHERE virtual_class_id = ? AND student_id = ?");
                        $att->execute([$kvc['id'], $kid['id']]);
                        $a = $att->fetch();
                        $attPct = $a['total'] > 0 ? round(($a['present'] / $a['total']) * 100) : 0;

                        $asnStats = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN asub.status = 'graded' THEN 1 ELSE 0 END) as graded FROM class_assignments ca LEFT JOIN assignment_submissions asub ON ca.id = asub.assignment_id AND asub.student_id = ? WHERE ca.virtual_class_id = ?");
                        $asnStats->execute([$kid['id'], $kvc['id']]);
                        $as = $asnStats->fetch();
                    ?>
                    <tr>
                        <td><strong><?= sanitizeInput($kvc['name']) ?></strong></td>
                        <td><?= sanitizeInput($kvc['subject_name']) ?> (<?= sanitizeInput($kvc['code']) ?>)</td>
                        <td><small><?= sanitizeInput($kvc['t_first'] . ' ' . $kvc['t_last']) ?></small></td>
                        <td><span class="badge bg-info"><?= $kvc['material_count'] ?></span></td>
                        <td>
                            <span class="badge bg-<?= $attPct >= 75 ? 'success' : ($attPct >= 50 ? 'warning' : 'danger') ?>"><?= $attPct ?>%</span>
                            <small class="text-muted">(<?= $a['present'] ?>/<?= $a['total'] ?>)</small>
                        </td>
                        <td><span class="badge bg-success"><?= $as['graded'] ?? 0 ?>/<?= $as['total'] ?? 0 ?> graded</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
