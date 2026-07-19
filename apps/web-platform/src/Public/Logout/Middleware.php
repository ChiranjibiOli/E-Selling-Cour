<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Session\AuthSession;

return static function (Request $request): void {
    if ($request->method !== 'POST' || AuthSession::role() === '') {
        throw new DomainException('An authenticated portal session is required to sign out.');
    }
};
