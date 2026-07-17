<?php

declare(strict_types=1);

namespace CourseHub\Payment\Features\VerifyPayment;

final class VerifyPaymentMiddleware
{
    public function assertAllowed(array $actor): void
    {
        if (($actor['role'] ?? null) !== 'admin') {
            throw new \RuntimeException('Administrator permission is required.');
        }
    }
}
