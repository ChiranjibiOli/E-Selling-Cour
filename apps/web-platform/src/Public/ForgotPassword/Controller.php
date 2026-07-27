<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    if ($request->method === 'GET') {
        return ForgotPasswordPage::render();
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $email = strtolower(trim((string) ($request->body['email'] ?? '')));
        $result = (new ApiClient())->post('/api/v1/auth/forgot-password', ['email' => $email]);
        $query = http_build_query([
            'purpose' => 'password_reset',
            'email' => $email,
            'development_code' => (string) ($result['development_code'] ?? ''),
        ]);
        return Response::redirect('/verify-otp?' . $query);
    } catch (DomainException $exception) {
        return ForgotPasswordPage::render($request->body, $exception->getMessage(), false);
    }
};
