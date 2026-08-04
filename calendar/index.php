<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'School Calendar';
$db = getDB();
$role = $_SESSION['role'] ?? '';

$month = (int)($_GET['month'] ?? date('m'));
$year = (int)($_GET['year'] ?? date('Y'));
if ($month < 1) { $month = 1; } if ($month > 12) { $month = 12; }

$targetSql = $role === 'admin' ? "'admin','all'" : "'$role','all'";
$events = $db->query("SELECT * FROM events WHERE target_role IN ($targetSql) AND YEAR(event_date) = $year AND MONTH(event_date) = $month ORDER BY event_date, event_time")->fetchAll();

$eventsByDate = [];
foreach ($events as $e) { $eventsByDate[(int)date('j', strtotime($e['event_date']))][] = $e; }

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$startDow = (int)date('w', $firstDay);
$prevMonth = $month - 1 < 1 ? 12 : $month - 1;
$prevYear = $month - 1 < 1 ? $year - 1 : $year;
$nextMonth = $month + 1 > 12 ? 1 : $month + 1;
$nextYear = $month + 1 > 12 ? $year + 1 : $year;
$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

$typeColors = ['academic'=>'#059669','sports'=>'#2563eb','cultural'=>'#d97706','meeting'=>'#7c3aed','holiday'=>'#dc2626','other'=>'#6b7280'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2"></i>School Calendar</h4>
    <div>
        <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
        <strong class="mx-3"><?= date('F Y', $firstDay) ?></strong>
        <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
    </div>
</div>

<div class="card">
    <div class="card-body p-2">
        <table class="table table-bordered mb-0" style="table-layout:fixed;">
            <thead class="table-dark">
                <tr><?php foreach ($dayNames as $d): ?><th class="text-center py-2"><?= $d ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <?php for ($w = 0; $w < 6; $w++): ?>
                <tr>
                    <?php for ($d = 0; $d < 7; $d++):
                        $dayNum = $w * 7 + $d - $startDow + 1;
                        $isValid = $dayNum >= 1 && $dayNum <= $daysInMonth;
                    ?>
                    <td style="height:90px;vertical-align:top;padding:4px;<?= !$isValid ? 'background:#f8f9fa;' : '' ?>">
                        <?php if ($isValid):
                            $today = ($dayNum == date('j') && $month == date('m') && $year == date('Y'));
                        ?>
                        <div class="small fw-bold <?= $today ? 'text-danger' : 'text-muted' ?>"><?= $dayNum ?></div>
                        <?php if (isset($eventsByDate[$dayNum])): foreach ($eventsByDate[$dayNum] as $ev): ?>
                        <div class="small px-1 rounded mb-1" style="background:<?= $typeColors[$ev['type']] ?? '#6b7280' ?>20;border-left:2px solid <?= $typeColors[$ev['type']] ?? '#6b7280' ?>;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= sanitizeInput($ev['title']) . ($ev['event_time'] ? ' @ ' . date('H:i', strtotime($ev['event_time'])) : '') ?>">
                            <?= sanitizeInput($ev['title']) ?>
                        </div>
                        <?php endforeach; endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php endfor; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex gap-3 flex-wrap">
    <?php foreach ($typeColors as $t => $c): ?>
    <span class="small"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= $c ?>;margin-right:4px;"></span><?= ucfirst($t) ?></span>
    <?php endforeach; ?>
</div>

<?php if (!empty($events)): ?>
<div class="mt-3">
    <h6>Events This Month</h6>
    <div class="list-group">
        <?php foreach ($events as $e): ?>
        <div class="list-group-item list-group-item-action py-2">
            <div class="d-flex justify-content-between">
                <div>
                    <strong><?= sanitizeInput($e['title']) ?></strong>
                    <small class="text-muted ms-2"><?= date('d M Y', strtotime($e['event_date'])) ?><?= $e['event_time'] ? ' @ ' . date('H:i', strtotime($e['event_time'])) : '' ?></small>
                </div>
                <span class="badge" style="background:<?= $typeColors[$e['type']] ?? '#6b7280' ?>"><?= $e['type'] ?></span>
            </div>
            <?php if ($e['description']): ?><small class="text-muted d-block mt-1"><?= sanitizeInput($e['description']) ?></small><?php endif; ?>
            <?php if ($e['location']): ?><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?= sanitizeInput($e['location']) ?></small><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
