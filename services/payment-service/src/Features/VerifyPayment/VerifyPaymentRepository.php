<?php

declare(strict_types=1);

namespace CourseHub\Payment\Features\VerifyPayment;

use PDO;

final class VerifyPaymentRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function recordDecision(array $decision, int $adminId): array
    {
        $status = $decision['decision'] === 'approve' ? 'paid' : 'rejected';
        $statement = $this->database->prepare(
            'UPDATE payments SET payment_status = :payment_status, verified_by = :verified_by, verified_at = NOW() '
            . 'WHERE id = :id AND payment_status = :expected'
        );
        $statement->execute([
            'payment_status' => $status,
            'verified_by' => $adminId,
            'id' => $decision['payment_id'],
            'expected' => 'pending',
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Payment decision was already processed or the payment does not exist.');
        }
        return ['payment_id' => $decision['payment_id'], 'payment_status' => $status];
    }
}
