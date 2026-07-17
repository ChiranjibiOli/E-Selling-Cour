<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\Landing;

use CourseHub\SharedUi\PortalShell;

final class LandingPage
{
    public function render(): string
    {
        $body = '<span class="eyebrow">CourseHub public web</span>'
            . '<h1>Learn without limits.</h1>'
            . '<p>This is the new independently deployable public application shell. The existing production landing page remains active until its components are migrated and tested.</p>'
            . '<div class="links">'
            . '<a class="button" href="/login">Sign in</a>'
            . '<a class="button secondary" href="/about">About</a>'
            . '<a class="button secondary" href="/contact">Contact</a>'
            . '<a class="button secondary" href="/privacy">Privacy</a>'
            . '<a class="button secondary" href="/terms">Terms</a>'
            . '</div>';

        return PortalShell::page('CourseHub', $body, '#7a5c2e');
    }
}
