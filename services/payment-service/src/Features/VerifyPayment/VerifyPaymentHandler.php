<?php

declare(strict_types=1);

namespace CourseHub\Payment\Features\VerifyPayment;

final class VerifyPaymentHandler
{
    public function __construct(private readonly VerifyPaymentRepository $repository)
    {
    }

    public function handle(array $decision, int $adminId): array
    {
        return $this->repository->recordDecision($decision, $adminId);
    }
}
