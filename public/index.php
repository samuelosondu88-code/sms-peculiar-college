<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Welcome';
$heroImg = getRandomHeroImage();
$gallery = getGalleryByDate();
$allImages = getSchoolImages();
shuffle($allImages);
$featureImgs = array_slice($allImages, 0, 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SCHOOL_NAME ?> - Excellence in Education</title>
    <meta name="description" content="Peculiar International College - A premier institution committed to academic excellence and character development.">
    <link rel="icon" type="image/jpeg" href="/sms-peculiar-college/assets/images/logo.jpg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, rgba(11,31,58,0.88), rgba(26,53,85,0.82)), url('<?= $heroImg ?>') center/cover no-repeat;
            min-height: 90vh;
            display: flex;
            align-items: center;
        }
    </style>
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
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
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

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <p class="slogan animate-fade-up">"Raising Future Leaders Through Excellence and Character."</p>
                    <h1 class="animate-fade-up" style="animation-delay:0.1s"><?= SCHOOL_NAME ?></h1>
                    <p class="subtitle animate-fade-up" style="animation-delay:0.2s">
                        Building future leaders through academic excellence, character development, and innovative education. Join us in shaping tomorrow's champions today.
                    </p>
                    <div class="mt-4 animate-fade-up" style="animation-delay:0.3s">
                        <a href="admission.php" class="btn btn-gold btn-lg fw-bold me-3 mb-2 px-4">
                            <i class="fas fa-file-signature me-2"></i>Apply Now
                        </a>
                        <a href="../auth/login.php" class="btn btn-outline-light btn-lg fw-bold me-3 mb-2 px-4">
                            <i class="fas fa-user-graduate me-2"></i>Student Portal
                        </a>
                        <a href="contact.php" class="btn btn-outline-light btn-lg fw-bold mb-2 px-4">
                            <i class="fas fa-phone-alt me-2"></i>Contact Us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-bar">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-md-3 stat-item">
                    <div class="number" data-count="500">0</div>
                    <div class="label">Students Enrolled</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <div class="number" data-count="45">0</div>
                    <div class="label">Expert Teachers</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <div class="number" data-count="30">0</div>
                    <div class="label">Years of Excellence</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <div class="number" data-count="98">0</div>
                    <div class="label">Pass Rate (%)</div>
                </div>
            </div>
        </div>
    </section>

    <section class="welcome-section">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <div class="position-relative">
                        <img src="/sms-peculiar-college/assets/images/logo.jpg" alt="<?= SCHOOL_NAME ?>" class="img-fluid rounded-4 shadow-lg" style="max-width: 90%;">
                        <div class="position-absolute bottom-0 end-0 bg-gold text-dark p-3 rounded-3 shadow" style="background: var(--gold);">
                            <i class="fas fa-quote-right fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <h5 class="text-uppercase" style="color: var(--gold); letter-spacing: 2px; font-weight: 600;">Welcome to <?= SCHOOL_NAME ?></h5>
                    <h2 class="section-title text-start mb-3">Excellence in Education, Character in Life</h2>
                    <div class="principal-card animate-fade-up">
                        <i class="fas fa-quote-left quote-icon"></i>
                        <p class="fw500 mb-2" style="font-size: 17px; color: var(--text-dark);">
                            "At <?= SCHOOL_NAME ?>, we believe every child is peculiar and uniquely gifted. Our mission is to nurture these gifts through a holistic education that balances academic rigor with moral and character development. We are committed to raising future leaders who will make a positive impact in their communities and the world at large."
                        </p>
                        <div class="d-flex align-items-center mt-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; background: var(--gradient-gold); color: var(--primary); font-weight: 800; font-size: 18px;">P</div>
                            <div>
                                <strong style="color: var(--primary);">Principal</strong>
                                <div style="font-size: 13px; color: var(--text-muted);">Peculiar International College</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Why Choose <?= SCHOOL_NAME ?>?</h2>
                <p class="section-subtitle">We provide a holistic education that nurtures academic excellence and moral values.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="card-icon mx-auto"><i class="fas fa-book-open"></i></div>
                        <h5 class="fw-bold mb-2">Academic Excellence</h5>
                        <p class="text-muted small">Curriculum designed to challenge and inspire students to reach their full potential.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="card-icon mx-auto"><i class="fas fa-users"></i></div>
                        <h5 class="fw-bold mb-2">Expert Teachers</h5>
                        <p class="text-muted small">Highly qualified and dedicated educators committed to student success.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="card-icon mx-auto"><i class="fas fa-laptop-code"></i></div>
                        <h5 class="fw-bold mb-2">Modern Facilities</h5>
                        <p class="text-muted small">State-of-the-art classrooms, laboratories, and digital learning resources.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="card-icon mx-auto"><i class="fas fa-shield-alt"></i></div>
                        <h5 class="fw-bold mb-2">Safe Environment</h5>
                        <p class="text-muted small">Secure campus with 24/7 monitoring and a nurturing atmosphere.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="card-icon mx-auto"><i class="fas fa-hand-holding-heart"></i></div>
                        <h5 class="fw-bold mb-2">Character Development</h5>
                        <p class="text-muted small">Focus on discipline, integrity, and leadership skills.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card text-center">
                        <div class="card-icon mx-auto"><i class="fas fa-globe-africa"></i></div>
                        <h5 class="fw-bold mb-2">Global Perspective</h5>
                        <p class="text-muted small">Preparing students for a connected world with international standards.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <h5 class="text-uppercase" style="color: var(--gold); letter-spacing: 2px; font-weight: 600;">Our Vision & Mission</h5>
                    <h2 class="section-title text-start mb-4">Shaping the Future of Education</h2>
                    <div class="d-flex gap-3 mb-4">
                        <div style="min-width: 48px; height: 48px; border-radius: 12px; background: var(--gradient-gold); display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--primary);">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold" style="color: var(--primary);">Our Vision</h5>
                            <p class="text-muted"><?= SCHOOL_VISION ?></p>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div style="min-width: 48px; height: 48px; border-radius: 12px; background: var(--gradient-gold); display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--primary);">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold" style="color: var(--primary);">Our Mission</h5>
                            <p class="text-muted"><?= SCHOOL_MISSION ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <?php $captions = ['Modern Classrooms', 'Science Laboratory', 'Computer Lab', 'School Library', 'Sports Activities', 'Graduation Ceremony', 'Cultural Day', 'Award Ceremony']; ?>
                        <?php foreach ($featureImgs as $i => $imgUrl): ?>
                        <div class="col-6">
                            <div class="img-card" style="height: 180px;">
                                <img src="<?= $imgUrl ?>" alt="<?= $captions[$i] ?? 'School Image' ?>" loading="lazy">
                                <div class="overlay"><?= $captions[$i] ?? 'School Facility' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
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

    <section class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Upcoming Events</h2>
                <p class="section-subtitle">Stay connected with school activities and important dates.</p>
            </div>
            <div class="row g-4">
                <?php
                $events = [
                    ['day' => '15', 'month' => 'Sep', 'title' => 'Resumption Day - 1st Term', 'desc' => 'All students resume for the first term of the academic session.'],
                    ['day' => '25', 'month' => 'Sep', 'title' => 'Opening Assembly & Orientation', 'desc' => 'Welcome ceremony for new and returning students.'],
                    ['day' => '01', 'month' => 'Oct', 'title' => 'Independence Day Celebration', 'desc' => 'School-wide celebration with cultural activities and performances.'],
                    ['day' => '15', 'month' => 'Nov', 'title' => 'Mid-Term Break', 'desc' => 'School closes for mid-term break. Students resume on November 20th.'],
                ];
                foreach ($events as $e):
                ?>
                <div class="col-md-6">
                    <div class="event-card">
                        <div class="event-date">
                            <div class="day"><?= $e['day'] ?></div>
                            <div class="month"><?= $e['month'] ?></div>
                        </div>
                        <div class="event-info">
                            <h6><?= $e['title'] ?></h6>
                            <p><?= $e['desc'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">What Parents & Students Say</h2>
                <p class="section-subtitle">Hear from our school community.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="stars mb-2">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="quote">"Peculiar International College has transformed my child's academic performance and character. The teachers are dedicated and the facilities are top-notch."</p>
                        <div class="author">Mrs. Adaeze Obi</div>
                        <div class="role">Parent</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="stars mb-2">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="quote">"The best decision we made for our daughter's education. The holistic approach to learning has helped her excel both academically and socially."</p>
                        <div class="author">Mr. Chidi Okonkwo</div>
                        <div class="role">Parent</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="stars mb-2">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p class="quote">"I love studying at Peculiar International College! The teachers make learning fun and I have made wonderful friends. The computer lab is my favorite place."</p>
                        <div class="author">Chidi Okonkwo</div>
                        <div class="role">Student</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container text-center">
            <h2 class="section-title">Start Your Journey Today</h2>
            <p class="section-subtitle">Admission is now open for the 2025/2026 academic session. Purchase your application form online.</p>
            <a href="admission.php" class="btn btn-gold btn-lg fw-bold px-5">
                <i class="fas fa-file-signature me-2"></i>Apply Now
            </a>
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
                    <p style="color: rgba(255,255,255,0.6); font-size: 14px;"><?= SCHOOL_VISION ?></p>
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
                        <li><a href="application-status.php">Check Status</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h6>Portals</h6>
                    <ul class="list-unstyled small">
                        <li><a href="../auth/login.php">Student Portal</a></li>
                        <li><a href="../auth/login.php">Parent Portal</a></li>
                        <li><a href="../auth/login.php">Staff Portal</a></li>
                        <li><a href="../auth/login.php">Admin Portal</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h6>Contact Us</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2" style="color: var(--gold);"></i><?= SCHOOL_ADDRESS ?></li>
                        <li class="mb-2"><i class="fas fa-phone me-2" style="color: var(--gold);"></i><?= SCHOOL_PHONE ?></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2" style="color: var(--gold);"></i><?= SCHOOL_EMAIL ?></li>
                        <li class="mb-2"><i class="fas fa-clock me-2" style="color: var(--gold);"></i>Mon - Fri: 7:30 AM - 4:00 PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?= date('Y') ?> <?= SCHOOL_NAME ?>. All rights reserved. | <?= SCHOOL_MOTTO ?></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/sms-peculiar-college/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.dataset.count);
                const increment = target / 50;
                let current = 0;
                const update = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.round(current);
                        requestAnimationFrame(update);
                    } else {
                        counter.textContent = target + '+';
                    }
                };
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) { update(); observer.disconnect(); }
                    });
                });
                observer.observe(counter);
            });
        });
    </script>
</body>
</html>
