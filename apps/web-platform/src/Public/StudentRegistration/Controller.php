<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\RegistrationPage;

return static function (Request $request) {
    if ($request->method === 'GET') {
        return RegistrationPage::render('student');
    }
    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $result = (new ApiClient())->post('/api/v1/auth/register/student', $request->body);
        return RegistrationPage::render('student', [], (string) ($result['message'] ?? 'Account created.'), true, 201);
    } catch (DomainException $exception) {
        return RegistrationPage::render('student', $request->body, $exception->getMessage(), false, 422);
    }
};
