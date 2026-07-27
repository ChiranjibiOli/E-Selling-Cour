<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorMessagingPage
{
    public static function render(array $user, string $message = '', bool $success = true, array $values = []): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $content = $alert . '<section class="instructor-message-console"><header><div><span>CONTACT COURSEHUB ADMIN</span><h2>Instructor support message</h2><p>Send course-review, payment, Student-access or account questions to the Admin inbox. Admin replies directly to your registered email.</p></div><strong>MS</strong></header><div class="instructor-message-identity"><span>Reply email</span><strong>' . $e($user['email'] ?? '') . '</strong><small>' . $e($user['name'] ?? 'Instructor') . '</small></div><form class="portal-form" method="post" action="/instructor/messaging" data-instructor-message-form>' . Csrf::field()
            . '<label>Subject<input type="text" name="subject" minlength="3" maxlength="200" value="' . $e($values['subject'] ?? '') . '" placeholder="Course review question" required data-error="Write a short subject explaining why Admin should open this message."></label>'
            . '<label>Message<textarea name="message" rows="9" minlength="10" maxlength="10000" placeholder="Include the course or order number and explain the problem clearly." required data-error="Write at least 10 characters explaining the issue and any related course or order number.">' . $e($values['message'] ?? '') . '</textarea></label>'
            . '<div class="instructor-message-note"><span>i</span><p>This creates a protected Admin support request. The reply is sent through CourseHub SMTP to the email shown above.</p></div><button class="portal-button" type="submit">Send message to Admin</button></form></section>';
        return PortalPage::render('instructor', 'Messages', $content);
    }
}
