<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Gallery';
$galleryData = getGalleryByDate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - <?= SCHOOL_NAME ?></title>
    <link rel="icon" type="image/jpeg" href="/sms-peculiar-college/assets/images/logo.jpg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .gallery-item {
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
            height: 220px;
            background: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .gallery-item:hover {
            transform: scale(1.03);
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-item .placeholder-icon {
            font-size: 48px;
            color: var(--text-muted);
            opacity: 0.5;
        }
        .gallery-item .overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            padding: 20px 16px 12px;
            font-weight: 600;
            font-size: 14px;
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="gallery.php">Gallery</a></li>
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
            <h1 class="animate-fade-up">Our Gallery</h1>
            <p class="animate-fade-up" style="animation-delay:0.1s">Take a visual tour of our school facilities, events, and activities.</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-4">
                <div class="btn-group" role="group">
                    <button class="btn btn-primary filter-btn active" data-filter="all">All</button>
                    <button class="btn btn-outline-primary filter-btn" data-filter="facilities">Facilities</button>
                    <button class="btn btn-outline-primary filter-btn" data-filter="events">Events</button>
                    <button class="btn btn-outline-primary filter-btn" data-filter="academics">Academics</button>
                    <button class="btn btn-outline-primary filter-btn" data-filter="sports">Sports</button>
                </div>
            </div>
            <?php foreach ($galleryData as $group): ?>
            <div class="mb-5">
                <h4 class="fw-bold mb-3" style="color: var(--primary);">
                    <i class="fas fa-folder-open me-2" style="color: var(--gold);"></i><?= $group['label'] ?>
                    <span class="badge bg-gold ms-2" style="background: var(--gold); color: var(--primary);"><?= count($group['images']) ?> photos</span>
                </h4>
                <div class="row g-3">
                    <?php foreach ($group['images'] as $img): ?>
                    <div class="col-md-4 col-6 gallery-col" data-category="<?= $group['category'] ?>">
                        <div class="gallery-item" onclick="openModal(this)">
                            <img src="<?= $img ?>" alt="<?= $group['label'] ?>" loading="lazy">
                            <div class="overlay"><i class="fas fa-search-plus me-1"></i> View</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
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

    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
                <div class="modal-body p-0">
                    <img id="modalImage" src="" alt="" style="width: 100%; height: auto; display: block;">
                </div>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white rounded-circle" style="width: 32px; height: 32px;" data-bs-dismiss="modal"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(el) {
            const img = el.querySelector('img');
            const modalImg = document.getElementById('modalImage');
            modalImg.src = img.src;
            modalImg.alt = img.alt;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('btn-primary');
                    b.classList.add('btn-outline-primary');
                });
                this.classList.remove('btn-outline-primary');
                this.classList.add('btn-primary');
                const filter = this.dataset.filter;
                document.querySelectorAll('.gallery-col').forEach(col => {
                    col.style.display = (filter === 'all' || col.dataset.category === filter) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>
