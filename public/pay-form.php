<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$error = '';
$success = '';
$applicationRef = '';
$formId = 1;

$db = getDB();
$stmt = $db->prepare("SELECT * FROM admission_forms WHERE id = ? AND is_active = 1");
$stmt->execute([$formId]);
$form = $stmt->fetch();

if (!$form) {
    $error = 'Admission forms are not available at this time. Please contact the school.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $firstName = sanitizeInput($_POST['first_name'] ?? '');
    $lastName = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');

    $validator = new Validator();
    $validator->required('first_name', $firstName, 'First Name')
              ->required('last_name', $lastName, 'Last Name')
              ->required('email', $email)
              ->email('email', $email)
              ->required('phone', $phone, 'Phone Number')
              ->phone('phone', $phone);

    if ($validator->passes()) {
        $ref = generateReference('PEC');
        $stmt = $db->prepare("INSERT INTO applications (form_id, first_name, last_name, email, phone, application_ref, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'draft')");
        $stmt->execute([$formId, $firstName, $lastName, $email, $phone, $ref]);
        $applicationId = $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO application_payments (application_id, amount, payment_method, transaction_ref, status) VALUES (?, ?, 'card', ?, 'pending')");
        $paymentRef = generateReference('PAY');
        $stmt->execute([$applicationId, $form['price'], $paymentRef]);

        $_SESSION['application_id'] = $applicationId;
        $_SESSION['application_ref'] = $ref;
        $success = 'Application initiated! Please complete payment below.';
        $applicationRef = $ref;
    } else {
        $error = $validator->getFirstError();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Admission Form - <?= SCHOOL_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark public-header">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-school me-2"></i><?= SCHOOL_NAME ?></a>
        </div>
    </nav>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="fas fa-file-invoice fa-3x text-primary mb-2"></i>
                                <h4 class="fw-bold">Purchase Admission Form</h4>
                                <p class="text-muted">Pay <strong>₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?></strong> to begin your application</p>
                            </div>

                            <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>Application created! Your reference: <strong><?= $applicationRef ?></strong>
                            </div>
                            <div class="text-center mt-3">
                                <p>Choose payment method:</p>
                                <a href="#" class="btn btn-primary btn-lg w-100 mb-2" onclick="alert('Paystack/Flutterwave integration will go here. For now, use the bank transfer option below.');">
                                    <i class="fas fa-credit-card me-2"></i>Pay with Card
                                </a>
                                <p class="text-muted small my-2">- OR -</p>
                                <div class="card bg-light p-3">
                                    <h6 class="fw-bold">Bank Transfer Details</h6>
                                    <p class="mb-1 small">Bank: First Bank of Nigeria</p>
                                    <p class="mb-1 small">Account Name: Peculiar International College</p>
                                    <p class="mb-1 small">Account No: 2034567890</p>
                                    <hr>
                                    <a href="apply.php?ref=<?= $applicationRef ?>" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-check me-2"></i>I've Paid, Continue Application
                                    </a>
                                </div>
                            </div>
                            <?php else: ?>
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" name="first_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" name="last_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone *</label>
                                        <input type="tel" name="phone" class="form-control" required>
                                    </div>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                        <i class="fas fa-shopping-cart me-2"></i>Purchase Now - ₦<?= number_format(ADMISSION_FORM_PRICE, 0) ?>
                                    </button>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
