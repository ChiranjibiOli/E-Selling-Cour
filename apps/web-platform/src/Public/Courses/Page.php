<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Session\AuthSession;
use CourseHub\WebPlatform\Shared\Ui\PublicNavbar;

require_once __DIR__ . '/Components/CourseCard.php';

final class CourseCatalogPage
{
    public static function render(CourseCatalogViewModel $model): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $student = AuthSession::role() === 'student';
        $cards = '';
        foreach ($model->courses as $course) {
            $cards .= CatalogCourseCard::render($course);
        }
        if ($cards === '') {
            $cards = $student
                ? '<div class="catalog-empty"><h2>No new courses found.</h2><p>Courses already purchased are hidden from Browse Courses. Open My Courses to continue learning.</p><a class="catalog-action" href="/student/my-courses">Open My Courses</a></div>'
                : '<div class="catalog-empty"><h2>No courses found.</h2><p>Try another keyword or filter.</p></div>';
        }

        $options = '<option value="">All categories</option>';
        foreach ($model->categories as $category) {
            $slug = (string) ($category['slug'] ?? '');
            $selected = $slug === $model->filters->category ? ' selected' : '';
            $options .= '<option value="' . $e($slug) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }

        $notice = $model->available ? '' : '<div class="catalog-notice">The course catalogue is temporarily unavailable. Please refresh after the services are running.</div>';
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f0e5"><title>Explore courses | CourseHub</title>'
            . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Courses/page.css"><link rel="stylesheet" href="/assets/css/course-card-theme.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1"><link rel="stylesheet" href="/assets/css/course-human-system.css?v=20260728-1">' . PublicNavbar::styles() . '</head><body class="catalog-body">'
            . PublicNavbar::render('courses', $student ? 'student' : 'guest')
            . '<main><section class="catalog-hero"><span>COURSE CATALOGUE</span><h1>Find a course worth continuing.</h1><p>'
            . ($student ? 'Browse only courses not already in your learning library. Purchased courses stay in My Courses.' : 'Search reviewed courses, compare the level and learning direction, then purchase once for lifetime Student access.')
            . '</p></section>'
            . '<section class="catalog-toolbar"><form method="get" action="/courses"><label>Search<input type="search" name="q" value="' . $e($model->filters->query) . '" placeholder="Web development, networking…"></label>'
            . '<label>Category<select name="category">' . $options . '</select></label><label>Level<select name="level"><option value="">All levels</option>'
            . '<option value="beginner"' . ($model->filters->level === 'beginner' ? ' selected' : '') . '>Beginner</option><option value="intermediate"' . ($model->filters->level === 'intermediate' ? ' selected' : '') . '>Intermediate</option><option value="advanced"' . ($model->filters->level === 'advanced' ? ' selected' : '') . '>Advanced</option></select></label>'
            . '<button type="submit">Apply filters</button><a href="/courses">Reset</a></form></section>' . $notice
            . '<section class="catalog-results"><div class="catalog-heading"><div><span>COURSEHUB LEARNING</span><h2>' . ($student ? 'Courses available to buy' : 'Available courses') . '</h2></div><strong>' . count($model->courses) . ' shown</strong></div><div class="catalog-grid">' . $cards . '</div></section></main>'
            . '<footer class="catalog-footer"><a href="/">CourseHub</a><span>Education that moves with you.</span></footer>' . PublicNavbar::script() . '<script src="/assets/js/course-card-theme.js" defer></script></body></html>';
        return Response::html($html);
    }
}
