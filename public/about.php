<?php
require_once __DIR__ . '/../config/app.php';
$pageTitle = 'About Us';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?= SCHOOL_NAME ?></title>
    <link rel="icon" type="image/jpeg" href="/sms-peculiar-college/assets/images/logo.jpg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark public-header">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="/sms-peculiar-college/assets/images/logo.jpg" alt="" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold); padding: 2px;">
                <?= SCHOOL_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link" href="admission.php">Admission</a></li>
                    <li class="nav-item"><a class="nav-link" href="application-status.php">Check Status</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-gold fw-bold px-4" href="../auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Student Portal
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="page-title-bar">
        <div class="container text-center">
            <h1 class="animate-fade-up">About <?= SCHOOL_NAME ?></h1>
            <p class="animate-fade-up" style="animation-delay:0.1s"><?= SCHOOL_MOTTO ?></p>
        </div>
    </section>

    <section class="py-5" style="margin-top: -40px; position: relative; z-index: 2;">
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-lg" style="border-radius: var(--radius-xl);">
                        <div class="card-body p-5 text-center">
                            <img src="/sms-peculiar-college/assets/images/logo.jpg" alt="<?= SCHOOL_NAME ?>" style="max-width: 120px; border-radius: 50%; border: 3px solid var(--gold); padding: 3px; margin-bottom: 16px;">
                            <h2 class="fw-bold" style="color: var(--primary);">Welcome to <?= SCHOOL_NAME ?></h2>
                            <div style="width: 60px; height: 3px; background: var(--gradient-gold); margin: 12px auto 20px;"></div>
                            <p style="color: var(--text-muted); font-size: 16px; line-height: 1.8;"><?= SCHOOL_NAME ?> is a premier educational institution dedicated to providing quality education that nurtures academic excellence, character development, and leadership skills in every student. Located in the serene environment of Plateau State, our school offers a conducive learning atmosphere with state-of-the-art facilities and a team of highly qualified educators committed to bringing out the best in every child.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="feature-card h-100 p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: var(--gradient-gold); display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--primary);">
                                <i class="fas fa-eye"></i>
                            </div>
                            <h4 class="fw-bold mb-0" style="color: var(--primary);">Our Vision</h4>
                        </div>
                        <p style="color: var(--text-muted); line-height: 1.8;"><?= SCHOOL_VISION ?></p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="feature-card h-100 p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="width: 50px; height: 50px; border-radius: 12px; background: var(--gradient-gold); display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--primary);">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <h4 class="fw-bold mb-0" style="color: var(--primary);">Our Mission</h4>
                        </div>
                        <p style="color: var(--text-muted); line-height: 1.8;"><?= SCHOOL_MISSION ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Our Core Values</h2>
                <p class="section-subtitle">The principles that guide everything we do.</p>
            </div>
            <div class="row g-4 justify-content-center">
                <?php foreach (explode(', ', SCHOOL_VALUES) as $value): ?>
                <div class="col-md-4 col-lg-2">
                    <div class="feature-card text-center py-4">
                        <div class="card-icon mx-auto mb-3" style="width: 64px; height: 64px; border-radius: 50%; font-size: 28px;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h6 class="fw-bold mb-0" style="color: var(--primary);"><?= trim($value) ?></h6>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Contact Information</h2>
                <p class="section-subtitle">Get in touch with us.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="contact-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h6>Our Location</h6>
                        <p><?= SCHOOL_ADDRESS ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-card">
                        <i class="fas fa-phone-alt"></i>
                        <h6>Call Us</h6>
                        <p><?= SCHOOL_PHONE ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-card">
                        <i class="fas fa-envelope"></i>
                        <h6>Email Us</h6>
                        <p><?= SCHOOL_EMAIL ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5>
                        <img src="/sms-peculiar-college/assets/images/logo.jpg" alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--gold); padding: 2px; margin-right: 8px;">
                        <?= SCHOOL_NAME ?>
                    </h5>
                    <p class="small mt-2" style="color: rgba(255,255,255,0.6);"><?= SCHOOL_MOTTO ?></p>
                    <div class="social-links mt-3">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
                        <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled small">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="gallery.php">Gallery</a></li>
                        <li><a href="admission.php">Admission</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6>Portals</h6>
                    <ul class="list-unstyled small">
                        <li><a href="../auth/login.php">Student Portal</a></li>
                        <li><a href="../auth/login.php">Parent Portal</a></li>
                        <li><a href="../auth/login.php">Staff Portal</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h6>Contact Us</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2" style="color: var(--gold);"></i><?= SCHOOL_ADDRESS ?></li>
                        <li class="mb-2"><i class="fas fa-phone me-2" style="color: var(--gold);"></i><?= SCHOOL_PHONE ?></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2" style="color: var(--gold);"></i><?= SCHOOL_EMAIL ?></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?= date('Y') ?> <?= SCHOOL_NAME ?>. All rights reserved. | <?= SCHOOL_MOTTO ?></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
