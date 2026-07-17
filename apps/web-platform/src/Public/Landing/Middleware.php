<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;

return static function (Request $request): void {
    // The landing page is intentionally public and performs no role-based authorization.
};
