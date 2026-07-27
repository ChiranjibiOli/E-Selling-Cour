<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

return static function (Request $request): Response {
    RoomRuntime::authorize(__DIR__, $request);
    $courseId = filter_var($request->query['course'] ?? 0, FILTER_VALIDATE_INT);
    $destination = '/instructor/courses/create';
    if ($courseId !== false && $courseId > 0) {
        $destination .= '?course=' . (int) $courseId . '#curriculum';
    } else {
        $destination .= '#curriculum';
    }
    return Response::redirect($destination);
};
