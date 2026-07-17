<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\InstructorRegistration;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class InstructorRegistrationController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new InstructorRegistrationPage())->render((new InstructorRegistrationService())->load($request));
    }
}
