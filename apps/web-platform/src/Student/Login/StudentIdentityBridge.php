<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Identity\IdentityClient;

final class StudentIdentityBridge
{
    public function authenticate(StudentCredentialPacket $credentials): array
    {
        return (new IdentityClient())->login(
            'student',
            $credentials->email,
            $credentials->password,
        );
    }
}
