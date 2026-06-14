<?php
$role = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$currentPath = $currentDir . '/' . $currentPage;
?>
<div class="bg-primary text-white" id="sidebar-wrapper" style="min-height: 100vh; width: 250px;">
    <div class="sidebar-heading text-center py-4 border-bottom border-light">
        <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="<?= SCHOOL_NAME ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; margin-bottom: 8px;">
        <h5 class="mb-0 fw-bold"><?= SCHOOL_NAME ?></h5>
        <small class="opacity-75"><?= ucfirst($role) ?> Portal</small>
    </div>
    <div class="list-group list-group-flush">
        <a href="<?= BASE_URL ?>/<?= $role ?>/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt me-3"></i>Dashboard
        </a>
        <?php if ($role === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link" href="#resultsMenu" data-bs-toggle="collapse" data-bs-target="#resultsMenu">
                <i class="fas fa-chart-bar me-2"></i> Results
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse show" id="resultsMenu">
                <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/enter.php"><i class="fas fa-pen me-2"></i>Enter Scores</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/preview.php"><i class="fas fa-eye me-2"></i>Preview</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/submit.php"><i class="fas fa-paper-plane me-2"></i>Submit</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/manage.php"><i class="fas fa-table me-2"></i>Manage Results</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/approve.php"><i class="fas fa-check-double me-2"></i>Approvals</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/pins.php"><i class="fas fa-key me-2"></i>Result PINs</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/promotion.php"><i class="fas fa-arrow-up me-2"></i>Promotion</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/psychomotor.php"><i class="fas fa-running me-2"></i>Psychomotor</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/affective.php"><i class="fas fa-heart me-2"></i>Affective</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/comments.php"><i class="fas fa-comment me-2"></i>Remarks</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/pdf.php"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF Report Cards</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/annual.php"><i class="fas fa-calendar-alt me-2"></i>Annual Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/import.php"><i class="fas fa-file-import me-2"></i>Import Scores</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/results/broadcast.php"><i class="fas fa-bullhorn me-2"></i>Broadcast</a></li>
                </ul>
            </div>
        </li>
        <a href="<?= BASE_URL ?>/admin/users.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users me-3"></i>Users
        </a>
        <a href="<?= BASE_URL ?>/admin/classes.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'classes.php' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard me-3"></i>Classes
        </a>
        <a href="<?= BASE_URL ?>/admin/subjects.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'subjects.php' ? 'active' : '' ?>">
            <i class="fas fa-book me-3"></i>Subjects
        </a>
        <a href="<?= BASE_URL ?>/admin/timetable.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'timetable.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt me-3"></i>Timetable
        </a>
        <a href="<?= BASE_URL ?>/admin/exams.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'exams.php' ? 'active' : '' ?>">
            <i class="fas fa-file-alt me-3"></i>Exams
        </a>
        <a href="<?= BASE_URL ?>/admin/lesson-plans/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'lesson-plans' ? 'active' : '' ?>">
            <i class="fas fa-book-open me-3"></i>Lesson Plans
        </a>
        <a href="<?= BASE_URL ?>/admin/cbt/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'cbt' ? 'active' : '' ?>">
            <i class="fas fa-laptop me-3"></i>CBT Exams
        </a>
        <a href="<?= BASE_URL ?>/admin/exams/monitor.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'exams' && $currentPage === 'monitor.php' ? 'active' : '' ?>">
            <i class="fas fa-tv me-3"></i>Exam Monitoring
        </a>
        <a href="<?= BASE_URL ?>/admin/fees.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'fees.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill me-3"></i>Fees
        </a>
        <a href="<?= BASE_URL ?>/admin/applications.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'applications.php' ? 'active' : '' ?>">
            <i class="fas fa-file-signature me-3"></i>Applications
        </a>
        <a href="<?= BASE_URL ?>/admin/library.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'library.php' ? 'active' : '' ?>">
            <i class="fas fa-book-open me-3"></i>Library
        </a>
        <a href="<?= BASE_URL ?>/admin/transport.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'transport.php' ? 'active' : '' ?>">
            <i class="fas fa-bus me-3"></i>Transport
        </a>
        <a href="<?= BASE_URL ?>/admin/hostel.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'hostel.php' ? 'active' : '' ?>">
            <i class="fas fa-bed me-3"></i>Hostel
        </a>
        <a href="<?= BASE_URL ?>/admin/notices.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'notices.php' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn me-3"></i>Notices
        </a>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog me-3"></i>Settings
        </a>
        <hr class="border-light my-1">
        <a href="<?= BASE_URL ?>/admin/security/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'security' ? 'active' : '' ?>">
            <i class="fas fa-shield-alt me-3"></i>Security
        </a>
        <a href="<?= BASE_URL ?>/admin/pins/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'pins' ? 'active' : '' ?>">
            <i class="fas fa-key me-3"></i>Student PINs
        </a>
        <a href="<?= BASE_URL ?>/admin/subscriptions/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'subscriptions' ? 'active' : '' ?>">
            <i class="fas fa-credit-card me-3"></i>Subscriptions
        </a>
        <?php elseif ($role === 'teacher'): ?>
        <a href="<?= BASE_URL ?>/teacher/classes.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'classes.php' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard me-3"></i>My Classes
        </a>
        <a href="<?= BASE_URL ?>/teacher/attendance.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <i class="fas fa-check-circle me-3"></i>Attendance
        </a>
        <a href="<?= BASE_URL ?>/teacher/results/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'results' ? 'active' : '' ?>">
            <i class="fas fa-star me-3"></i>Results
        </a>
        <a href="<?= BASE_URL ?>/teacher/assignments.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'assignments.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks me-3"></i>Assignments
        </a>
        <a href="<?= BASE_URL ?>/teacher/lesson-notes.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'lesson-notes.php' ? 'active' : '' ?>">
            <i class="fas fa-sticky-note me-3"></i>Lesson Notes
        </a>
        <a href="<?= BASE_URL ?>/teacher/lesson-plans/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'lesson-plans' ? 'active' : '' ?>">
            <i class="fas fa-book-open me-3"></i>Lesson Plans
        </a>
        <a href="<?= BASE_URL ?>/teacher/exams/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'exams' && $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-file-alt me-3"></i>Exams
        </a>
        <a href="<?= BASE_URL ?>/teacher/exams/security-settings.php?exam_id=<?= $currentDir === 'exams' && isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : '' ?>" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'security-settings.php' ? 'active' : '' ?>">
            <i class="fas fa-shield-alt me-3"></i>Exam Security
        </a>
        <a href="<?= BASE_URL ?>/teacher/exams/monitor.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'monitor.php' ? 'active' : '' ?>">
            <i class="fas fa-tv me-3"></i>Live Monitor
        </a>
        <a href="<?= BASE_URL ?>/teacher/timetable.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'timetable.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt me-3"></i>Timetable
        </a>
        <li class="nav-item">
            <a class="nav-link collapsed" href="#teacherResultsMenu" data-bs-toggle="collapse" data-bs-target="#teacherResultsMenu">
                <i class="fas fa-chart-bar me-2"></i> Results
                <i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse" id="teacherResultsMenu">
                <ul class="nav flex-column ms-3">
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/enter.php"><i class="fas fa-pen me-2"></i>Enter Scores</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/submit.php"><i class="fas fa-paper-plane me-2"></i>Submit</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/preview.php"><i class="fas fa-eye me-2"></i>Preview</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/psychomotor.php"><i class="fas fa-running me-2"></i>Psychomotor</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/affective.php"><i class="fas fa-heart me-2"></i>Affective</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/comments.php"><i class="fas fa-comment me-2"></i>Remarks</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/pdf.php"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF Report Cards</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/annual.php"><i class="fas fa-calendar-alt me-2"></i>Annual Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/teacher/results/import.php"><i class="fas fa-file-import me-2"></i>Import Scores</a></li>
                </ul>
            </div>
        </li>
        <a href="<?= BASE_URL ?>/teacher/classroom/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'classroom' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher me-3"></i>Virtual Classroom
        </a>
        <?php elseif ($role === 'student'): ?>
        <a href="<?= BASE_URL ?>/student/cbt/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'cbt' && $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-laptop me-3"></i>CBT Exams
        </a>
        <a href="<?= BASE_URL ?>/student/cbt/analytics.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar me-3"></i>Performance
        </a>
        <a href="<?= BASE_URL ?>/student/exams/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'exams' ? 'active' : '' ?>">
            <i class="fas fa-file-alt me-3"></i>Teacher Exams
        </a>
        <a href="<?= BASE_URL ?>/student/timetable.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'timetable.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt me-3"></i>Timetable
        </a>
        <a href="<?= BASE_URL ?>/student/attendance.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <i class="fas fa-check-circle me-3"></i>Attendance
        </a>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/student/results/index.php"><i class="fas fa-chart-bar me-2"></i>My Results</a></li>
        <a href="<?= BASE_URL ?>/student/assignments.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'assignments.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks me-3"></i>Assignments
        </a>
        <a href="<?= BASE_URL ?>/student/fees.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'fees.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill me-3"></i>Fees
        </a>
        <a href="<?= BASE_URL ?>/student/library.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'library.php' ? 'active' : '' ?>">
            <i class="fas fa-book-open me-3"></i>Library
        </a>
        <a href="<?= BASE_URL ?>/student/classroom/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'classroom' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher me-3"></i>Virtual Classroom
        </a>
        <?php elseif ($role === 'parent'): ?>
        <a href="<?= BASE_URL ?>/parent/children.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'children.php' ? 'active' : '' ?>">
            <i class="fas fa-child me-3"></i>My Children
        </a>
        <a href="<?= BASE_URL ?>/parent/attendance.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">
            <i class="fas fa-check-circle me-3"></i>Attendance
        </a>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/parent/results/index.php"><i class="fas fa-chart-bar me-2"></i>Children's Results</a></li>
        <a href="<?= BASE_URL ?>/result-checker.php" class="list-group-item list-group-item-action bg-transparent text-white">
            <i class="fas fa-search me-3"></i>PIN Result Checker
        </a>
        <a href="<?= BASE_URL ?>/parent/fees.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'fees.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill me-3"></i>Pay Fees
        </a>
        <a href="<?= BASE_URL ?>/parent/timetable.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'timetable.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-alt me-3"></i>Timetable
        </a>
        <a href="<?= BASE_URL ?>/parent/complaints.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'complaints.php' ? 'active' : '' ?>">
            <i class="fas fa-comment-dots me-3"></i>Complaints
        </a>
        <a href="<?= BASE_URL ?>/parent/classroom/index.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentDir === 'classroom' ? 'active' : '' ?>">
            <i class="fas fa-chalkboard-teacher me-3"></i>Virtual Classroom
        </a>
        <?php elseif ($role === 'accountant'): ?>
        <a href="<?= BASE_URL ?>/accountant/fees.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'fees.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill me-3"></i>Fee Management
        </a>
        <a href="<?= BASE_URL ?>/accountant/payments.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'payments.php' ? 'active' : '' ?>">
            <i class="fas fa-credit-card me-3"></i>Payments
        </a>
        <a href="<?= BASE_URL ?>/accountant/expenses.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'expenses.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart me-3"></i>Expenses
        </a>
        <a href="<?= BASE_URL ?>/accountant/payroll.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'payroll.php' ? 'active' : '' ?>">
            <i class="fas fa-wallet me-3"></i>Payroll
        </a>
        <a href="<?= BASE_URL ?>/accountant/reports.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar me-3"></i>Reports
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/messages.php" class="list-group-item list-group-item-action bg-transparent text-white <?= $currentPage === 'messages.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope me-3"></i>Messages
            <?php if ($notifCount > 0): ?>
            <span class="badge bg-danger float-end"><?= $notifCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="list-group-item list-group-item-action bg-transparent text-white border-top mt-2">
            <i class="fas fa-sign-out-alt me-3"></i>Logout
        </a>
    </div>
</div>
