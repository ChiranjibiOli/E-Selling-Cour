<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Identity\IdentityClient;

final class AdminIdentityChannel
{
    public function challenge(AdminChallengeEnvelope $envelope): array
    {
        return (new IdentityClient())->login(
            'admin',
            $envelope->identity,
            $envelope->secret,
            ['admin_access_code' => $envelope->entryCode],
        );
    }
}
