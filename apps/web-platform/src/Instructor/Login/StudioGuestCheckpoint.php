<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;

return static function (Request $request): void {
    $role = (string) ($_SESSION['role'] ?? '');
    if ($role !== '' && $role !== 'instructor') {
        throw new DomainException('Sign out from the current portal before opening the instructor studio.');
    }
};
