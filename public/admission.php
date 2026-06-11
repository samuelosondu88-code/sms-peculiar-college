<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Admission';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark public-header">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/public/index.php"><i class="fas fa-school me-2"></i><?= SCHOOL_NAME ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/public/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/public/admission.php">Admission</a></li>
                    <li class="nav-item"><a class="nav-link" href="/public/application-status.php">Check Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="/public/contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8">
                    <h2 class="fw-bold text-primary">Admission into <?= SCHOOL_NAME ?></h2>
                    <p>We are pleased to announce that admission is open for the 2025/2026 academic session. We offer a comprehensive education from JSS1 to SS3.</p>

                    <h5 class="fw-bold mt-4">Available Classes</h5>
                    <ul>
                        <li>Junior Secondary School: JSS1 - JSS3</li>
                        <li>Senior Secondary School: SS1 - SS3 (Science, Arts, Commercial)</li>
                    </ul>

                    <h5 class="fw-bold mt-4">Admission Requirements</h5>
                    <ul>
                        <li>Completed application form</li>
                        <li>Passport photograph</li>
                        <li>Birth certificate</li>
                        <li>Previous school report card</li>
                    </ul>

                    <h5 class="fw-bold mt-4">How to Apply</h5>
                    <ol>
                        <li>Click "Purchase Form" below</li>
                        <li>Pay the application fee of <strong>₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?></strong> online via card or bank transfer</li>
                        <li>Fill and submit the application form</li>
                        <li>Track your application status using your reference number</li>
                        <li>If accepted, download your admission letter</li>
                    </ol>

                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> The application form fee is <strong>₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?></strong> and is non-refundable.
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-file-alt fa-4x text-primary mb-3"></i>
                            <h5 class="fw-bold">Application Form</h5>
                            <p class="display-6 fw-bold text-primary mb-2">₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?></p>
                            <p class="text-muted small">One-time payment</p>
                            <a href="/public/pay-form.php" class="btn btn-primary btn-lg w-100 fw-bold">
                                <i class="fas fa-shopping-cart me-2"></i>Purchase Form
                            </a>
                            <hr>
                            <a href="/public/application-status.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search me-2"></i>Check Application Status
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer mt-5">
        <div class="container">
            <p class="text-center small text-white-50 mb-0">&copy; <?= date('Y') ?> <?= SCHOOL_NAME ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
