<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request): Response {
    RoomRuntime::authorize(__DIR__, $request);
    $courseId = filter_var($request->query['id'] ?? $request->body['course_id'] ?? 0, FILTER_VALIDATE_INT);
    if ($courseId === false || $courseId < 1) {
        return Response::redirect('/instructor/courses');
    }
    return Response::redirect('/instructor/courses/create?course=' . (int) $courseId);
};
