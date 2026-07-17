<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Request;

return static function (Request $request): void {
    // A signed-in instructor or administrator must not cross into the learner entrance.
    $role = (string) ($_SESSION['role'] ?? '');
    if ($role !== '' && $role !== 'student') {
        throw new DomainException('Sign out from the current portal before using the learner entrance.');
    }
};
