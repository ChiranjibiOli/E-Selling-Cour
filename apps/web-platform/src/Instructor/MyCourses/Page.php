<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorCoursesPage
{
    public static function render(array $courses, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($courses === []) {
            $content .= '<div class="portal-empty"><h2>Your studio is ready.</h2><p>Create the first course, save it privately as a draft, then submit it when the details are ready.</p><a class="portal-link" href="/instructor/courses/create">Create your first course →</a></div>';
        } else {
            $content .= '<section class="portal-grid">';
            foreach ($courses as $course) {
                $status = (string) ($course['status'] ?? 'draft');
                $review = $status === 'rejected' && trim((string) ($course['review_note'] ?? '')) !== ''
                    ? '<p><strong>Review note:</strong> ' . $e($course['review_note']) . '</p>' : '';
                $actions = '<a class="portal-link" href="/instructor/courses/edit?id=' . (int) ($course['id'] ?? 0) . '">Open course</a>';
                if (in_array($status, ['draft', 'rejected'], true)) {
                    $actions .= '<form method="post" action="/instructor/courses" style="display:inline">' . Csrf::field()
                        . '<input type="hidden" name="course_id" value="' . (int) ($course['id'] ?? 0) . '"><button class="portal-button" type="submit">Submit for approval</button></form>';
                }
                $content .= '<article class="portal-card"><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span><h2>' . $e($course['title'] ?? 'Untitled course') . '</h2>'
                    . '<p>' . $e($course['short_description'] ?? '') . '</p>' . $review
                    . '<small>' . $e($course['category_name'] ?? 'Uncategorized') . ' · ' . $e(ucfirst((string) ($course['level'] ?? 'beginner'))) . ' · NPR ' . number_format((float) ($course['price'] ?? 0), 2) . '</small>'
                    . '<footer>' . $actions . '</footer></article>';
            }
            $content .= '</section>';
        }
        return PortalPage::render('instructor', 'My courses', $content, '<a class="portal-button" href="/instructor/courses/create">Create course</a>');
    }
}
