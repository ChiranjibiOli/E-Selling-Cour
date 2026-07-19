<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class StudentNotificationsPage
{
    public static function render(array $notifications, int $unread, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $list = '';
        foreach ($notifications as $notification) {
            $isRead = (int) ($notification['is_read'] ?? 0) === 1;
            $list .= '<article class="portal-card notification-card' . ($isRead ? '' : ' unread') . '"><span class="status-badge ' . ($isRead ? 'published' : 'pending') . '">' . ($isRead ? 'Read' : 'New') . '</span>'
                . '<h2>' . $e($notification['title'] ?? 'CourseHub update') . '</h2><p>' . $e($notification['message'] ?? '') . '</p><small>' . $e($notification['created_at'] ?? '') . ' · ' . $e($notification['notification_type'] ?? 'general') . '</small>'
                . (!$isRead ? '<form method="post" action="/student/notifications">' . Csrf::field() . '<input type="hidden" name="notification_id" value="' . (int) ($notification['id'] ?? 0) . '"><button class="portal-button secondary" type="submit">Mark as read</button></form>' : '') . '</article>';
        }
        if ($list === '') {
            $list = '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>No notifications yet</h3><p>Payment, enrollment and course updates will appear here.</p></div>';
        }
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Unread</span><i></i></div><strong>' . $unread . '</strong><small>Updates needing attention</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>All updates</span><i></i></div><strong>' . count($notifications) . '</strong><small>Latest 100 records</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Payment events</span><i></i></div><strong>Linked</strong><small>Submission and decisions</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Course access</span><i></i></div><strong>Tracked</strong><small>Enrollment activation</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>ACTIVITY INBOX</span><h3>Your CourseHub updates</h3></div>'
            . ($unread > 0 ? '<form method="post" action="/student/notifications">' . Csrf::field() . '<button class="portal-button secondary" name="action" value="read_all" type="submit">Mark all as read</button></form>' : '')
            . '</div><div class="portal-grid">' . $list . '</div></section>';
        return PortalPage::render('student', 'Notifications', $content);
    }
}
