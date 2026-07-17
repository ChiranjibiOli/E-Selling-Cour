<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\Contact;

use CourseHub\SharedUi\PortalShell;

final class ContactPage
{
    public function render(): string
    {
        $body = '<span class="eyebrow">Contact</span><h1>Contact CourseHub.</h1>'
            . '<p>The contact form will call a dedicated contact or notification service during the next migration slice.</p>'
            . '<form method="post" action="/contact">'
            . '<label>Name<input type="text" name="name" maxlength="100" required></label>'
            . '<label>Email<input type="email" name="email" maxlength="150" required></label>'
            . '<label>Message<input type="text" name="message" maxlength="1000" required></label>'
            . '<button type="submit" disabled>Service migration pending</button>'
            . '</form><div class="links"><a class="button secondary" href="/">Back home</a></div>';

        return PortalShell::page('Contact CourseHub', $body);
    }
}
