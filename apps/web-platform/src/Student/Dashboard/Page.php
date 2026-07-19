<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentDashboardPage
{
    public static function render(array $courses, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $content = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content .= '<section class="portal-grid"><article class="portal-card"><h2>' . count($courses) . '</h2><p>Published courses available to explore now.</p><a class="portal-link" href="/courses">Explore catalog →</a></article>';
        foreach (array_slice($courses, 0, 2) as $course) {
            $content .= '<article class="portal-card"><span class="status-badge published">Published</span><h2>' . $e($course['title'] ?? '') . '</h2><p>' . $e($course['short_description'] ?? '') . '</p><a class="portal-link" href="/course?id=' . (int) ($course['id'] ?? 0) . '">View course →</a></article>';
        }
        $content .= '</section>';
        return PortalPage::render('student', 'Learning overview', $content);
    }
}
