<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$ref = sanitizeInput($_GET['ref'] ?? $_POST['ref'] ?? '');
$application = null;
$searched = false;

if ($ref) {
    $searched = true;
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, af.form_name FROM applications a JOIN admission_forms af ON a.form_id = af.id WHERE a.application_ref = ? LIMIT 1");
    $stmt->execute([$ref]);
    $application = $stmt->fetch();
}

$statusFlow = ['draft' => 0, 'submitted' => 1, 'reviewing' => 2, 'accepted' => 3, 'rejected' => 3, 'waitlisted' => 2];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Application Status - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark public-header">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-school me-2"></i><?= SCHOOL_NAME ?></a>
            <div class="collapse navbar-collapse show">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="admission.php">Back to Admission</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="fas fa-search fa-3x text-primary mb-2"></i>
                                <h4 class="fw-bold">Check Application Status</h4>
                                <p class="text-muted">Enter your application reference number</p>
                            </div>
                            <form method="GET">
                                <div class="input-group input-group-lg">
                                    <input type="text" name="ref" class="form-control" placeholder="e.g., PEC-2026-0001" value="<?= sanitizeInput($ref) ?>" required>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                                </div>
                            </form>

                            <?php if ($searched): ?>
                            <hr>
                            <?php if ($application): ?>
                            <div class="text-center">
                                <h5 class="fw-bold"><?= sanitizeInput($application['first_name'] . ' ' . $application['last_name']) ?></h5>
                                <p class="text-muted"><?= sanitizeInput($application['form_name']) ?></p>

                                <div class="d-flex justify-content-between mt-4">
                                    <?php
                                    $steps = [
                                        'draft' => ['icon' => 'fa-file', 'label' => 'Initiated'],
                                        'submitted' => ['icon' => 'fa-check-circle', 'label' => 'Submitted'],
                                        'reviewing' => ['icon' => 'fa-search', 'label' => 'Reviewing'],
                                        'final' => ['icon' => 'fa-flag-checkered', 'label' => 'Decision'],
                                    ];
                                    $current = $statusFlow[$application['status']] ?? 0;
                                    $stepIndex = 0;
                                    foreach ($steps as $key => $step):
                                        $done = $stepIndex <= $current;
                                        $isFinal = $key === 'final';
                                    ?>
                                    <div class="text-center <?= $done ? 'text-primary' : 'text-muted' ?>" style="flex:1">
                                        <div class="rounded-circle bg-<?= $done ? 'primary' : 'light' ?> d-inline-flex align-items-center justify-content-center mb-1" style="width:40px;height:40px">
                                            <i class="fas <?= $step['icon'] ?> text-<?= $done ? 'white' : 'muted' ?>"></i>
                                        </div>
                                        <div><small><?= $step['label'] ?></small></div>
                                    </div>
                                    <?php if (!$isFinal): ?>
                                    <div class="align-self-center" style="flex:0.5"><hr class="border-<?= $done ? 'primary' : 'light' ?>"></div>
                                    <?php endif; ?>
                                    <?php $stepIndex++; endforeach; ?>
                                </div>

                                <div class="mt-4 p-3 bg-light rounded">
                                    <strong>Status:</strong> <?= getStatusBadge($application['status']) ?>
                                    <?php if ($application['status'] === 'accepted'): ?>
                                    <div class="mt-2">
                                        <a href="download-admission.php?ref=<?= $ref ?>" class="btn btn-success">
                                            <i class="fas fa-download me-2"></i>Download Admission Letter
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <p class="text-muted small mt-3">Reference: <?= $application['application_ref'] ?> | Submitted: <?= $application['submitted_at'] ? formatDate($application['submitted_at']) : 'Not yet' ?></p>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-danger mt-3">No application found with reference "<strong><?= sanitizeInput($ref) ?></strong>". Please check and try again.</div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
