<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Media\SecureUpload;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $categories = [];
    $instructorName = (string) (AuthSession::user()['name'] ?? 'CourseHub instructor');

    try {
        $categories = $client->get('/api/v1/categories?limit=50')['data'] ?? [];
    } catch (DomainException $exception) {
        return CreateCoursePage::render([], [], $exception->getMessage(), false, $instructorName);
    }

    if ($request->method === 'GET') {
        return CreateCoursePage::render($categories, [], '', false, $instructorName);
    }

    $values = $request->body;
    $storedThumbnail = null;

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $intent = FormInput::enum($request->body, 'intent', 'Course action', ['draft', 'curriculum'], 'draft');
        $price = FormInput::decimal($request->body, 'price', 'Standard price', 0, 10_000_000, true, 2);
        $discount = FormInput::decimal($request->body, 'discount_price', 'Discount price', 0, 10_000_000, false, 2);
        if ($discount !== null && $price !== null && $discount >= $price) {
            throw new DomainException('Discount price must be lower than the standard price.');
        }
        $durationHours = FormInput::decimal($request->body, 'duration_hours', 'Estimated duration', 0.25, 10_000, false, 2);

        $payload = [
            'title' => FormInput::text($request->body, 'title', 'Course title', 3, 180),
            'subtitle' => FormInput::text($request->body, 'subtitle', 'Course subtitle', 0, 240, false),
            'short_description' => FormInput::multiline($request->body, 'short_description', 'Short description', 20, 500),
            'full_description' => FormInput::multiline($request->body, 'full_description', 'Full course description', 50, 50_000),
            'learning_outcomes' => FormInput::listText($request->body, 'learning_outcomes', 'Learning outcomes'),
            'requirements' => FormInput::listText($request->body, 'requirements', 'Course requirements'),
            'target_audience' => FormInput::listText($request->body, 'target_audience', 'Target audience'),
            'tags' => FormInput::text($request->body, 'tags', 'Tags', 0, 500, false),
            'category_id' => FormInput::integer($request->body, 'category_id', 'Category', 1, PHP_INT_MAX),
            'level' => FormInput::enum($request->body, 'level', 'Course level', ['beginner', 'intermediate', 'advanced'], 'beginner'),
            'price' => number_format((float) $price, 2, '.', ''),
            'discount_price' => $discount !== null ? number_format($discount, 2, '.', '') : '',
            'language' => FormInput::text(
                $request->body,
                'language',
                'Language',
                2,
                60,
                true,
                "/^[\\p{L}\\p{M}\\p{N} .,'()&\\/-]+$/u",
            ),
            'duration' => $durationHours !== null
                ? rtrim(rtrim(number_format($durationHours, 2, '.', ''), '0'), '.') . ' hours'
                : '',
            'intro_video_url' => FormInput::httpsUrl($request->body, 'intro_video_url', 'Introduction video URL'),
            'thumbnail' => '',
        ];

        $thumbnail = is_array($_FILES['thumbnail'] ?? null) ? $_FILES['thumbnail'] : [];
        $thumbnailError = (int) ($thumbnail['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($thumbnailError !== UPLOAD_ERR_NO_FILE) {
            $temporaryPath = (string) ($thumbnail['tmp_name'] ?? '');
            if ($thumbnailError !== UPLOAD_ERR_OK || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                throw new DomainException('The course thumbnail could not be received.');
            }
            $dimensions = @getimagesize($temporaryPath);
            if (!is_array($dimensions)) {
                throw new DomainException('The course thumbnail must be a valid image.');
            }
            $width = (int) ($dimensions[0] ?? 0);
            $height = (int) ($dimensions[1] ?? 0);
            $ratio = $height > 0 ? $width / $height : 0;
            if ($width < 640 || $height < 360 || $ratio < 1.3 || $ratio > 2.0) {
                throw new DomainException('Use a landscape course thumbnail of at least 640 × 360 pixels.');
            }
            $storedThumbnail = SecureUpload::store(
                $thumbnail,
                'media/course-thumbnails',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                4 * 1024 * 1024,
            );
            if ($storedThumbnail === null) {
                throw new DomainException('Choose a course thumbnail to upload.');
            }
            $payload['thumbnail'] = $storedThumbnail;
        }

        $created = $client->post('/api/v1/courses', $payload);
        $courseId = (int) ($created['id'] ?? 0);
        if ($courseId < 1) {
            throw new DomainException('The catalog service did not return the new course identifier.');
        }
        if ($intent === 'curriculum') {
            return Response::redirect('/instructor/lessons?course=' . $courseId);
        }

        return CreateCoursePage::render(
            $categories,
            [],
            (string) ($created['message'] ?? 'Course saved as a private draft.'),
            true,
            $instructorName,
        );
    } catch (DomainException $exception) {
        SecureUpload::delete($storedThumbnail);
        return CreateCoursePage::render($categories, $values, $exception->getMessage(), false, $instructorName);
    }
};
