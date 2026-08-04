<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Payment domain logic: verify a gateway response and record the payment,
 * recomputing the fee balance atomically.
 */
final class PaymentService
{
    /**
     * Verify a transaction reference against the Paystack API.
     *
     * @return array{verified: bool, amount: float, reference: string, raw: array}
     */
    public static function verifyPaystack(string $reference, string $secretKey): array
    {
        if ($reference === '' || $secretKey === '') {
            return ['verified' => false, 'amount' => 0.0, 'reference' => $reference, 'raw' => []];
        }

        $ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        $verified = $httpCode === 200
            && is_array($data)
            && (bool) ($data['status'] ?? false)
            && ($data['data']['status'] ?? '') === 'success';

        return [
            'verified' => $verified,
            'amount' => $verified ? (float) ($data['data']['amount'] ?? 0) / 100 : 0.0,
            'reference' => $reference,
            'raw' => is_array($data) ? $data : [],
        ];
    }

    /**
     * Idempotently record a successful payment and update the fee balance.
     *
     * @return bool True when a new payment record was written.
     */
    public static function record(PDO $db, int $feeId, float $amount, string $transactionRef): bool
    {
        $repo = new \App\Repositories\PaymentRepository($db);

        if ($repo->exists($transactionRef, $feeId)) {
            return false; // already processed
        }

        $fee = $repo->findFee($feeId);
        if (!$fee) {
            throw new \RuntimeException('Fee record not found.');
        }

        $receiptNo = 'RCP-' . strtoupper(bin2hex(random_bytes(5)));

        $db->beginTransaction();
        try {
            $repo->insert([
                'fee_id' => $feeId,
                'amount_paid' => $amount,
                'transaction_ref' => $transactionRef,
                'receipt_no' => $receiptNo,
            ]);

            $newPaid = (float) $fee['paid_amount'] + $amount;
            $newBalance = (float) $fee['total_amount'] - $newPaid;
            $newStatus = $newBalance <= 0 ? 'paid' : 'partial';
            $repo->updateFeeBalance($feeId, $newPaid, max(0.0, $newBalance), $newStatus);

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            logger('payment')->error('Payment record failed', ['error' => $e->getMessage(), 'fee_id' => $feeId]);
            throw $e;
        }
    }
}