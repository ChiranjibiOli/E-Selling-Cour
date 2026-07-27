<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;

final class CourseDetailsPage
{
    public static function render(CourseDetailsViewModel $model): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($model->course === []) {
            return Response::html('<h1>Course not found</h1>', 404);
        }
        $course = $model->course;
        $price = (float) ($course['price'] ?? 0);
        $originalPrice = (float) ($course['original_price'] ?? $price);
        $discounted = isset($course['discount_price']) && $course['discount_price'] !== null && $price < $originalPrice;
        $image = (string) ($course['thumbnail_url'] ?? '');
        $imageHtml = $image !== '' ? '<img src="' . $e($image) . '" alt="" loading="eager">' : '<div class="detail-image-fallback">CourseHub</div>';

        $curriculum = '';
        $lessonCount = 0;
        $previewCount = 0;
        foreach ($model->sections as $index => $section) {
            $lessons = '';
            foreach ((array) ($section['lessons'] ?? []) as $lesson) {
                $lessonCount++;
                $isPreview = (int) ($lesson['is_preview'] ?? 0) === 1;
                if ($isPreview) { $previewCount++; }
                $preview = $isPreview ? '<span>Preview</span>' : '';
                $lessons .= '<li><div><b>' . str_pad((string) $lessonCount, 2, '0', STR_PAD_LEFT) . '</b><p><strong>' . $e($lesson['title'] ?? 'Lesson') . '</strong><small>' . $e($lesson['content_type'] ?? 'lesson') . ' · ' . (int) ($lesson['duration_minutes'] ?? 0) . ' min</small></p></div>' . $preview . '</li>';
            }
            $curriculum .= '<article class="curriculum-section"><header><span>SECTION ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '</span><h3>' . $e($section['title'] ?? 'Section') . '</h3></header><ul>' . $lessons . '</ul></article>';
        }
        if ($curriculum === '') {
            $curriculum = '<div class="detail-empty">Curriculum will be added by the instructor.</div>';
        }

        $list = static function (mixed $items, callable $escape): string {
            if (!is_array($items) || $items === []) {
                return '<li>Details will be added by the instructor.</li>';
            }
            $html = '';
            foreach ($items as $item) {
                $html .= '<li>' . $escape($item) . '</li>';
            }
            return $html;
        };
        $outcomes = $list($course['learning_outcomes'] ?? [], $e);
        $requirements = $list($course['requirements'] ?? [], $e);
        $audience = $list($course['target_audience'] ?? [], $e);
        $subtitle = trim((string) ($course['subtitle'] ?? ''));
        $introVideo = trim((string) ($course['intro_video_url'] ?? ''));
        $introLink = $introVideo !== '' ? '<a class="intro-video-link" href="' . $e($introVideo) . '" target="_blank" rel="noopener noreferrer">Watch course introduction ↗</a>' : '';
        $priceHtml = $discounted
            ? '<small class="original-price">NPR ' . number_format($originalPrice, 0) . '</small><strong class="price">NPR ' . number_format($price, 0) . '</strong>'
            : '<strong class="price">' . ($price > 0 ? 'NPR ' . number_format($price, 0) : 'Free') . '</strong>';
        $owned = (bool) ($course['owned'] ?? false);
        $viewerRole = (string) ($course['viewer_role'] ?? '');
        if ($owned) {
            $action = '<a class="buy-button" href="/student/course-player?course=' . (int) $course['id'] . '">Open purchased course</a>';
            $priceHtml = '<strong class="price">Access active</strong>';
        } elseif ($viewerRole === 'student') {
            $action = $price > 0
                ? '<a class="buy-button" href="/student/cart?add=' . (int) $course['id'] . '">Buy once · NPR ' . number_format($price, 0) . '</a>'
                : '<a class="buy-button" href="/student/cart?add=' . (int) $course['id'] . '">Enroll free</a>';
        } else {
            $action = '<a class="buy-button" href="/learn/sign-in">Sign in to enrol</a>';
        }
        $accountLink = $viewerRole === 'student' ? '/student/my-courses' : '/learn/sign-in';
        $accountLabel = $viewerRole === 'student' ? 'My courses' : 'Student sign in';

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($course['title'] ?? 'Course') . ' | CourseHub</title><link rel="stylesheet" href="/room-assets/Public/CourseDetails/page.css"></head><body class="detail-body">'
            . '<header class="detail-header"><a href="/">CourseHub</a><nav><a href="/courses">All courses</a><a href="' . $e($accountLink) . '">' . $e($accountLabel) . '</a></nav></header><main>'
            . '<section class="detail-hero"><div class="detail-copy"><a href="/courses">← Back to catalog</a><span>' . $e($course['category_name'] ?? 'Course') . '</span><h1>' . $e($course['title'] ?? 'Untitled course') . '</h1>'
            . ($subtitle !== '' ? '<h2>' . $e($subtitle) . '</h2>' : '') . '<p>' . $e($course['short_description'] ?? '') . '</p>' . $introLink
            . '<div class="detail-meta"><div><small>Instructor</small><strong>' . $e($course['instructor_name'] ?? 'CourseHub instructor') . '</strong></div><div><small>Level</small><strong>' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . '</strong></div><div><small>Language</small><strong>' . $e($course['language'] ?? 'English') . '</strong></div></div></div>'
            . '<aside class="detail-purchase"><div class="detail-image">' . $imageHtml . '</div><div class="purchase-body">' . $priceHtml . $action . '<ul><li>Lifetime course access</li><li>' . $lessonCount . ' structured lessons</li><li>' . $previewCount . ' public previews</li><li>Progress tracking</li><li>Secure payment verification</li></ul></div></aside></section>'
            . '<section class="detail-content"><article class="detail-description"><span>ABOUT THIS COURSE</span><h2>Course overview</h2><div>' . nl2br($e($course['full_description'] ?? 'Course details will be added soon.')) . '</div></article><aside class="detail-facts"><h3>Course information</h3><dl><div><dt>Duration</dt><dd>' . $e($course['duration'] ?? 'Self-paced') . '</dd></div><div><dt>Access</dt><dd>Lifetime</dd></div><div><dt>Status</dt><dd>Published</dd></div><div><dt>Tags</dt><dd>' . $e($course['tags'] ?? 'CourseHub learning') . '</dd></div></dl></aside></section>'
            . '<section class="course-promise"><article><span>LEARNING OUTCOMES</span><h2>What you will be able to do</h2><ul>' . $outcomes . '</ul></article><article><span>REQUIREMENTS</span><h2>What you need first</h2><ul>' . $requirements . '</ul></article><article><span>TARGET AUDIENCE</span><h2>Who this course is for</h2><ul>' . $audience . '</ul></article></section>'
            . '<section class="curriculum"><div class="curriculum-heading"><span>CURRICULUM</span><h2>Course structure</h2><p>Preview lessons are available before enrollment. Full lessons unlock after verified payment.</p></div><div class="curriculum-list">' . $curriculum . '</div></section>'
            . '</main><footer class="detail-footer"><a href="/">CourseHub</a><span>Learn with structure.</span></footer></body></html>';
        return Response::html($html);
    }
}
