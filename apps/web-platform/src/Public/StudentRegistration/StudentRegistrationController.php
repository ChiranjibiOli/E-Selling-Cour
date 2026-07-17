<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Public\StudentRegistration;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class StudentRegistrationController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new StudentRegistrationPage())->render((new StudentRegistrationService())->load($request));
    }
}
