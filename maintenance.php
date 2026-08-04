<?php
http_response_code(503);
$base_url = defined('BASE_URL') ? BASE_URL : '/';
$maintenanceMessage = getenv('APP_MAINTENANCE_MESSAGE') ?: 'We are currently performing scheduled maintenance. Please check back shortly.';
$retrySeconds = (int)(getenv('APP_MAINTENANCE_RETRY') ?: 3600);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Peculiar International College</title>
    <meta http-equiv="refresh" content="<?= $retrySeconds ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
    <div class="container text-center">
        <div class="display-1 text-secondary fw-bold"><i class="fas fa-tools"></i></div>
        <h2 class="fw-bold mt-3">Under Maintenance</h2>
        <p class="text-muted mb-4"><?= htmlspecialchars($maintenanceMessage) ?></p>
        <p class="small text-muted">We will be back shortly.</p>
    </div>
</body>
</html>
