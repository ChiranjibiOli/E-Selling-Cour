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
        $rows = '';

        foreach ($messages as $support) {
            $id = (int) ($support['id'] ?? 0);
            $status = in_array((string) ($support['status'] ?? ''), ['new', 'read', 'replied'], true)
                ? (string) $support['status']
                : 'new';
            $subject = trim((string) ($support['subject'] ?? ''));
            $subject = $subject !== '' ? $subject : 'Support request';
            $replySubject = trim((string) ($support['reply_subject'] ?? ''));
            $replySubject = $replySubject !== '' ? $replySubject : 'Re: ' . $subject;
            $delivery = (string) ($support['reply_delivery_status'] ?? 'not_sent');
            $deliveryLabel = match ($delivery) {
                'sent' => 'Email sent',
                'failed' => 'Delivery failed',
                default => 'Not replied',
            };
            $previousReply = trim((string) ($support['reply_message'] ?? ''));
            $replyHistory = $previousReply !== ''
                ? '<div class="support-reply-history"><span>' . $e($deliveryLabel) . '</span><strong>' . $e($support['reply_subject'] ?? '') . '</strong><p>' . nl2br($e($previousReply)) . '</p><small>' . $e($support['replied_at'] ?? '') . '</small></div>'
                : '';

            $rows .= '<details class="support-message-row"' . ($status === 'new' ? ' open' : '') . '>'
                . '<summary><span class="status-badge ' . $e($status) . '">' . $e($status) . '</span><div><strong>' . $e($subject) . '</strong><small>' . $e($support['name'] ?? '') . ' · ' . $e($support['email'] ?? '') . '</small></div><time>' . $e($support['created_at'] ?? '') . '</time></summary>'
                . '<div class="support-message-content"><div class="support-original-message"><span>ORIGINAL MESSAGE</span><p>' . nl2br($e($support['message'] ?? '')) . '</p></div>'
                . $replyHistory
                . '<form class="portal-form support-reply-form" method="post" action="/admin/contact-messages">' . Csrf::field()
                . '<input type="hidden" name="message_id" value="' . $id . '"><input type="hidden" name="action" value="send_reply">'
                . '<div class="support-destination"><span>Reply destination</span><strong>' . $e($support['email'] ?? '') . '</strong></div>'
                . '<label>Reply subject<input type="text" name="reply_subject" minlength="1" maxlength="200" value="' . $e($replySubject) . '" required data-error="Write the email subject that the user will receive."></label>'
                . '<label>Reply message<textarea name="reply_message" rows="7" minlength="2" maxlength="10000" placeholder="Explain the answer, required action, or resolution clearly." required data-error="Write the support answer that should be emailed to this user."></textarea></label>'
                . '<button class="portal-button" type="submit">Send reply by email</button></form>'
                . '<form class="support-status-form" method="post" action="/admin/contact-messages">' . Csrf::field()
                . '<input type="hidden" name="message_id" value="' . $id . '"><input type="hidden" name="action" value="update_status">'
                . '<label>Internal status<select name="status"><option value="new"' . ($status === 'new' ? ' selected' : '') . '>New</option><option value="read"' . ($status === 'read' ? ' selected' : '') . '>Read</option><option value="replied"' . ($status === 'replied' ? ' selected' : '') . '>Replied</option></select></label><button class="portal-button secondary" type="submit">Update status only</button></form></div></details>';
        }

        if ($rows === '') {
            $rows = '<div class="rich-empty"><h3>No support requests</h3><p>Public contact messages will appear here when a human needs assistance, which tends to happen shortly after software is released.</p></div>';
        }

        $content = $alert
            . '<section class="support-inbox"><header><div><span>SUPPORT INBOX</span><h2>Contact messages</h2><p>Open a request, write the response, and send it directly to the email used in the contact form.</p></div><strong>' . count($messages) . '</strong></header><div class="support-message-list">' . $rows . '</div></section>';

        return PortalPage::render('admin', 'Contact messages', $content);
    }
}
