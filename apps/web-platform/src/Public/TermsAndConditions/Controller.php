<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    return TermsAndConditionsPage::render();
};
