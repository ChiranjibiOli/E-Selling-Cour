<?php

declare(strict_types=1);

namespace CourseHub\Catalog\Features\CreateCourse;

final class CreateCourseMiddleware
{
    public function assertAllowed(array $actor): void
    {
        if (($actor['role'] ?? null) !== 'instructor' || ($actor['status'] ?? null) !== 'active') {
            throw new \RuntimeException('An approved instructor account is required.');
        }
    }
}
