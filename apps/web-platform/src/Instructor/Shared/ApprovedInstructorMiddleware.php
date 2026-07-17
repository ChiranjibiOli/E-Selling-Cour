<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Instructor\Shared;

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;

class ApprovedInstructorMiddleware extends InstructorAuthMiddleware
{
    public function check(Request $request): ?Response
    {
        $roleFailure = parent::check($request);
        if ($roleFailure !== null) {
            return $roleFailure;
        }

        if ((string) ($_SESSION['user']['status'] ?? '') !== 'active') {
            return Response::html('<h1>Instructor approval required</h1><p>Your instructor account is not active yet.</p>', 403);
        }

        return null;
    }
}
