<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Media\PrivateMedia;
use CourseHub\WebPlatform\Shared\Media\SecureUpload;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();

    try {
        if ($request->method === 'GET' && (string) ($request->query['photo'] ?? '') === '1') {
            $profile = $client->get('/api/v1/users/instructor-profile')['data'] ?? [];
            return PrivateMedia::response((string) ($profile['profile_image'] ?? ''), ['private/instructor-profiles']);
        }
    } catch (DomainException $exception) {
        return InstructorProfilePage::render([], $exception->getMessage(), false);
    }

    $message = '';
    $success = true;
    $newProfileImage = null;

    try {
        if ($request->method === 'POST') {
            Csrf::assertValid((string) ($request->body['_token'] ?? ''));
            $action = FormInput::enum($request->body, 'action', 'Profile action', ['save_profile', 'remove_photo'], 'save_profile');

            if ($action === 'remove_photo') {
                $result = $client->post('/api/v1/users/instructor-profile', ['action' => 'remove_photo']);
                SecureUpload::delete((string) ($result['old_profile_image'] ?? ''));
                $message = (string) ($result['message'] ?? 'Instructor profile photo removed.');
            } else {
                $payload = [
                    'action' => 'save_profile',
                    'full_name' => FormInput::text(
                        $request->body,
                        'full_name',
                        'Full name',
                        2,
                        100,
                        true,
                        "/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'-]*$/u",
                    ),
                    'phone' => FormInput::text(
                        $request->body,
                        'phone',
                        'Phone number',
                        7,
                        20,
                        false,
                        '/^[0-9+() -]+$/',
                    ),
                    'professional_headline' => FormInput::text($request->body, 'professional_headline', 'Professional headline', 5, 160),
                    'bio' => FormInput::multiline($request->body, 'bio', 'Professional biography', 40, 3000),
                    'expertise' => FormInput::multiline($request->body, 'expertise', 'Areas of expertise', 10, 1000),
                    'teaching_experience' => FormInput::multiline($request->body, 'teaching_experience', 'Teaching experience', 20, 2000),
                    'course_subjects' => FormInput::multiline($request->body, 'course_subjects', 'Course subjects', 3, 1000),
                    'social_profile_url' => FormInput::httpsUrl($request->body, 'social_profile_url', 'Professional profile URL'),
                ];

                $profileFile = is_array($_FILES['profile_photo'] ?? null) ? $_FILES['profile_photo'] : [];
                $photoError = (int) ($profileFile['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($photoError !== UPLOAD_ERR_NO_FILE) {
                    $temporaryPhoto = (string) ($profileFile['tmp_name'] ?? '');
                    if ($photoError !== UPLOAD_ERR_OK || $temporaryPhoto === '' || !is_uploaded_file($temporaryPhoto)) {
                        throw new DomainException('The new profile photo could not be received.');
                    }
                    $dimensions = @getimagesize($temporaryPhoto);
                    if (!is_array($dimensions)) {
                        throw new DomainException('The new profile photo must be a valid image.');
                    }
                    $width = (int) ($dimensions[0] ?? 0);
                    $height = (int) ($dimensions[1] ?? 0);
                    $ratio = $height > 0 ? $width / $height : 0;
                    if ($width < 300 || $height < 400 || $height <= $width || $ratio < 0.62 || $ratio > 0.9) {
                        throw new DomainException('Upload a clear portrait passport-size photo at least 300 × 400 pixels.');
                    }
                    $newProfileImage = SecureUpload::store(
                        $profileFile,
                        'private/instructor-profiles',
                        ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                        3 * 1024 * 1024,
                    );
                    if ($newProfileImage === null) {
                        throw new DomainException('Choose a valid Instructor profile photo.');
                    }
                    $payload['profile_image'] = $newProfileImage;
                }

                $result = $client->post('/api/v1/users/instructor-profile', $payload);
                if ($newProfileImage !== null) {
                    SecureUpload::delete((string) ($result['old_profile_image'] ?? ''));
                }
                $message = (string) ($result['message'] ?? 'Instructor profile updated.');
            }
        }

        $profile = $client->get('/api/v1/users/instructor-profile')['data'] ?? [];
        if (is_array($profile) && $profile !== []) {
            $current = AuthSession::user();
            AuthSession::synchronizeUser([
                'id' => (int) ($current['id'] ?? 0),
                'name' => (string) ($profile['full_name'] ?? $current['name'] ?? ''),
                'email' => (string) ($profile['email'] ?? $current['email'] ?? ''),
                'role' => 'instructor',
                'profile_image' => (string) ($profile['profile_image'] ?? ''),
            ]);
        }
    } catch (DomainException $exception) {
        SecureUpload::delete($newProfileImage);
        $message = $exception->getMessage();
        $success = false;
        $profile = [];
        try {
            $profile = $client->get('/api/v1/users/instructor-profile')['data'] ?? [];
        } catch (DomainException) {
        }
    }

    return InstructorProfilePage::render(is_array($profile) ? $profile : [], $message, $success);
};
