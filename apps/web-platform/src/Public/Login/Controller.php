<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use CourseHub\WebPlatform\Shared\Http\Response;

return static function (Request $request): Response {
    $dashboard = match (AuthSession::role()) {
        'student' => '/student/dashboard',
        'instructor' => '/instructor/dashboard',
        'admin' => '/admin/dashboard',
        default => '',
    };

    if ($dashboard !== '') {
        return Response::redirect($dashboard);
    }

    $sessionEnded = strtolower(trim((string) ($request->query['session'] ?? ''))) === 'ended';
    $destination = '/learn/sign-in' . ($sessionEnded ? '?session=ended' : '');

    return Response::redirect($destination);
};
