<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

require_once __DIR__ . '/Components/CourseCard.php';

final class CourseCatalogPage
{
    public static function render(CourseCatalogViewModel $model): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cards = '';
        foreach ($model->courses as $course) {
            $cards .= CatalogCourseCard::render($course);
        }
        if ($cards === '') {
            $cards = '<div class="catalog-empty"><h2>No courses found.</h2><p>Try another keyword or filter.</p></div>';
        }

        $options = '<option value="">All categories</option>';
        foreach ($model->categories as $category) {
            $slug = (string) ($category['slug'] ?? '');
            $selected = $slug === $model->filters->category ? ' selected' : '';
            $options .= '<option value="' . $e($slug) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }

        $notice = $model->available ? '' : '<div class="catalog-notice">The catalog service is unavailable. Start the backend services and refresh.</div>';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Explore courses | CourseHub</title>'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Courses/page.css"></head><body class="catalog-body">'
            . '<header class="catalog-header"><a href="/">CourseHub</a><nav><a class="active" href="/courses">Courses</a><a href="/instructors">Instructors</a><a href="/about">About</a></nav><a class="catalog-signin" href="/login">Sign in</a></header>'
            . '<main><section class="catalog-hero"><span>COURSE CATALOG</span><h1>Find the next skill worth learning.</h1><p>Browse approved courses from CourseHub instructors. Preview the course first, then purchase once for lifetime access.</p></section>'
            . '<section class="catalog-toolbar"><form method="get" action="/courses"><label>Search<input type="search" name="q" value="' . $e($model->filters->query) . '" placeholder="Web development, networking…"></label>'
            . '<label>Category<select name="category">' . $options . '</select></label><label>Level<select name="level"><option value="">All levels</option>'
            . '<option value="beginner"' . ($model->filters->level === 'beginner' ? ' selected' : '') . '>Beginner</option><option value="intermediate"' . ($model->filters->level === 'intermediate' ? ' selected' : '') . '>Intermediate</option><option value="advanced"' . ($model->filters->level === 'advanced' ? ' selected' : '') . '>Advanced</option></select></label>'
            . '<button type="submit">Apply filters</button><a href="/courses">Reset</a></form></section>' . $notice
            . '<section class="catalog-results"><div class="catalog-heading"><div><span>CURATED COURSEHUB LEARNING</span><h2>Available courses</h2></div><strong>' . count($model->courses) . ' shown</strong></div><div class="catalog-grid">' . $cards . '</div></section></main>'
            . '<footer class="catalog-footer"><a href="/">CourseHub</a><span>Learn carefully. Build properly.</span></footer></body></html>';
        return Response::html($html);
    }
}
