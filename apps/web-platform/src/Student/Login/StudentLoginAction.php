<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/StudentCredentialPacket.php';
require_once __DIR__ . '/StudentIdentityBridge.php';
require_once __DIR__ . '/StudentLoginScreen.php';

return static function (Request $request): Response {
    if (AuthSession::role() === 'student') {
        return Response::redirect('/student/dashboard');
    }

    if ($request->method === 'GET') {
        return StudentLoginScreen::render();
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $credentials = StudentCredentialPacket::from($request->body);
        $result = (new StudentIdentityBridge())->authenticate($credentials);
        AuthSession::establish($result);

        return Response::redirect('/student/dashboard');
    } catch (DomainException $exception) {
        return StudentLoginScreen::render(
            $exception->getMessage(),
            (string) ($request->body['email'] ?? ''),
            422,
        );
    }
};
