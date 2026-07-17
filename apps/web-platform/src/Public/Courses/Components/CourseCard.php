<?php

declare(strict_types=1);

final class CatalogCourseCard
{
    public static function render(array $course): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = (int) ($course['id'] ?? 0);
        $image = (string) ($course['thumbnail_url'] ?? '');
        $media = $image !== '' ? '<img src="' . $e($image) . '" alt="" loading="lazy">' : '<div class="catalog-fallback">CourseHub</div>';
        $price = (float) ($course['price'] ?? 0);
        return '<article class="catalog-card"><a class="catalog-media" href="/course?id=' . $id . '">' . $media . '</a><div class="catalog-card-body">'
            . '<div class="catalog-meta"><span>' . $e($course['category_name'] ?? 'Course') . '</span><span>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . '</span></div>'
            . '<h2><a href="/course?id=' . $id . '">' . $e($course['title'] ?? 'Untitled course') . '</a></h2><p>' . $e($course['short_description'] ?? '') . '</p>'
            . '<footer><small>' . $e($course['instructor_name'] ?? 'Instructor') . '</small><strong>' . ($price > 0 ? 'NPR ' . number_format($price, 0) : 'Free') . '</strong></footer></div></article>';
    }
}
