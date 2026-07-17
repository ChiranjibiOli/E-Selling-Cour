<?php

declare(strict_types=1);

namespace CourseHub\Catalog\Features\CreateCourse;

final class CreateCoursePolicy
{
    public function assertInstructorOwnsDraft(int $instructorId, int $courseInstructorId): void
    {
        if ($instructorId !== $courseInstructorId) {
            throw new \RuntimeException('Course ownership check failed.');
        }
    }
}
