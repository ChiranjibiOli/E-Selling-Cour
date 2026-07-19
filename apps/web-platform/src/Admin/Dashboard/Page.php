<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminDashboardPage
{
    public static function render(int $instructors, int $courses, string $error = ''): Response
    {
        $content = $error !== '' ? '<div class="form-alert error">' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>' : '';
        $content .= '<section class="portal-grid"><article class="portal-card"><span class="status-badge pending">Needs review</span><h2>' . $instructors . '</h2><p>Instructor applications awaiting an identity decision.</p><a class="portal-link" href="/admin/instructor-approvals">Review instructors →</a></article>'
            . '<article class="portal-card"><span class="status-badge pending">Needs review</span><h2>' . $courses . '</h2><p>Submitted courses awaiting publication approval.</p><a class="portal-link" href="/admin/course-approvals">Review courses →</a></article>'
            . '<article class="portal-card"><h2>Controlled publishing</h2><p>Drafts remain invisible. Only reviewed courses become part of the public catalog.</p><a class="portal-link" href="/courses">View public catalog →</a></article></section>';
        return PortalPage::render('admin', 'Control overview', $content);
    }
}
