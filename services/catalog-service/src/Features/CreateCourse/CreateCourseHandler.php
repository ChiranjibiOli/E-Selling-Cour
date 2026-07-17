<?php

declare(strict_types=1);

namespace CourseHub\Catalog\Features\CreateCourse;

final class CreateCourseHandler
{
    public function __construct(private readonly CreateCourseRepository $repository)
    {
    }

    public function handle(array $course, int $instructorId): array
    {
        $id = $this->repository->insertDraft($course, $instructorId);
        return ['id' => $id, 'status' => 'draft'];
    }
}
