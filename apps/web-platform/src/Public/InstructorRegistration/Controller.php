<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\RegistrationPage;

return static function (Request $request) {
    if ($request->method === 'GET') {
        return RegistrationPage::render('instructor');
    }
    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $result = (new ApiClient())->post('/api/v1/auth/register/instructor', $request->body);
        return RegistrationPage::render('instructor', [], (string) ($result['message'] ?? 'Application submitted.'), true, 201);
    } catch (DomainException $exception) {
        return RegistrationPage::render('instructor', $request->body, $exception->getMessage(), false, 422);
    }
};
