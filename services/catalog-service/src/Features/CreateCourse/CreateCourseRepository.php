<?php

declare(strict_types=1);

namespace CourseHub\Catalog\Features\CreateCourse;

use PDO;

final class CreateCourseRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function insertDraft(array $course, int $instructorId): int
    {
        $statement = $this->database->prepare('INSERT INTO courses (instructor_id, category_id, title, slug, short_description, full_description, price, status) VALUES (:instructor_id, :category_id, :title, :slug, :short_description, :full_description, :price, :status)');
        $statement->execute([
            'instructor_id' => $instructorId,
            'category_id' => $course['category_id'],
            'title' => $course['title'],
            'slug' => strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $course['title']), '-')) . '-' . bin2hex(random_bytes(3)),
            'short_description' => $course['short_description'],
            'full_description' => $course['full_description'],
            'price' => $course['price'],
            'status' => 'draft',
        ]);
        return (int) $this->database->lastInsertId();
    }
}
