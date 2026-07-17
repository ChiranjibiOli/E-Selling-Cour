<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\Privacy;

use CourseHub\SharedUi\PortalShell;

final class PrivacyPage
{
    public function render(): string
    {
        return PortalShell::page(
            'Privacy Policy',
            '<span class="eyebrow">Legal</span><h1>Privacy policy.</h1><p>This dedicated feature folder will own the production privacy content, revision date and consent references.</p><div class="notice">Legal text must be reviewed before accepting real customer data.</div><div class="links"><a class="button secondary" href="/">Back home</a></div>'
        );
    }
}
