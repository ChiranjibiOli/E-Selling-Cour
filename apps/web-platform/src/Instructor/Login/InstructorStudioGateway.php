<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Identity\IdentityClient;

final class InstructorStudioGateway
{
    public function open(StudioAccessRequest $request): array
    {
        return (new IdentityClient())->login(
            'instructor',
            $request->email,
            $request->password,
        );
    }
}
