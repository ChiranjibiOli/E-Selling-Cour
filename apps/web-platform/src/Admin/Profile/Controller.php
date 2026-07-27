<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;
use CourseHub\WebPlatform\Shared\Profile\AccountProfileManager;
use CourseHub\WebPlatform\Shared\Room\RoomRuntime;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    RoomRuntime::authorize(__DIR__, $request);
    return AccountProfileManager::handle(
        $request,
        'admin',
        static fn (array $profile, string $message, bool $success) => AdminProfilePage::render($profile, $message, $success),
    );
};
