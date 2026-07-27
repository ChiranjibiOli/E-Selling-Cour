<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class InstructorsPage
{
    public static function render(array $courses, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $instructors = [];
        foreach ($courses as $course) {
            $name = trim((string) ($course['instructor_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (!isset($instructors[$key])) {
                $instructors[$key] = ['name' => $name, 'courses' => [], 'categories' => []];
            }
            $instructors[$key]['courses'][] = $course;
            $category = trim((string) ($course['category_name'] ?? ''));
            if ($category !== '') {
                $instructors[$key]['categories'][$category] = true;
            }
        }

        $cards = '';
        foreach ($instructors as $instructor) {
            $name = (string) $instructor['name'];
            $parts = preg_split('/\s+/', trim($name)) ?: [];
            $initials = '';
            foreach (array_slice($parts, 0, 2) as $part) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
            $initials = $initials !== '' ? $initials : 'CH';
            $courseLinks = '';
            foreach (array_slice($instructor['courses'], 0, 3) as $course) {
                $courseLinks .= '<a href="/course?id=' . (int) ($course['id'] ?? 0) . '"><span>' . $e($course['category_name'] ?? 'Course') . '</span><strong>' . $e($course['title'] ?? 'Untitled course') . '</strong><small>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . ' · NPR ' . number_format((float) ($course['price'] ?? 0), 0) . '</small></a>';
            }
            $categoryText = implode(' · ', array_keys($instructor['categories']));
            $cards .= '<article class="instructor-card"><header><span class="instructor-avatar">' . $e($initials) . '</span><div><small>APPROVED PUBLISHED INSTRUCTOR</small><h2>' . $e($name) . '</h2><p>' . $e($categoryText !== '' ? $categoryText : 'Published CourseHub courses') . '</p></div></header>'
                . '<div class="instructor-stats"><span><strong>' . count($instructor['courses']) . '</strong><small>Published course' . (count($instructor['courses']) === 1 ? '' : 's') . '</small></span><span><strong>Reviewed</strong><small>Publishing access</small></span><span><strong>Lifetime</strong><small>Student access model</small></span></div>'
                . '<div class="instructor-courses">' . $courseLinks . '</div><a class="instructor-catalog-link" href="/courses?q=' . rawurlencode($name) . '">View published courses →</a></article>';
        }
        if ($cards === '') {
            $cards = '<div class="instructors-empty"><span>CH</span><h2>No published instructors yet.</h2><p>Approved instructors appear here after at least one course is reviewed and published.</p><a href="/courses">Open the course catalog</a></div>';
        }
        $notice = $error !== '' ? '<div class="instructors-notice">' . $e($error) . '</div>' : '';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Meet CourseHub instructors with reviewed, published courses."><title>Instructors | CourseHub</title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/room-assets/Public/Instructors/page.css"></head>'
            . '<body class="instructors-body"><header class="instructors-header"><a href="/">CourseHub</a><nav><a href="/courses">Courses</a><a class="active" href="/instructors">Instructors</a><a href="/pricing">Pricing</a><a href="/about">About</a><a href="/contact">Contact</a><a class="instructors-apply" href="/login">Sign in</a></nav></header>'
            . '<main><section class="instructors-hero"><span>COURSEHUB INSTRUCTORS</span><h1>Learn from instructors whose courses reached the published catalog.</h1><p>This directory is built from live published-course records. A pending application, private draft, or rejected course does not create a public instructor listing.</p><div><a href="/courses">Explore all courses →</a><a href="/contact">Contact support</a></div></section>'
            . $notice . '<section class="instructors-directory"><div class="instructors-heading"><div><span>PUBLIC DIRECTORY</span><h2>Published teaching profiles</h2></div><p>' . count($instructors) . ' instructor' . (count($instructors) === 1 ? '' : 's') . ' currently represented by approved courses.</p></div><div class="instructors-grid">' . $cards . '</div></section>'
            . '<section class="instructors-standard"><div><span>QUALITY FLOW</span><h2>Application approval is only the beginning.</h2></div><ol><li><b>01</b><strong>Apply</strong><span>Submit identity and teaching information through the dedicated Instructor portal.</span></li><li><b>02</b><strong>Get approved</strong><span>Administrator reviews the instructor application.</span></li><li><b>03</b><strong>Build privately</strong><span>Create drafts and curriculum inside the studio.</span></li><li><b>04</b><strong>Submit course</strong><span>The complete course enters admin review.</span></li><li><b>05</b><strong>Publish</strong><span>Only an approved course appears here and in the catalog.</span></li></ol></section></main>'
            . '<footer class="instructors-footer"><a href="/">CourseHub</a><div><a href="/courses">Courses</a><a href="/pricing">Pricing</a><a href="/faq">FAQ</a><a href="/contact">Support</a></div><span>Reviewed teaching, visible through published work.</span></footer></body></html>';
        return Response::html($html);
    }
}
