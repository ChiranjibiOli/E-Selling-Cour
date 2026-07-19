<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    $token = trim((string) ($request->query['token'] ?? $request->body['token'] ?? ''));
    if ($request->method === 'GET') {
        return ResetPasswordPage::render(['token' => $token]);
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $result = (new ApiClient())->post('/api/v1/auth/reset-password', [
            'token' => $token,
            'password' => (string) ($request->body['password'] ?? ''),
            'password_confirmation' => (string) ($request->body['password_confirmation'] ?? ''),
        ]);
        return ResetPasswordPage::render(['token' => ''], (string) ($result['message'] ?? 'Password changed.'), true);
    } catch (DomainException $exception) {
        return ResetPasswordPage::render(['token' => $token], $exception->getMessage(), false);
    }
};
