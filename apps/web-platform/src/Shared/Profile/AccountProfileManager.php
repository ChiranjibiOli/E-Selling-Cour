<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Profile;

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Media\PrivateMedia;
use CourseHub\WebPlatform\Shared\Media\SecureUpload;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use DomainException;

final class AccountProfileManager
{
    /** @param callable(array,string,bool):Response $renderer */
    public static function handle(Request $request, string $role, callable $renderer): Response
    {
        if (!in_array($role, ['student', 'admin'], true)) {
            throw new DomainException('This profile manager supports Student and Administrator accounts only.');
        }

        $client = new ApiClient();
        if ($request->method === 'GET' && (string) ($request->query['photo'] ?? '') === '1') {
            try {
                $profile = $client->get('/api/v1/users/account-profile')['data'] ?? [];
                return PrivateMedia::response((string) ($profile['profile_image'] ?? ''), ['private/profile-photos']);
            } catch (DomainException) {
                return Response::html('', 404);
            }
        }

        $message = '';
        $success = true;
        $newProfileImage = null;

        try {
            if ($request->method === 'POST') {
                Csrf::assertValid((string) ($request->body['_token'] ?? ''));
                $action = strtolower(trim((string) ($request->body['action'] ?? '')));

                if ($action === 'change_photo') {
                    $file = is_array($_FILES['profile_photo'] ?? null) ? $_FILES['profile_photo'] : [];
                    $temporaryPath = (string) ($file['tmp_name'] ?? '');
                    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($error !== UPLOAD_ERR_OK || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                        throw new DomainException('Choose a profile photo to upload.');
                    }

                    $dimensions = @getimagesize($temporaryPath);
                    if (!is_array($dimensions)) {
                        throw new DomainException('The profile photo must be a valid image.');
                    }
                    $width = (int) ($dimensions[0] ?? 0);
                    $height = (int) ($dimensions[1] ?? 0);
                    $ratio = $height > 0 ? $width / $height : 0;
                    if ($width < 200 || $height < 200 || $ratio < 0.65 || $ratio > 1.35) {
                        throw new DomainException('Use a clear, mostly square profile photo of at least 200 × 200 pixels.');
                    }

                    $newProfileImage = SecureUpload::store(
                        $file,
                        'private/profile-photos',
                        ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                        3 * 1024 * 1024,
                    );
                    if ($newProfileImage === null) {
                        throw new DomainException('Choose a profile photo to upload.');
                    }
                    $result = $client->post('/api/v1/users/account-profile', [
                        'action' => 'change_photo',
                        'profile_image' => $newProfileImage,
                    ]);
                } elseif ($action === 'remove_photo') {
                    $result = $client->post('/api/v1/users/account-profile', ['action' => 'remove_photo']);
                } else {
                    throw new DomainException('Choose Change photo or Remove photo.');
                }

                SecureUpload::delete((string) ($result['old_profile_image'] ?? ''));
                $profile = is_array($result['data'] ?? null) ? $result['data'] : [];
                if ($profile !== []) {
                    AuthSession::synchronizeUser([
                        'id' => (int) ($profile['id'] ?? 0),
                        'name' => (string) ($profile['full_name'] ?? ''),
                        'email' => (string) ($profile['email'] ?? ''),
                        'role' => (string) ($profile['role'] ?? $role),
                        'profile_image' => (string) ($profile['profile_image'] ?? ''),
                    ]);
                }
                $message = (string) ($result['message'] ?? 'Profile photo updated.');
            }

            $profile = $client->get('/api/v1/users/account-profile')['data'] ?? [];
            if (is_array($profile) && $profile !== []) {
                AuthSession::synchronizeUser([
                    'id' => (int) ($profile['id'] ?? 0),
                    'name' => (string) ($profile['full_name'] ?? ''),
                    'email' => (string) ($profile['email'] ?? ''),
                    'role' => (string) ($profile['role'] ?? $role),
                    'profile_image' => (string) ($profile['profile_image'] ?? ''),
                ]);
            }
        } catch (DomainException $exception) {
            SecureUpload::delete($newProfileImage);
            $message = $exception->getMessage();
            $success = false;
            $profile = [];
            try {
                $profile = $client->get('/api/v1/users/account-profile')['data'] ?? [];
            } catch (DomainException) {
            }
        }

        return $renderer(is_array($profile) ? $profile : [], $message, $success);
    }
}
