<?php http_response_code(403); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
    <div class="container text-center">
        <div class="display-1 text-danger fw-bold">403</div>
        <h2 class="fw-bold mt-3">Access Denied</h2>
        <p class="text-muted">You don't have permission to access this page.</p>
        <a href="/" class="btn btn-primary btn-lg mt-3"><i class="fas fa-home me-2"></i>Go Home</a>
    </div>
</body>
</html>
