<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';

$ref = sanitizeInput($_GET['ref'] ?? $_POST['ref'] ?? '');
if (empty($ref)) {
    redirect('/public/admission.php');
}

$db = getDB();
$stmt = $db->prepare("SELECT a.*, ap.status as payment_status FROM applications a LEFT JOIN application_payments ap ON a.id = ap.application_id WHERE a.application_ref = ? LIMIT 1");
$stmt->execute([$ref]);
$application = $stmt->fetch();

if (!$application) {
    die('Invalid application reference.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
        'gender' => sanitizeInput($_POST['gender'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'class_applying' => sanitizeInput($_POST['class_applying'] ?? ''),
        'previous_school' => sanitizeInput($_POST['previous_school'] ?? ''),
        'parent_name' => sanitizeInput($_POST['parent_name'] ?? ''),
        'parent_phone' => sanitizeInput($_POST['parent_phone'] ?? ''),
        'parent_email' => sanitizeInput($_POST['parent_email'] ?? ''),
        'parent_occupation' => sanitizeInput($_POST['parent_occupation'] ?? ''),
    ];

    $validator = new Validator();
    $validator->required('date_of_birth', $data['date_of_birth'], 'Date of Birth')
              ->date('date_of_birth', $data['date_of_birth'])
              ->required('gender', $data['gender'])
              ->inList('gender', $data['gender'], ['male','female','other'])
              ->required('address', $data['address'], 'Address')
              ->required('class_applying', $data['class_applying'], 'Class Applying For')
              ->required('parent_name', $data['parent_name'], 'Parent/Guardian Name')
              ->phone('parent_phone', $data['parent_phone']);

    $uploadedDocs = [];

    foreach (['passport', 'birth_cert', 'school_report'] as $doc) {
        if (isset($_FILES[$doc]) && $_FILES[$doc]['error'] === UPLOAD_ERR_OK) {
            $path = uploadFile($_FILES[$doc], 'documents/applications');
            if ($path) {
                $uploadedDocs[$doc] = $path;
            }
        }
    }

    if ($validator->passes()) {
        $stmt = $db->prepare("UPDATE applications SET date_of_birth = ?, gender = ?, address = ?, class_applying = ?, previous_school = ?, parent_name = ?, parent_phone = ?, parent_email = ?, parent_occupation = ?, documents = ?, status = 'submitted', submitted_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['date_of_birth'], $data['gender'], $data['address'],
            $data['class_applying'], $data['previous_school'],
            $data['parent_name'], $data['parent_phone'], $data['parent_email'],
            $data['parent_occupation'], json_encode($uploadedDocs),
            $application['id']
        ]);

        $subject = "Application Submitted - " . SCHOOL_NAME;
        $body = "<p>Dear {$application['first_name']},</p>
                <p>Your application ({$ref}) has been submitted successfully.</p>
                <p>We will review your application and notify you of the status.</p>
                <p>Track your status: <a href='" . APP_URL . "/public/application-status.php?ref={$ref}'>Click here</a></p>";
        sendEmail($application['email'], $subject, $body);

        $success = 'Application submitted successfully! You will be notified of the outcome.';
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
    <title>Complete Application - <?= SCHOOL_NAME ?></title>
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
                <div class="col-lg-8">
                    <?php if ($success): ?>
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4 class="fw-bold"><?= $success ?></h4>
                            <p class="text-muted">Your reference number: <strong><?= $ref ?></strong></p>
                            <a href="application-status.php?ref=<?= $ref ?>" class="btn btn-primary">Track Application Status</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="fw-bold mb-0"><i class="fas fa-file-alt me-2"></i>Application Form</h5>
                            <small class="text-muted">Reference: <?= $ref ?> | Applicant: <?= sanitizeInput($application['first_name'] . ' ' . $application['last_name']) ?></small>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>

                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="ref" value="<?= $ref ?>">

                                <h6 class="fw-bold text-primary">Applicant Information</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label">Date of Birth *</label>
                                        <input type="date" name="date_of_birth" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Gender *</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Class Applying For *</label>
                                        <select name="class_applying" class="form-select" required>
                                            <option value="">Select</option>
                                            <option>JSS1</option><option>JSS2</option><option>JSS3</option>
                                            <option>SS1</option><option>SS2</option><option>SS3</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Residential Address *</label>
                                        <textarea name="address" class="form-control" rows="2" required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Previous School Attended</label>
                                        <input type="text" name="previous_school" class="form-control">
                                    </div>
                                </div>

                                <h6 class="fw-bold text-primary">Parent / Guardian Information</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="parent_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Phone *</label>
                                        <input type="tel" name="parent_phone" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="parent_email" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Occupation</label>
                                        <input type="text" name="parent_occupation" class="form-control">
                                    </div>
                                </div>

                                <h6 class="fw-bold text-primary">Upload Documents</h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label">Passport Photo</label>
                                        <input type="file" name="passport" class="form-control" accept="image/*">
                                        <small class="text-muted">Max 500KB, JPG/PNG</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Birth Certificate</label>
                                        <input type="file" name="birth_cert" class="form-control" accept=".pdf,.jpg,.png">
                                        <small class="text-muted">PDF or image</small>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">School Report</label>
                                        <input type="file" name="school_report" class="form-control" accept=".pdf,.jpg,.png">
                                        <small class="text-muted">PDF or image</small>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Application
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
