<?php

declare(strict_types=1);

namespace CourseHub\Payment\Features\VerifyPayment;

final class VerifyPaymentController
{
    public function __construct(private readonly VerifyPaymentHandler $handler)
    {
    }

    public function verify(array $input, array $actor): array
    {
        (new VerifyPaymentMiddleware())->assertAllowed($actor);
        $validated = (new VerifyPaymentValidator())->validate($input);
        return $this->handler->handle($validated, (int) $actor['id']);
    }
}
