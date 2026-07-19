<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

return static function (Request $request) {
    Csrf::assertValid((string) ($request->body['_token'] ?? ''));
    $role = AuthSession::role();
    try {
        (new ApiClient())->post('/api/v1/auth/logout', []);
    } catch (DomainException $exception) {
        error_log('Remote logout failed; clearing local session: ' . $exception->getMessage());
    }
    AuthSession::clear();
    $entrance = match ($role) {
        'instructor' => '/teach/studio-access',
        'student' => '/learn/sign-in',
        default => '/login',
    };
    return Response::redirect($entrance);
};
