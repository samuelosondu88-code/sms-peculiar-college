<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Data access for the payments + fees tables.
 */
final class PaymentRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function exists(string $transactionRef, int $feeId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM payments WHERE transaction_ref = ? AND fee_id = ?");
        $stmt->execute([$transactionRef, $feeId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function findFee(int $feeId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM fees WHERE id = ? LIMIT 1");
        $stmt->execute([$feeId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function insert(array $payment): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO payments (fee_id, amount_paid, payment_method, transaction_ref, receipt_no, payment_date, status)
             VALUES (?, ?, ?, ?, ?, CURDATE(), ?)"
        );
        $stmt->execute([
            $payment['fee_id'],
            $payment['amount_paid'],
            $payment['payment_method'] ?? 'card',
            $payment['transaction_ref'],
            $payment['receipt_no'],
            $payment['status'] ?? 'approved',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateFeeBalance(int $feeId, float $paid, float $balance, string $status): void
    {
        $this->db->prepare("UPDATE fees SET paid_amount = ?, balance = ?, status = ? WHERE id = ?")
            ->execute([$paid, $balance, $status, $feeId]);
    }
}