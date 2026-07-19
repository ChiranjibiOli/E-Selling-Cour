<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorDashboardPage
{
    public static function render(array $data, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $courses = is_array($data['courses'] ?? null) ? $data['courses'] : [];
        $business = is_array($data['business'] ?? null) ? $data['business'] : [];
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Published courses</span><i></i></div><strong>' . (int) ($courses['published'] ?? 0) . '</strong><small>Visible in the public catalog</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Active students</span><i></i></div><strong>' . (int) ($business['students'] ?? 0) . '</strong><small>' . (int) ($business['enrollments'] ?? 0) . ' active enrollments</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Available earnings</span><i></i></div><strong>NPR ' . number_format((float) ($business['available_earnings'] ?? 0), 2) . '</strong><small>Verified and withdrawable</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Gross sales</span><i></i></div><strong>NPR ' . number_format((float) ($business['gross_sales'] ?? 0), 2) . '</strong><small>Before platform commission</small></article></section>'
            . '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>COURSE WORKFLOW</span><h3>Your studio status</h3></div><a class="portal-button" href="/instructor/courses/create">+ Create course</a></div><div class="status-overview-grid">'
            . '<a href="/instructor/courses/drafts"><strong>' . (int) ($courses['draft'] ?? 0) . '</strong><span>Private drafts</span></a><a href="/instructor/courses/pending"><strong>' . (int) ($courses['pending'] ?? 0) . '</strong><span>Pending review</span></a><a href="/instructor/courses/published"><strong>' . (int) ($courses['published'] ?? 0) . '</strong><span>Published</span></a><a href="/instructor/courses"><strong>' . (int) ($courses['rejected'] ?? 0) . '</strong><span>Needs changes</span></a></div></section>'
            . '<aside class="summary-card accent-card"><span>EARNINGS POSITION</span><div class="summary-row"><span>Available</span><strong>NPR ' . number_format((float) ($business['available_earnings'] ?? 0), 2) . '</strong></div><div class="summary-row"><span>Reserved</span><strong>NPR ' . number_format((float) ($business['reserved_earnings'] ?? 0), 2) . '</strong></div><div class="summary-row"><span>Paid</span><strong>NPR ' . number_format((float) ($business['paid_earnings'] ?? 0), 2) . '</strong></div><a class="portal-button full" href="/instructor/withdrawals">Open withdrawals</a><a class="portal-button secondary full" href="/instructor/sales">View sales</a></aside></div>';
        return PortalPage::render('instructor', 'Studio overview', $content, '<a class="portal-button" href="/instructor/courses/create">Create course</a>');
    }
}
