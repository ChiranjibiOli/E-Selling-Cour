<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/StudioAccessRequest.php';
require_once __DIR__ . '/InstructorStudioGateway.php';
require_once __DIR__ . '/StudioAccessScreen.php';

return static function (Request $request): Response {
    if (AuthSession::role() === 'instructor') {
        return Response::redirect('/instructor/dashboard');
    }

    if ($request->method === 'GET') {
        return StudioAccessScreen::render();
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $credentials = StudioAccessRequest::capture($request->body);
        $result = (new InstructorStudioGateway())->open($credentials);
        AuthSession::establish($result);

        return Response::redirect('/instructor/dashboard');
    } catch (DomainException $exception) {
        return StudioAccessScreen::render(
            $exception->getMessage(),
            (string) ($request->body['studio_email'] ?? ''),
            422,
        );
    }
};
