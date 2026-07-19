<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    if ($request->method === 'GET') {
        return ForgotPasswordPage::render();
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $email = trim((string) ($request->body['email'] ?? ''));
        $result = (new ApiClient())->post('/api/v1/auth/forgot-password', ['email' => $email]);
        return ForgotPasswordPage::render(
            ['email' => $email],
            (string) ($result['message'] ?? 'Password-reset instructions were created.'),
            true,
            (string) ($result['development_reset_url'] ?? ''),
        );
    } catch (DomainException $exception) {
        return ForgotPasswordPage::render($request->body, $exception->getMessage(), false);
    }
};
