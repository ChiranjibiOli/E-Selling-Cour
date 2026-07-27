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

        $profileFile = is_array($_FILES['profile_photo'] ?? null) ? $_FILES['profile_photo'] : [];
        $temporaryPhoto = (string) ($profileFile['tmp_name'] ?? '');
        $photoError = (int) ($profileFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($photoError !== UPLOAD_ERR_OK || $temporaryPhoto === '' || !is_uploaded_file($temporaryPhoto)) {
            throw new DomainException('A passport-size profile photo is required.');
        }
        $dimensions = @getimagesize($temporaryPhoto);
        if (!is_array($dimensions)) {
            throw new DomainException('The profile photo must be a valid image.');
        }
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $ratio = $height > 0 ? $width / $height : 0;
        if ($width < 300 || $height < 400 || $height <= $width || $ratio < 0.62 || $ratio > 0.9) {
            throw new DomainException('Upload a clear portrait passport-size photo at least 300 × 400 pixels.');
        }

        $profileImage = SecureUpload::store(
            $profileFile,
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
            throw new DomainException('A passport-size photo and identity document are required.');
        }

        $payload = $request->body;
        unset($payload['_token']);
        $payload['profile_image'] = $profileImage;
        $payload['identity_document'] = $identityDocument;
        $result = (new ApiClient())->post('/api/v1/auth/register/instructor', $payload);

        SecureUpload::delete((string) ($result['old_profile_image'] ?? ''));
        SecureUpload::delete((string) ($result['old_identity_document'] ?? ''));

        return RegistrationPage::render('instructor', [], (string) ($result['message'] ?? 'Application submitted.'), true, 201);
    } catch (DomainException $exception) {
        SecureUpload::delete($profileImage);
        SecureUpload::delete($identityDocument);
        return RegistrationPage::render('instructor', $request->body, $exception->getMessage(), false, 422);
    }
};
