<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$ref = sanitizeInput($_GET['ref'] ?? '');
$db = getDB();
$stmt = $db->prepare("SELECT a.*, af.form_name FROM applications a JOIN admission_forms af ON a.form_id = af.id WHERE a.application_ref = ? AND a.status = 'accepted' LIMIT 1");
$stmt->execute([$ref]);
$app = $stmt->fetch();

$pageTitle = 'Admission Letter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Letter - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if ($app): ?>
                <div class="card shadow-sm border-success">
                    <div class="card-body p-5 text-center">
                        <i class="fas fa-graduation-cap fa-4x text-success mb-3"></i>
                        <h2 class="fw-bold text-success">Congratulations!</h2>
                        <h4 class="mb-4"><?= SCHOOL_NAME ?></h4>
                        <hr>
                        <p class="lead">Dear <strong><?= sanitizeInput($app['first_name'] . ' ' . $app['last_name']) ?></strong>,</p>
                        <p>We are pleased to inform you that your application (<strong><?= $ref ?></strong>) has been <span class="text-success fw-bold">ACCEPTED</span>.</p>
                        <p>You are hereby offered provisional admission into <strong><?= sanitizeInput($app['class_applying']) ?></strong> for the academic session.</p>
                        <hr>
                        <p class="text-muted">Please visit the school with this letter and your credentials for verification and clearance.</p>
                        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
                        <a href="application-status.php?ref=<?= $ref ?>" class="btn btn-outline-secondary">Back</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning text-center">No admission letter found for this reference or application not yet accepted.</div>
                <div class="text-center"><a href="application-status.php" class="btn btn-primary">Check Status</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
