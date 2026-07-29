<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\RegistrationPage;

return static function (Request $request) {
    if ($request->method === 'GET') {
        return RegistrationPage::render('student');
    }
    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));

        $fullName = preg_replace('/\s+/u', ' ', trim((string) ($request->body['full_name'] ?? ''))) ?? '';
        $phone = trim((string) ($request->body['phone'] ?? ''));
        if (preg_match('/^\p{L}+(?: \p{L}+)*$/u', $fullName) !== 1 || mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            throw new DomainException('Full name can contain letters and spaces only.');
        }
        if (preg_match('/^[0-9]{7,20}$/', $phone) !== 1) {
            throw new DomainException('Phone number can contain numbers only and must be 7 to 20 digits.');
        }

        $request->body['full_name'] = $fullName;
        $request->body['phone'] = $phone;
        $email = strtolower(trim((string) ($request->body['email'] ?? '')));
        $result = (new ApiClient())->post('/api/v1/auth/register/student', $request->body);
        $query = http_build_query([
            'purpose' => 'registration',
            'email' => $email,
            'development_code' => (string) ($result['development_code'] ?? ''),
        ]);
        return Response::redirect('/verify-otp?' . $query);
    } catch (DomainException $exception) {
        return RegistrationPage::render('student', $request->body, $exception->getMessage(), false, 422);
    }
};
