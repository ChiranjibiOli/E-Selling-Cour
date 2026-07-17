<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\Terms;

use CourseHub\SharedUi\PortalShell;

final class TermsPage
{
    public function render(): string
    {
        return PortalShell::page(
            'Terms and Conditions',
            '<span class="eyebrow">Legal</span><h1>Terms and conditions.</h1><p>This page is isolated so purchase, refund, instructor and acceptable-use terms can be versioned without touching the landing page.</p><div class="notice">Production terms require legal review.</div><div class="links"><a class="button secondary" href="/">Back home</a></div>'
        );
    }
}
