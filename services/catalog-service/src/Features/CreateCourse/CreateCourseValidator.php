<?php

declare(strict_types=1);

namespace CourseHub\Catalog\Features\CreateCourse;

final class CreateCourseValidator
{
    public function validate(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            throw new \InvalidArgumentException('A course title up to 180 characters is required.');
        }

        return [
            'title' => $title,
            'category_id' => isset($input['category_id']) ? (int) $input['category_id'] : null,
            'short_description' => trim((string) ($input['short_description'] ?? '')),
            'full_description' => trim((string) ($input['full_description'] ?? '')),
            'price' => max(0, (float) ($input['price'] ?? 0)),
            'status' => 'draft',
        ];
    }
}
