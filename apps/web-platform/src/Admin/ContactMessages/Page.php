<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminContactMessagesPage
{
    public static function render(array $messages, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $cards = '';
        $new = $read = $replied = 0;
        foreach ($messages as $support) {
            $status = (string) ($support['status'] ?? 'new');
            if ($status === 'new') { $new++; } elseif ($status === 'read') { $read++; } else { $replied++; }
            $cards .= '<article class="portal-card notification-card' . ($status === 'new' ? ' unread' : '') . '"><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span><h2>' . $e($support['subject'] ?? 'Support request') . '</h2>'
                . '<p><strong>' . $e($support['name'] ?? '') . '</strong><br>' . $e($support['email'] ?? '') . '</p><p>' . nl2br($e($support['message'] ?? '')) . '</p><small>' . $e($support['created_at'] ?? '') . '</small>'
                . '<form class="portal-form" method="post" action="/admin/contact-messages">' . Csrf::field() . '<input type="hidden" name="message_id" value="' . (int) ($support['id'] ?? 0) . '"><label>Workflow status<select name="status"><option value="new"' . ($status === 'new' ? ' selected' : '') . '>New</option><option value="read"' . ($status === 'read' ? ' selected' : '') . '>Read</option><option value="replied"' . ($status === 'replied' ? ' selected' : '') . '>Replied</option></select></label><button class="portal-button secondary" type="submit">Update status</button></form></article>';
        }
        if ($cards === '') {
            $cards = '<div class="rich-empty"><h3>No support requests</h3><p>Public Contact submissions will appear here.</p></div>';
        }
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>New</span><i></i></div><strong>' . $new . '</strong><small>Needs first review</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Read</span><i></i></div><strong>' . $read . '</strong><small>Follow-up in progress</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Replied</span><i></i></div><strong>' . $replied . '</strong><small>Support response recorded</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Total</span><i></i></div><strong>' . count($messages) . '</strong><small>Latest support requests</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>SUPPORT CENTER</span><h3>Public contact requests</h3></div></div><div class="portal-grid">' . $cards . '</div></section>';
        return PortalPage::render('admin', 'Contact messages', $content);
    }
}
