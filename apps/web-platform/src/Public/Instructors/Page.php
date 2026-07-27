<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class InstructorsPage
{
    public static function render(array $courses = [], string $error = ''): Response
    {
        return Response::redirect('/courses');
    }
}
