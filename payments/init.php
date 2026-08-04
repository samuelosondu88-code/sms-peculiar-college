<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$feeId = (int)($_POST['fee_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

$fee = $db->prepare("
    SELECT f.*, s.user_id AS student_user_id, u.email, u.first_name, u.last_name
    FROM fees f
    JOIN students s ON f.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE f.id = ? AND f.balance > 0
");
$fee->execute([$feeId]);
$fee = $fee->fetch();

if (!$fee) {
    http_response_code(404);
    echo json_encode(['error' => 'Fee not found or already paid.']);
    exit;
}

if ((int)$fee['student_user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

if (!PAYSTACK_SECRET_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'Payment gateway not configured.']);
    exit;
}

$amountKobo = (int)($fee['balance'] * 100);
$reference = 'PIC-' . $feeId . '-' . time() . '-' . bin2hex(random_bytes(4));
$callbackUrl = BASE_URL . '/payments/callback.php?reference=' . $reference . '&fee_id=' . $feeId;

$ch = curl_init('https://api.paystack.co/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'email' => $fee['email'],
        'amount' => $amountKobo,
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => json_encode([
            'fee_id' => $feeId,
            'student_user_id' => $userId,
        ]),
    ]),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode !== 200 || !$data || !$data['status']) {
    http_response_code(502);
    echo json_encode(['error' => 'Payment gateway error. Try again later.']);
    exit;
}

echo json_encode([
    'authorization_url' => $data['data']['authorization_url'],
    'reference' => $reference,
]);
