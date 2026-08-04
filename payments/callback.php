<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

$reference = $_GET['reference'] ?? '';
$feeId = (int)($_GET['fee_id'] ?? 0);

if (!$reference || !$feeId) {
    redirect('/student/fees.php?error=invalid_callback');
}

if (!PAYSTACK_SECRET_KEY) {
    redirect('/student/fees.php?error=gateway_not_configured');
}

$result = \App\Services\PaymentService::verifyPaystack($reference, PAYSTACK_SECRET_KEY);

if (!$result['verified']) {
    redirect('/student/fees.php?error=payment_failed');
}
$amountPaid = $result['amount'];
$db = getDB();

$fee = $db->prepare("SELECT f.*, s.user_id FROM fees f JOIN students s ON f.student_id = s.id WHERE f.id = ?");
$fee->execute([$feeId]);
$fee = $fee->fetch();

if (!$fee) {
    redirect('/student/fees.php?error=fee_not_found');
}

try {
    $inserted = \App\Services\PaymentService::record($db, $feeId, $amountPaid, $reference);
} catch (\Throwable $e) {
    redirect('/student/fees.php?error=processing_failed');
}

if (!$inserted) {
    redirect('/student/fees.php?success=already_verified');
}

logActivity($fee['user_id'], 'online_payment', 'payments', (int)$db->lastInsertId());
logger('payment')->info('Payment verified via Paystack', ['fee_id' => $feeId, 'student_user_id' => (int)$fee['user_id'], 'amount' => $amountPaid, 'reference' => $reference, 'receipt_no' => $receiptNo, 'new_status' => 'approved', 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
redirect('/student/fees.php?success=payment_successful');
