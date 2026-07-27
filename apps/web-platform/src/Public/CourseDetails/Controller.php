<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Service.php';
require_once __DIR__ . '/ViewModel.php';
require_once __DIR__ . '/Page.php';

return static function (Request $request): Response {
    try {
        $input = CourseDetailsRequest::from($request->query);
        CourseDetailsValidator::validate($input);
        $response = (new CourseDetailsService())->load($input->courseId);
        $course = is_array($response['data'] ?? null) ? $response['data'] : [];
        $course['owned'] = false;
        $course['viewer_role'] = AuthSession::role();

        if (AuthSession::role() === 'student') {
            try {
                $enrollments = (new ApiClient())->get('/api/v1/enrollments/mine')['data'] ?? [];
                foreach ((array) $enrollments as $enrollment) {
                    if ((int) ($enrollment['course_id'] ?? 0) === $input->courseId
                        && (string) ($enrollment['status'] ?? '') === 'active'
                    ) {
                        $course['owned'] = true;
                        break;
                    }
                }
            } catch (DomainException) {
                // The public course page still works when enrollment status is temporarily unavailable.
            }
        }

        $response['data'] = $course;
        return CourseDetailsPage::render(CourseDetailsViewModel::from($response));
    } catch (DomainException $exception) {
        return Response::html(
            '<h1>Course unavailable</h1><p>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
            404,
        );
    }
};
