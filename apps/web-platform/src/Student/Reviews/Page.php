<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentReviewsPage
{
    public static function render(array $reviews, array $eligible, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $cards = '';
        foreach ($reviews as $review) {
            $ratingOptions = '';
            for ($rating = 5; $rating >= 1; $rating--) {
                $ratingOptions .= '<option value="' . $rating . '"' . ((int) ($review['rating'] ?? 0) === $rating ? ' selected' : '') . '>' . $rating . ' star' . ($rating === 1 ? '' : 's') . '</option>';
            }
            $cards .= '<article class="portal-card"><span class="status-badge ' . $e((string) ($review['status'] ?? 'visible')) . '">' . $e((string) ($review['status'] ?? 'visible')) . '</span><h2>' . $e($review['course_title'] ?? 'Course review') . '</h2>'
                . '<form class="portal-form" method="post" action="/student/reviews">' . Csrf::field() . '<input type="hidden" name="course_id" value="' . (int) ($review['course_id'] ?? 0) . '">'
                . '<label>Rating<select name="rating">' . $ratingOptions . '</select></label><label>Review<textarea name="review_text" rows="5" maxlength="5000">' . $e($review['review_text'] ?? '') . '</textarea></label>'
                . '<div class="actions"><button class="portal-button" name="action" value="save" type="submit">Save changes</button></div></form>'
                . '<form method="post" action="/student/reviews">' . Csrf::field() . '<input type="hidden" name="review_id" value="' . (int) ($review['id'] ?? 0) . '"><button class="portal-button danger" name="action" value="delete" type="submit">Delete review</button></form></article>';
        }
        if ($cards === '') {
            $cards = '<div class="rich-empty"><h3>No reviews written</h3><p>Only courses with verified active enrollment can receive your review.</p></div>';
        }
        $eligibleOptions = '';
        foreach ($eligible as $course) {
            $eligibleOptions .= '<option value="' . (int) ($course['course_id'] ?? 0) . '">' . $e($course['title'] ?? 'Course') . ' · ' . $e($course['instructor_name'] ?? 'Instructor') . '</option>';
        }
        $create = '';
        if ($eligibleOptions !== '') {
            $create = '<section class="data-card"><div class="data-card-head"><div><span>VERIFIED FEEDBACK</span><h3>Review a purchased course</h3></div></div><form class="panel-form" method="post" action="/student/reviews">' . Csrf::field()
                . '<label>Course<select name="course_id" required><option value="">Choose course</option>' . $eligibleOptions . '</select></label><label>Rating<select name="rating" required><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select></label>'
                . '<label>Review<textarea name="review_text" rows="6" maxlength="5000" placeholder="Share specific, useful feedback"></textarea></label><button class="portal-button" name="action" value="save" type="submit">Publish verified review</button></form></section>';
        }
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Reviews written</span><i></i></div><strong>' . count($reviews) . '</strong><small>One review per course</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Courses to review</span><i></i></div><strong>' . count($eligible) . '</strong><small>Verified active enrollments</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Rating range</span><i></i></div><strong>1–5</strong><small>Validated by the server</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Trust</span><i></i></div><strong>Verified</strong><small>No unpurchased-course reviews</small></article></section>'
            . $create . '<section class="data-card"><div class="data-card-head"><div><span>MY REVIEWS</span><h3>Manage your course feedback</h3></div></div><div class="portal-grid">' . $cards . '</div></section>';
        return PortalPage::render('student', 'My reviews', $content);
    }
}
