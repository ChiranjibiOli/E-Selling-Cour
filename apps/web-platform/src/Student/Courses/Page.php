<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentCoursesPage
{
    public static function render(
        array $courses,
        array $categories,
        array $ownedCourseIds,
        array $selectedCourse = [],
        array $filters = [],
        string $error = '',
    ): Response {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $query = (string) ($filters['q'] ?? '');
        $selectedCategory = (string) ($filters['category'] ?? '');
        $selectedLevel = (string) ($filters['level'] ?? '');

        $categoryOptions = '<option value="">All categories</option>';
        foreach ($categories as $category) {
            $slug = (string) ($category['slug'] ?? '');
            $categoryOptions .= '<option value="' . $e($slug) . '"' . ($selectedCategory === $slug ? ' selected' : '') . '>'
                . $e($category['name'] ?? 'Category') . '</option>';
        }

        $levelOptions = '<option value="">All levels</option>';
        foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label) {
            $levelOptions .= '<option value="' . $value . '"' . ($selectedLevel === $value ? ' selected' : '') . '>' . $label . '</option>';
        }

        $selectedPanel = '';
        if ($selectedCourse !== []) {
            $courseId = (int) ($selectedCourse['id'] ?? 0);
            $image = trim((string) ($selectedCourse['thumbnail_url'] ?? ''));
            $media = $image !== ''
                ? '<img src="' . $e($image) . '" alt="" loading="lazy">'
                : '<span>CH</span>';
            $outcomes = self::listItems($selectedCourse['learning_outcomes'] ?? [], $e);
            $requirements = self::listItems($selectedCourse['requirements'] ?? [], $e);
            $price = (float) ($selectedCourse['price'] ?? 0);
            $originalPrice = (float) ($selectedCourse['original_price'] ?? $price);
            $priceLine = $originalPrice > $price
                ? '<strong>NPR ' . number_format($price, 2) . '</strong><small><s>NPR ' . number_format($originalPrice, 2) . '</s></small>'
                : '<strong>' . ($price > 0 ? 'NPR ' . number_format($price, 2) : 'Free') . '</strong>';

            $selectedPanel = '<section class="data-card"><div class="data-card-head"><div><span>COURSE DETAILS</span><h3>' . $e($selectedCourse['title'] ?? 'Published course') . '</h3></div><a class="text-button" href="/student/courses">Close details</a></div>'
                . '<div class="panel-split panel-split-wide"><div class="learning-course-card"><div class="learning-course-media">' . $media . '</div><div class="learning-course-copy"><span>'
                . $e($selectedCourse['category_name'] ?? 'Course') . ' · ' . $e(ucfirst((string) ($selectedCourse['level'] ?? 'beginner'))) . '</span><h3>'
                . $e($selectedCourse['title'] ?? 'Course') . '</h3><p>' . nl2br($e($selectedCourse['full_description'] ?? $selectedCourse['short_description'] ?? '')) . '</p><small>By '
                . $e($selectedCourse['instructor_name'] ?? 'CourseHub instructor') . ' · ' . $e($selectedCourse['language'] ?? 'English') . ' · '
                . $e($selectedCourse['duration'] ?? 'Self-paced') . '</small><footer><a class="portal-button" href="/student/cart?add=' . $courseId . '">Add to cart</a><a class="portal-button secondary" href="/student/cart">View cart</a></footer></div></div>'
                . '<aside class="summary-card"><span>COURSE PRICE</span><div class="summary-total"><span>Payable</span><div>' . $priceLine . '</div></div>'
                . ($outcomes !== '' ? '<h4>What you will learn</h4><ul class="clean-list">' . $outcomes . '</ul>' : '')
                . ($requirements !== '' ? '<h4>Requirements</h4><ul class="clean-list">' . $requirements . '</ul>' : '')
                . '<div class="payment-note"><span>i</span><p>Paid courses use manual payment proof and Admin verification. Automatic gateway checkout is not available.</p></div>'
                . '</aside></div></section>';
        }

        $cards = '';
        foreach ($courses as $course) {
            $courseId = (int) ($course['id'] ?? 0);
            if (isset($ownedCourseIds[$courseId])) {
                continue;
            }
            $image = trim((string) ($course['thumbnail_url'] ?? ''));
            $media = $image !== ''
                ? '<img src="' . $e($image) . '" alt="" loading="lazy">'
                : '<span>CH</span>';
            $rawPrice = (float) ($course['price'] ?? 0);
            $originalPrice = (float) ($course['original_price'] ?? $rawPrice);
            $price = number_format($rawPrice, 2);
            $priceCopy = $originalPrice > $rawPrice
                ? '<b>NPR ' . $price . '</b><small><s>NPR ' . number_format($originalPrice, 2) . '</s></small>'
                : '<b>' . ($rawPrice > 0 ? 'NPR ' . $price : 'Free') . '</b>';

            $cards .= '<article class="learning-course-card"><div class="learning-course-media">' . $media
                . '<span class="status-badge published">Available</span>'
                . '</div><div class="learning-course-copy"><span>' . $e($course['category_name'] ?? 'Course') . ' · '
                . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . '</span><h3>' . $e($course['title'] ?? 'Published course') . '</h3><p>'
                . $e($course['short_description'] ?? '') . '</p><small>By ' . $e($course['instructor_name'] ?? 'CourseHub instructor') . ' · '
                . $e($course['language'] ?? 'English') . ' · ' . $e($course['duration'] ?? 'Self-paced') . '</small><div class="summary-row"><span>Lifetime access</span><span>'
                . $priceCopy . '</span></div><footer><a class="portal-button" href="/student/cart?add=' . $courseId . '">Add to cart</a><a class="portal-button secondary" href="/student/courses?course=' . $courseId . '">View details</a></footer></div></article>';
        }

        if ($cards === '') {
            $cards = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No new courses match these filters</h3><p>Courses already in your learning library are hidden here. Open My Courses to continue learning.</p><a class="portal-button" href="/student/my-courses">Open My Courses</a><a class="portal-button secondary" href="/student/courses">Clear filters</a></div>';
        }

        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $filterForm = '<form class="admin-console-toolbar" method="get" action="/student/courses"><label>Search<input type="search" name="q" value="' . $e($query) . '" placeholder="Course, skill or instructor"></label>'
            . '<label>Category<select name="category">' . $categoryOptions . '</select></label><label>Level<select name="level">' . $levelOptions . '</select></label>'
            . '<button class="portal-button secondary" type="submit">Apply filters</button><a class="text-button" href="/student/courses">Clear</a></form>';

        $content = $alert
            . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Available courses</span><i></i></div><strong>' . count($courses) . '</strong><small>Owned courses are hidden</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Categories</span><i></i></div><strong>' . count($categories) . '</strong><small>Active catalogue groups</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Payment</span><i></i></div><strong>Manual</strong><small>Proof checked by Admin</small></article></section>'
            . $selectedPanel
            . '<section class="data-card"><div class="data-card-head"><div><span>STUDENT CATALOGUE</span><h3>Courses available to buy</h3></div><a class="portal-button secondary" href="/student/cart">My cart</a></div>'
            . $filterForm . '<div class="learning-course-grid">' . $cards . '</div></section>';

        return PortalPage::render('student', 'All courses', $content, '<a class="portal-button secondary" href="/student/my-courses">My purchased courses</a>');
    }

    private static function listItems(mixed $items, callable $escape): string
    {
        if (!is_array($items)) {
            return '';
        }
        $html = '';
        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $html .= '<li>' . $escape($text) . '</li>';
            }
        }
        return $html;
    }
}
