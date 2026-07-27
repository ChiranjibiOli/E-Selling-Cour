<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Media\SecureUpload;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Security\FormInput;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    $client = new ApiClient();
    $instructorName = (string) (AuthSession::user()['name'] ?? 'CourseHub instructor');
    $courseId = filter_var($request->query['course'] ?? $request->body['course_id'] ?? 0, FILTER_VALIDATE_INT);
    $courseId = $courseId !== false && $courseId > 0 ? (int) $courseId : 0;
    $categories = [];
    $authoring = [];
    $newUploads = [];

    try {
        $categories = $client->get('/api/v1/categories?limit=50')['data'] ?? [];
        if ($courseId > 0) {
            $authoring = $client->get('/api/v1/courses/' . $courseId . '/authoring')['data'] ?? [];
        }
    } catch (DomainException $exception) {
        return CreateCoursePage::render([], [], $exception->getMessage(), false, $instructorName);
    }

    if ($request->method === 'GET') {
        return CreateCoursePage::render($categories, is_array($authoring) ? $authoring : [], '', true, $instructorName);
    }

    $courseValues = $request->body;
    $sections = [];

    try {
        Csrf::assertValid((string) ($request->body['_token'] ?? ''));
        $action = FormInput::enum($request->body, 'action', 'Course action', ['draft', 'submit'], 'draft');
        $price = FormInput::decimal($request->body, 'price', 'Standard price', 0, 10_000_000, true, 2);
        $discount = FormInput::decimal($request->body, 'discount_price', 'Discount price', 0, 10_000_000, false, 2);
        if ($discount !== null && $price !== null && $discount >= $price) {
            throw new DomainException('Discount price must be lower than the standard price.');
        }
        $durationHours = FormInput::decimal($request->body, 'duration_hours', 'Estimated duration', 0.25, 10_000, false, 2);
        $existingThumbnail = FormInput::text($request->body, 'existing_thumbnail', 'Existing thumbnail', 0, 255, false);
        if ($existingThumbnail !== '' && preg_match('#^media/course-thumbnails/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $existingThumbnail) !== 1) {
            throw new DomainException('The existing course thumbnail reference is invalid.');
        }

        $course = [
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
            'thumbnail' => $existingThumbnail,
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
            $stored = SecureUpload::store(
                $thumbnail,
                'media/course-thumbnails',
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                4 * 1024 * 1024,
            );
            if ($stored === null) {
                throw new DomainException('Choose a valid course thumbnail.');
            }
            $course['thumbnail'] = $stored;
            $newUploads[] = $stored;
        }

        $curriculumRaw = (string) ($request->body['curriculum_json'] ?? '[]');
        try {
            $decodedSections = json_decode($curriculumRaw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DomainException('The section and lesson data is unreadable. Refresh the page and try again.', 0, $exception);
        }
        if (!is_array($decodedSections) || count($decodedSections) > 100) {
            throw new DomainException('The course curriculum must contain no more than 100 sections.');
        }

        $lessonTotal = 0;
        foreach (array_values($decodedSections) as $sectionIndex => $sectionInput) {
            if (!is_array($sectionInput)) {
                throw new DomainException('Section ' . ($sectionIndex + 1) . ' is invalid.');
            }
            $sectionTitle = FormInput::text($sectionInput, 'title', 'Section ' . ($sectionIndex + 1) . ' title', 2, 180);
            $lessonInputs = is_array($sectionInput['lessons'] ?? null) ? array_values($sectionInput['lessons']) : [];
            if (count($lessonInputs) > 100) {
                throw new DomainException('Section ' . ($sectionIndex + 1) . ' may contain at most 100 lessons.');
            }
            $lessons = [];
            foreach ($lessonInputs as $lessonIndex => $lessonInput) {
                $lessonTotal++;
                if ($lessonTotal > 500 || !is_array($lessonInput)) {
                    throw new DomainException('A course may contain at most 500 valid lessons.');
                }
                $label = 'Section ' . ($sectionIndex + 1) . ', lesson ' . ($lessonIndex + 1);
                $type = FormInput::enum($lessonInput, 'content_type', $label . ' content type', ['text', 'word', 'video', 'pdf', 'audio', 'image', 'link'], 'text');
                $duration = FormInput::integer($lessonInput, 'duration_minutes', $label . ' duration', 0, 10_000);
                $contentText = FormInput::multiline($lessonInput, 'content_text', $label . ' content', 0, 200_000, false);
                $contentUrl = FormInput::text($lessonInput, 'content_url', $label . ' existing resource', 0, 500, false);
                $contentName = FormInput::text($lessonInput, 'content_name', $label . ' resource name', 0, 255, false);
                $fileKey = FormInput::text($lessonInput, 'file_key', $label . ' upload key', 0, 100, false, '/^lesson_file_[0-9]+_[0-9]+$/');

                if ($type === 'link') {
                    $contentUrl = FormInput::httpsUrl($lessonInput, 'content_url', $label . ' resource URL', 500, $action === 'submit');
                    $contentText = '';
                } elseif (in_array($type, ['text', 'word'], true)) {
                    $contentUrl = '';
                    $contentName = '';
                    if ($action === 'submit' && trim($contentText) === '') {
                        throw new DomainException($label . ' needs written lesson content.');
                    }
                } else {
                    $file = $fileKey !== '' && is_array($_FILES[$fileKey] ?? null) ? $_FILES[$fileKey] : [];
                    $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($fileError !== UPLOAD_ERR_NO_FILE) {
                        if ($fileError !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                            throw new DomainException($label . ' file could not be received.');
                        }
                        $allowed = match ($type) {
                            'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
                            'pdf' => ['application/pdf' => 'pdf'],
                            'audio' => ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a'],
                            'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                            default => [],
                        };
                        $maximum = match ($type) {
                            'video' => 512 * 1024 * 1024,
                            'audio' => 100 * 1024 * 1024,
                            'pdf' => 25 * 1024 * 1024,
                            'image' => 10 * 1024 * 1024,
                            default => 0,
                        };
                        if ($type === 'image') {
                            $dimensions = @getimagesize((string) ($file['tmp_name'] ?? ''));
                            if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 200 || (int) ($dimensions[1] ?? 0) < 200) {
                                throw new DomainException($label . ' image must be at least 200 × 200 pixels.');
                            }
                        }
                        $stored = SecureUpload::store($file, 'private/course-content', $allowed, $maximum);
                        if ($stored === null) {
                            throw new DomainException($label . ' needs a valid ' . $type . ' file.');
                        }
                        $contentUrl = $stored;
                        $contentName = FormInput::text(
                            ['name' => basename((string) ($file['name'] ?? ucfirst($type) . ' resource'))],
                            'name',
                            $label . ' file name',
                            1,
                            255,
                        );
                        $newUploads[] = $stored;
                    }
                    if ($action === 'submit' && $contentUrl === '') {
                        throw new DomainException($label . ' requires the selected ' . $type . ' file.');
                    }
                    $contentText = '';
                }

                $lessons[] = [
                    'title' => FormInput::text($lessonInput, 'title', $label . ' title', 2, 180),
                    'content_type' => $type,
                    'content_url' => $contentUrl,
                    'content_name' => $contentName,
                    'content_text' => $contentText,
                    'duration_minutes' => $duration,
                    'is_preview' => filter_var($lessonInput['is_preview'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }
            $sections[] = ['title' => $sectionTitle, 'lessons' => $lessons];
        }

        $payload = ['action' => $action, 'course' => $course, 'sections' => $sections];
        $result = $courseId > 0
            ? $client->post('/api/v1/courses/' . $courseId . '/authoring', $payload)
            : $client->post('/api/v1/courses/authoring', $payload);
        $courseId = (int) ($result['id'] ?? $courseId);
        if ($courseId < 1) {
            throw new DomainException('The course service did not return the course identifier.');
        }
        foreach ((array) ($result['retired_files'] ?? []) as $retired) {
            SecureUpload::delete(is_string($retired) ? $retired : null);
        }
        $newUploads = [];
        $authoring = $client->get('/api/v1/courses/' . $courseId . '/authoring')['data'] ?? [];
        return CreateCoursePage::render(
            $categories,
            is_array($authoring) ? $authoring : [],
            (string) ($result['message'] ?? 'Course saved.'),
            true,
            $instructorName,
        );
    } catch (DomainException $exception) {
        foreach ($newUploads as $uploaded) {
            SecureUpload::delete($uploaded);
        }
        $fallbackCourse = [
            'title' => (string) ($courseValues['title'] ?? ''),
            'subtitle' => (string) ($courseValues['subtitle'] ?? ''),
            'short_description' => (string) ($courseValues['short_description'] ?? ''),
            'full_description' => (string) ($courseValues['full_description'] ?? ''),
            'learning_outcomes' => (string) ($courseValues['learning_outcomes'] ?? ''),
            'requirements' => (string) ($courseValues['requirements'] ?? ''),
            'target_audience' => (string) ($courseValues['target_audience'] ?? ''),
            'tags' => (string) ($courseValues['tags'] ?? ''),
            'category_id' => (string) ($courseValues['category_id'] ?? ''),
            'price' => (string) ($courseValues['price'] ?? '0'),
            'discount_price' => (string) ($courseValues['discount_price'] ?? ''),
            'level' => (string) ($courseValues['level'] ?? 'beginner'),
            'language' => (string) ($courseValues['language'] ?? 'English'),
            'duration' => (string) ($courseValues['duration_hours'] ?? ''),
            'intro_video_url' => (string) ($courseValues['intro_video_url'] ?? ''),
            'thumbnail' => (string) ($courseValues['existing_thumbnail'] ?? ''),
        ];
        $fallback = ['course' => $fallbackCourse, 'sections' => $sections, 'meta' => ['id' => $courseId, 'status' => (string) ($authoring['meta']['status'] ?? 'draft')]];
        return CreateCoursePage::render($categories, $fallback, $exception->getMessage(), false, $instructorName);
    }
};
