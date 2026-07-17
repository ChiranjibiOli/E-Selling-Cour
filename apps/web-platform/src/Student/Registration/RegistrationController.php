<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Student\Registration;

use CourseHub\WebPlatform\Shared\Contracts\RoomController;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

final class RegistrationController implements RoomController
{
    public function handle(Request $request): Response
    {
        return (new RegistrationPage())->render((new RegistrationService())->load($request));
    }
}
