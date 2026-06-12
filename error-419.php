<?php
$pageTitle = 'Session Expired';
$base_url = defined('BASE_URL') ? BASE_URL : '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 Session Expired - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
    <div class="container text-center">
        <h1 class="display-1 text-warning fw-bold">419</h1>
        <h3 class="mb-3">Session Expired</h3>
        <p class="text-muted mb-4">Your session has expired or the security token is invalid. Please refresh and try again.</p>
        <a href="<?= $base_url ?>/auth/login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt me-2"></i>Login Again</a>
        <a href="<?= $base_url ?>/public/index.php" class="btn btn-outline-secondary ms-2">Back to Home</a>
    </div>
</body>
</html>
