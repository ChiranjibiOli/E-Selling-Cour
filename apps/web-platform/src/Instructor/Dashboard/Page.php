<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorDashboardPage
{
    public static function render(array $courses, string $error = ''): Response
    {
        $counts = array_fill_keys(['draft', 'pending', 'published', 'rejected'], 0);
        foreach ($courses as $course) {
            $status = (string) ($course['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }
        $content = $error !== '' ? '<div class="form-alert error">' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>' : '';
        $content .= '<section class="portal-grid">';
        foreach ($counts as $status => $count) {
            $content .= '<article class="portal-card"><span class="status-badge ' . $status . '">' . $status . '</span><h2>' . $count . '</h2><p>' . ucfirst($status) . ' courses in your studio.</p></article>';
        }
        $content .= '<article class="portal-card"><h2>Course workflow</h2><p>Build privately in Draft, submit when ready, then respond to the administrator review without exposing unfinished work.</p><a class="portal-link" href="/instructor/courses">Manage courses →</a></article></section>';
        return PortalPage::render('instructor', 'Studio overview', $content, '<a class="portal-button" href="/instructor/courses/create">Create course</a>');
    }
}
