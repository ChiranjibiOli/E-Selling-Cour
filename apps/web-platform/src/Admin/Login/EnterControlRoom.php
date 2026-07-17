<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Config\Environment;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/AdminChallengeEnvelope.php';
require_once __DIR__ . '/AdminIdentityChannel.php';
require_once __DIR__ . '/ControlRoomScreen.php';

return static function (Request $request): Response {
    if (AuthSession::role() === 'admin') {
        return Response::redirect('/admin/dashboard');
    }

    $entryPath = Environment::adminLoginPath();
    if ($request->method === 'GET') {
        return ControlRoomScreen::render($entryPath);
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $challenge = AdminChallengeEnvelope::seal($request->body);
        $result = (new AdminIdentityChannel())->challenge($challenge);
        AuthSession::establish($result);

        return Response::redirect('/admin/dashboard');
    } catch (DomainException $exception) {
        return ControlRoomScreen::render(
            $entryPath,
            'Control-room authentication failed.',
            (string) ($request->body['control_identity'] ?? ''),
            422,
        );
    }
};
