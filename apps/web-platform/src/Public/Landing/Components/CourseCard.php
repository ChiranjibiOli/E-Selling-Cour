<?php

declare(strict_types=1);

final class LandingCourseCard
{
    public static function render(array $course): string
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $id = (int) ($course['id'] ?? 0);
        $image = $e($course['thumbnail_url'] ?? '');
        $imageHtml = $image !== ''
            ? '<img src="' . $image . '" alt="" loading="lazy">'
            : '<div class="course-card-placeholder">CH</div>';
        $price = (float) ($course['price'] ?? 0);
        $priceText = $price > 0 ? 'NPR ' . number_format($price, 0) : 'Free';
        $level = ucfirst((string) ($course['level'] ?? 'beginner'));
        $language = (string) ($course['language'] ?? 'English');

        return '<article class="course-card"><div class="course-card-crown"><span>FEATURED COURSE</span><b aria-hidden="true">✦</b></div>'
            . '<a class="course-card-media" href="/course?id=' . $id . '">' . $imageHtml . '</a>'
            . '<div class="course-card-body"><span>' . $e($course['category_name'] ?? 'Course') . '</span>'
            . '<h3><a href="/course?id=' . $id . '">' . $e($course['title'] ?? 'Untitled course') . '</a></h3>'
            . '<p>' . $e($course['short_description'] ?? '') . '</p>'
            . '<div class="course-card-meta"><small>' . $e($level) . '</small><small>' . $e($language) . '</small><small>Lifetime</small></div>'
            . '<footer><small>By ' . $e($course['instructor_name'] ?? 'CourseHub instructor') . '</small><strong>' . $e($priceText) . '</strong></footer>'
            . '<a class="course-card-action" href="/course?id=' . $id . '">View course <span aria-hidden="true">↗</span></a></div></article>';
    }
}
