<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Security\Csrf;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    $purpose = (string) ($request->method === 'GET' ? ($request->query['purpose'] ?? 'registration') : ($request->body['purpose'] ?? 'registration'));
    if (!in_array($purpose, ['registration', 'password_reset'], true)) {
        $purpose = 'registration';
    }
    $email = strtolower(trim((string) ($request->method === 'GET' ? ($request->query['email'] ?? '') : ($request->body['email'] ?? ''))));
    $developmentCode = (string) ($request->method === 'GET' ? ($request->query['development_code'] ?? '') : '');

    if ($request->method === 'GET') {
        return VerifyOtpPage::render($purpose, $email, [], '', false, $developmentCode);
    }

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $client = new ApiClient();
        if ($purpose === 'registration' && (string) ($request->body['operation'] ?? '') === 'resend') {
            $result = $client->post('/api/v1/auth/resend-student-verification', ['email' => $email]);
            return VerifyOtpPage::render(
                $purpose,
                $email,
                [],
                (string) ($result['message'] ?? 'A new verification code was created.'),
                false,
                (string) ($result['development_code'] ?? ''),
                202,
            );
        }
        if ($purpose === 'password_reset') {
            $result = $client->post('/api/v1/auth/reset-password-code', [
                'email' => $email,
                'code' => (string) ($request->body['code'] ?? ''),
                'password' => (string) ($request->body['password'] ?? ''),
                'password_confirmation' => (string) ($request->body['password_confirmation'] ?? ''),
            ]);
        } else {
            $result = $client->post('/api/v1/auth/verify-student-email', [
                'email' => $email,
                'code' => (string) ($request->body['code'] ?? ''),
            ]);
        }
        return VerifyOtpPage::render($purpose, $email, [], (string) ($result['message'] ?? 'Verification completed.'), true);
    } catch (DomainException $exception) {
        return VerifyOtpPage::render($purpose, $email, $request->body, $exception->getMessage(), false, '', 422);
    }
};
