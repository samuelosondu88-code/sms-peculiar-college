<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Contact Us';
$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    if ($name && $email && $message) {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message]);
        $msg = 'Thank you! Your message has been received. We will get back to you shortly.';
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?= SCHOOL_NAME ?></title>
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
                    <li class="nav-item"><a class="nav-link" href="/public/admission.php">Admission</a></li>
                    <li class="nav-item"><a class="nav-link" href="/public/application-status.php">Check Status</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/public/contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-primary">Contact Us</h2>
                <p class="text-muted">We'd love to hear from you. Get in touch with us.</p>
            </div>

            <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-4"><i class="fas fa-info-circle me-2 text-primary"></i>Get in Touch</h5>
                            <div class="mb-3">
                                <p class="mb-1"><strong><i class="fas fa-map-marker-alt me-2 text-primary"></i>Address</strong></p>
                                <p class="text-muted mb-0"><?= SCHOOL_ADDRESS ?></p>
                            </div>
                            <div class="mb-3">
                                <p class="mb-1"><strong><i class="fas fa-phone me-2 text-primary"></i>Phone</strong></p>
                                <p class="text-muted mb-0"><?= SCHOOL_PHONE ?></p>
                            </div>
                            <div class="mb-3">
                                <p class="mb-1"><strong><i class="fas fa-envelope me-2 text-primary"></i>Email</strong></p>
                                <p class="text-muted mb-0"><?= SCHOOL_EMAIL ?></p>
                            </div>
                            <div class="mb-3">
                                <p class="mb-1"><strong><i class="fas fa-clock me-2 text-primary"></i>Working Hours</strong></p>
                                <p class="text-muted mb-0">Monday - Friday: 8:00 AM - 4:00 PM</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4"><i class="fas fa-paper-plane me-2 text-primary"></i>Send Us a Message</h5>
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Subject</label>
                                        <input type="text" name="subject" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Message *</label>
                                        <textarea name="message" class="form-control" rows="5" required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="send_message" class="btn btn-primary btn-lg px-5 fw-bold">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </div>
                                </div>
                            </form>
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
