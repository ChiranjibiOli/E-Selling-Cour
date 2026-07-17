<?php

declare(strict_types=1);

namespace CourseHub\Catalog\Features\CreateCourse;

final class CreateCourseController
{
    public function __construct(private readonly CreateCourseHandler $handler)
    {
    }

    public function create(array $input, array $actor): array
    {
        (new CreateCourseMiddleware())->assertAllowed($actor);
        $validated = (new CreateCourseValidator())->validate($input);
        return $this->handler->handle($validated, (int) $actor['id']);
    }
}
