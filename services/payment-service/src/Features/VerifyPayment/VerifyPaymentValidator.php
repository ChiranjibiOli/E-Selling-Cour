<?php

declare(strict_types=1);

namespace CourseHub\Payment\Features\VerifyPayment;

final class VerifyPaymentValidator
{
    public function validate(array $input): array
    {
        $paymentId = (int) ($input['payment_id'] ?? 0);
        $decision = (string) ($input['decision'] ?? '');
        if ($paymentId < 1 || !in_array($decision, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('A valid payment and decision are required.');
        }
        return ['payment_id' => $paymentId, 'decision' => $decision, 'note' => trim((string) ($input['note'] ?? ''))];
    }
}
