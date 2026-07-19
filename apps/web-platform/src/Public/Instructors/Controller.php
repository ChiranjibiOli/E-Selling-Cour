<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\ApiClient;
use CourseHub\WebPlatform\Shared\Http\Request;

require_once __DIR__ . '/Page.php';

return static function (Request $request) {
    try {
        $courses = (new ApiClient())->get('/api/v1/courses?limit=48')['data'] ?? [];
        return InstructorsPage::render($courses);
    } catch (DomainException $exception) {
        return InstructorsPage::render([], $exception->getMessage());
    }
};
