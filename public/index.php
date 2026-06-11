<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = 'Welcome';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SCHOOL_NAME ?> - Excellence in Education</title>
    <meta name="description" content="Peculiar International College - A premier institution committed to academic excellence and character development.">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendors/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark public-header">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/public/index.php">
                <i class="fas fa-school me-2"></i><?= SCHOOL_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/public/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/public/admission.php">Admission</a></li>
                    <li class="nav-item"><a class="nav-link" href="/public/application-status.php">Check Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="/public/contact.php">Contact</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-warning fw-bold" href="/auth/login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <h1><i class="fas fa-graduation-cap me-3"></i><?= SCHOOL_NAME ?></h1>
            <p class="lead">Building future leaders through academic excellence, character development, and innovative education. Join us in shaping tomorrow's champions today.</p>
            <div class="mt-4">
                <a href="/public/admission.php" class="btn btn-warning btn-lg fw-bold me-3">
                    <i class="fas fa-file-signature me-2"></i>Apply Now - ₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?>
                </a>
                <a href="/public/application-status.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-search me-2"></i>Check Application Status
                </a>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-primary">Why Choose <?= SCHOOL_NAME ?>?</h2>
                <p class="text-muted">We provide a holistic education that nurtures academic excellence and moral values.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-book-open"></i>
                        <h5 class="fw-bold">Academic Excellence</h5>
                        <p class="text-muted small">Curriculum designed to challenge and inspire students to reach their full potential.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-users"></i>
                        <h5 class="fw-bold">Expert Teachers</h5>
                        <p class="text-muted small">Highly qualified and dedicated educators committed to student success.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-laptop"></i>
                        <h5 class="fw-bold">Modern Facilities</h5>
                        <p class="text-muted small">State-of-the-art classrooms, laboratories, and digital learning resources.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-shield-alt"></i>
                        <h5 class="fw-bold">Safe Environment</h5>
                        <p class="text-muted small">Secure campus with 24/7 monitoring and a nurturing atmosphere.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-hand-holding-heart"></i>
                        <h5 class="fw-bold">Character Development</h5>
                        <p class="text-muted small">Focus on discipline, integrity, and leadership skills.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="fas fa-globe-africa"></i>
                        <h5 class="fw-bold">Global Perspective</h5>
                        <p class="text-muted small">Preparing students for a connected world with international standards.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container text-center">
            <h2 class="fw-bold text-primary">Start Your Journey Today</h2>
            <p class="text-muted mb-4">Admission is now open for the 2025/2026 academic session. Purchase your application form online.</p>
            <a href="/public/admission.php" class="btn btn-primary btn-lg fw-bold px-5">
                <i class="fas fa-file-signature me-2"></i>Apply Now - ₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?>
            </a>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5 class="fw-bold text-white"><?= SCHOOL_NAME ?></h5>
                    <p class="small">Excellence in Education, Character in Life.</p>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold text-white">Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li><a href="/public/admission.php" class="text-white-50">Admission</a></li>
                        <li><a href="/public/application-status.php" class="text-white-50">Check Status</a></li>
                        <li><a href="/auth/login.php" class="text-white-50">Portal Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold text-white">Contact</h6>
                    <ul class="list-unstyled small text-white-50">
                        <li><i class="fas fa-map-marker-alt me-2"></i><?= SCHOOL_ADDRESS ?></li>
                        <li><i class="fas fa-phone me-2"></i><?= SCHOOL_PHONE ?></li>
                        <li><i class="fas fa-envelope me-2"></i><?= SCHOOL_EMAIL ?></li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary my-3">
            <p class="text-center small text-white-50 mb-0">&copy; <?= date('Y') ?> <?= SCHOOL_NAME ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/main.js"></script>
</body>
</html>
