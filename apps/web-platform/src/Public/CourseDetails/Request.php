<?php

declare(strict_types=1);

final class CourseDetailsRequest
{
    public function __construct(public readonly int $courseId) {}

    public static function from(array $query): self
    {
        $id = filter_var($query['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DomainException('A valid course ID is required.');
        }
        return new self((int) $id);
    }
}
