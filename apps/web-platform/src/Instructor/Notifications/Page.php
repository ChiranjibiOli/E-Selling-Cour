<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorNotificationsPage
{
    public static function render(array $notifications, int $unread, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $rows = '';
        foreach ($notifications as $notification) {
            $id = (int) ($notification['id'] ?? 0);
            $read = (int) ($notification['is_read'] ?? 0) === 1;
            $actions = '<div class="notification-row-actions">';
            if (!$read) {
                $actions .= '<form method="post" action="/instructor/notifications">' . Csrf::field() . '<input type="hidden" name="action" value="read_one"><input type="hidden" name="notification_id" value="' . $id . '"><button class="portal-button secondary" type="submit">Mark read</button></form>';
            }
            $actions .= '<form method="post" action="/instructor/notifications" onsubmit="return confirm(\'Delete this notification?\')">' . Csrf::field() . '<input type="hidden" name="action" value="delete_one"><input type="hidden" name="notification_id" value="' . $id . '"><button class="portal-button secondary notification-danger" type="submit">Delete</button></form></div>';
            $rows .= '<article class="notification-inbox-row' . ($read ? '' : ' unread') . '"><span class="status-badge ' . ($read ? 'published' : 'pending') . '">' . ($read ? 'Read' : 'New') . '</span><div><h2>' . $e($notification['title'] ?? 'CourseHub update') . '</h2><p>' . $e($notification['message'] ?? '') . '</p><small>' . $e($notification['created_at'] ?? '') . ' · ' . $e($notification['notification_type'] ?? 'general') . '</small></div>' . $actions . '</article>';
        }
        if ($rows === '') {
            $rows = '<div class="rich-empty"><h3>No instructor updates</h3><p>Application, course review, edit permission, sales and payout events will appear here.</p></div>';
        }

        $toolbar = '';
        if ($notifications !== []) {
            $toolbar = '<div class="notification-toolbar">';
            if ($unread > 0) {
                $toolbar .= '<form method="post" action="/instructor/notifications">' . Csrf::field() . '<input type="hidden" name="action" value="read_all"><button class="portal-button secondary" type="submit">Read all</button></form>';
            }
            $toolbar .= '<form method="post" action="/instructor/notifications" onsubmit="return confirm(\'Delete all notifications? This cannot be undone.\')">' . Csrf::field() . '<input type="hidden" name="action" value="delete_all"><button class="portal-button secondary notification-danger" type="submit">Delete all</button></form></div>';
        }

        $content = $alert . '<section class="notification-inbox"><header><div><span>INSTRUCTOR INBOX</span><h2>Notifications</h2><p>Review course decisions, approved edits, sales and payout events in one list.</p></div><div><strong>' . $unread . '</strong><span>unread of ' . count($notifications) . '</span></div></header>'
            . ($toolbar !== '' ? '<div class="notification-inbox-toolbar">' . $toolbar . '</div>' : '')
            . '<div class="notification-inbox-list">' . $rows . '</div></section>';
        return PortalPage::render('instructor', 'Notifications', $content);
    }
}
