<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Media\SecureUpload;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\RegistrationPage;

return static function (Request $request) {
    if ($request->method === 'GET') {
        return RegistrationPage::render('instructor');
    }

    $profileImage = null;
    $identityDocument = null;
    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        if (($request->body['agree_instructor_rules'] ?? '') !== '1') {
            throw new DomainException('You must agree to the instructor and content rules before applying.');
        }

        $profileImage = SecureUpload::store(
            is_array($_FILES['profile_photo'] ?? null) ? $_FILES['profile_photo'] : [],
            'private/instructor-profiles',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            3 * 1024 * 1024,
        );
        $identityDocument = SecureUpload::store(
            is_array($_FILES['identity_document'] ?? null) ? $_FILES['identity_document'] : [],
            'private/instructor-identity',
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'],
            6 * 1024 * 1024,
        );
        if ($profileImage === null || $identityDocument === null) {
            throw new DomainException('A personal photo and identity document are required.');
        }

        $payload = $request->body;
        unset($payload['_token']);
        $payload['profile_image'] = $profileImage;
        $payload['identity_document'] = $identityDocument;
        $result = (new ApiClient())->post('/api/v1/auth/register/instructor', $payload);

        return RegistrationPage::render('instructor', [], (string) ($result['message'] ?? 'Application submitted.'), true, 201);
    } catch (DomainException $exception) {
        SecureUpload::delete($profileImage);
        SecureUpload::delete($identityDocument);
        return RegistrationPage::render('instructor', $request->body, $exception->getMessage(), false, 422);
    }
};
