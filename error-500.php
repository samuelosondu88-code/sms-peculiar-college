<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
    <div class="container text-center">
        <div class="display-1 text-warning fw-bold">500</div>
        <h2 class="fw-bold mt-3">Server Error</h2>
        <p class="text-muted">Something went wrong. Please try again later.</p>
        <a href="<?= BASE_URL ?>/" class="btn btn-primary btn-lg mt-3"><i class="fas fa-home me-2"></i>Go Home</a>
    </div>
</body>
</html>
