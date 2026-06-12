<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function sanitizeInput(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateReference(string $prefix = 'PEC'): string {
    return $prefix . '-' . date('Y') . '-' . strtoupper(uniqid());
}

function generateReceiptNo(): string {
    return 'RCT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generatePasswordHash(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function formatCurrency(float $amount, string $currency = '₦'): string {
    return $currency . number_format($amount, 2);
}

function formatDate(string $date, string $format = 'd M, Y'): string {
    return date($format, strtotime($date));
}

function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    return date('d M', $timestamp);
}

function getRoleBadge(string $role): string {
    $badges = [
        'admin' => 'bg-danger',
        'teacher' => 'bg-primary',
        'student' => 'bg-success',
        'parent' => 'bg-warning text-dark',
        'accountant' => 'bg-info text-dark',
    ];
    $class = $badges[$role] ?? 'bg-secondary';
    return "<span class='badge {$class}'>{$role}</span>";
}

function getStatusBadge(string $status): string {
    $badges = [
        'active' => 'bg-success',
        'inactive' => 'bg-secondary',
        'suspended' => 'bg-danger',
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'paid' => 'bg-success',
        'unpaid' => 'bg-danger',
        'partial' => 'bg-warning text-dark',
        'present' => 'bg-success',
        'absent' => 'bg-danger',
        'late' => 'bg-warning text-dark',
        'excused' => 'bg-info text-dark',
        'submitted' => 'bg-info text-dark',
        'graded' => 'bg-success',
        'reviewing' => 'bg-warning text-dark',
        'accepted' => 'bg-success',
        'waitlisted' => 'bg-secondary',
        'in_progress' => 'bg-warning text-dark',
        'completed' => 'bg-success',
        'abandoned' => 'bg-danger',
    ];
    $class = $badges[$status] ?? 'bg-secondary';
    return "<span class='badge {$class}'>{$status}</span>";
}

function getGPA(float $score): string {
    if ($score >= 70) return 'A';
    if ($score >= 60) return 'B';
    if ($score >= 50) return 'C';
    if ($score >= 45) return 'D';
    if ($score >= 40) return 'E';
    return 'F';
}

function getGradePoint(string $grade): float {
    $points = ['A' => 4.0, 'B' => 3.0, 'C' => 2.0, 'D' => 1.5, 'E' => 1.0, 'F' => 0.0];
    return $points[$grade] ?? 0.0;
}

function getTotalStudents(): int {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM students WHERE status = 'active'");
    return (int)$stmt->fetchColumn();
}

function getTotalTeachers(): int {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM teachers");
    return (int)$stmt->fetchColumn();
}

function getTotalClasses(): int {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM classes");
    return (int)$stmt->fetchColumn();
}

function getTotalUsers(): int {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
    return (int)$stmt->fetchColumn();
}

function getCurrentTerm(): array {
    $db = getDB();
    $stmt = $db->query("SELECT t.*, s.session_name FROM terms t JOIN academic_sessions s ON t.session_id = s.id WHERE t.is_current = 1 LIMIT 1");
    return $stmt->fetch() ?: [];
}

function getTeacherId(): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    return (int)$stmt->fetchColumn();
}

function getStudentId(): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    return (int)$stmt->fetchColumn();
}

function getParentId(): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM parents WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    return (int)$stmt->fetchColumn();
}

function logActivity(int $userId, string $action, ?string $table = null, ?int $recordId = null, ?string $oldValue = null, ?string $newValue = null): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $table, $recordId, $oldValue, $newValue, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $_SERVER['HTTP_USER_AGENT'] ?? '']);
}

function uploadFile(array $file, string $subfolder = 'documents'): ?string {
    $targetDir = __DIR__ . '/../' . $subfolder . '/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) return null;
    if ($file['size'] > UPLOAD_MAX_SIZE) return null;
    $newName = uniqid() . '.' . $ext;
    $targetPath = $targetDir . $newName;
    return move_uploaded_file($file['tmp_name'], $targetPath) ? $subfolder . '/' . $newName : null;
}

function paginate(int $total, int $page, int $limit = PAGINATION_LIMIT): array {
    $totalPages = max(1, ceil($total / $limit));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset, 'total' => $total, 'totalPages' => $totalPages];
}

function sendEmail(string $to, string $subject, string $body): bool {
    $headers = "From: " . SCHOOL_NAME . " <" . SCHOOL_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SCHOOL_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

function sendSMS(string $phone, string $message): bool {
    $ch = curl_init('https://api.africastalking.com/version1/messaging');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'ApiKey: ' . (getenv('AT_API_KEY') ?: ''),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'username' => getenv('AT_USERNAME') ?: '',
            'to' => $phone,
            'message' => $message,
            'from' => 'PECULIAR',
        ]),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response !== false;
}

function getSchoolImages(int $limit = 0, string $datePrefix = ''): array {
    $pattern = __DIR__ . '/../assets/images/IMG_*.jpg';
    $images = glob($pattern);
    if ($datePrefix) {
        $images = array_filter($images, function($f) use ($datePrefix) {
            return strpos(basename($f), 'IMG_' . $datePrefix) === 0;
        });
    }
    sort($images);
    if ($limit > 0) $images = array_slice($images, 0, $limit);
    return array_map(function($img) {
        return BASE_URL . '/assets/images/' . basename($img);
    }, $images);
}

function getRandomHeroImage(): string {
    $images = getSchoolImages();
    return !empty($images) ? $images[array_rand($images)] : BASE_URL . '/assets/images/logo.jpg';
}

function getGalleryByDate(): array {
    $images = glob(__DIR__ . '/../assets/images/IMG_*.jpg');
    $groups = [];
    foreach ($images as $img) {
        $date = substr(basename($img), 4, 8);
        $groups[$date][] = BASE_URL . '/assets/images/' . basename($img);
    }
    krsort($groups);
    $labels = [
        '20251114' => ['Graduation & Awards', 'graduation'],
        '20251110' => ['Sports & Recreation', 'sports'],
        '20251108' => ['School Events', 'events'],
        '20251107' => ['Cultural Activities', 'events'],
        '20251106' => ['Academic Activities', 'academics'],
        '20251105' => ['School Facilities & Campus Life', 'facilities'],
    ];
    $result = [];
    foreach ($groups as $date => $imgs) {
        $info = $labels[$date] ?? ['School Photos', 'general'];
        $result[] = ['date' => $date, 'label' => $info[0], 'category' => $info[1], 'images' => $imgs];
    }
    return $result;
}
