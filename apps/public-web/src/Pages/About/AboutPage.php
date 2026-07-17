<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\About;

use CourseHub\SharedUi\PortalShell;

final class AboutPage
{
    public function render(): string
    {
        return PortalShell::page(
            'About CourseHub',
            '<span class="eyebrow">About</span><h1>Built for practical learning.</h1><p>This page owns its route, rendering and future API dependencies inside one feature folder.</p><div class="links"><a class="button secondary" href="/">Back home</a></div>'
        );
    }
}
